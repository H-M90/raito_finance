<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerAttentionFlagType;
use App\Models\CustomerPlaybook;
use App\Models\CustomerSuccessStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\NumberGenerator;
use App\Services\DocumentService;
use App\Services\CustomerSuccessService;
use Illuminate\Support\Facades\DB;
use App\Support\OwnRecordVisibility;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = auth()->user();
        $successStatuses = collect();
        $segments = Customer::query()->whereNotNull('segment')->distinct()->orderBy('segment')->pluck('segment');
        $cities = Customer::query()->whereNotNull('city')->where('city', '<>', '')->distinct()->orderBy('city')->pluck('city');
        $salesOwners = User::query()->whereIn('id', Customer::query()->whereNotNull('sales_owner_id')->select('sales_owner_id'))->orderBy('name')->get(['id','name']);
        $query = Customer::query()->withCount(['contracts','stations']);
        if ($viewer->hasPermission('customer-success.view')) {
            $query->with([
                'successProfile.lifecycleStatus',
                'attentionFlags'=>fn($q)=>$q->where('status','open')->with('type')->latest('opened_at'),
            ])->withCount([
                'attentionFlags as open_attention_flags_count'=>fn($q)=>$q->where('status','open'),
                'successTasks as open_success_tasks_count'=>fn($q)=>$q->open(),
            ]);
            $successStatuses = CustomerSuccessStatus::where('is_active',true)->orderBy('sort_order')->get(['id','code','name']);
        }
        $customers = $query->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status',$request->status))
            ->when($request->filled('segment'), fn ($q) => $q->where('segment',$request->segment))
            ->when($request->filled('city'), fn ($q) => $q->where('city',$request->city))
            ->when($request->filled('sales_owner_id'), fn ($q) => $q->where('sales_owner_id',$request->sales_owner_id))
            ->when($request->contracts === 'yes', fn ($q) => $q->has('contracts'))
            ->when($request->contracts === 'no', fn ($q) => $q->doesntHave('contracts'))
            ->when($viewer->hasPermission('customer-success.view') && $request->filled('success_status'), fn ($q) => $q->whereHas('successProfile.lifecycleStatus', fn ($s) => $s->where('code',$request->success_status)))
            ->latest()->paginate(config('finance.pagination'))->withQueryString();
        return view('customers.index', compact('customers','successStatuses','segments','cities','salesOwners'));
    }

    public function create(): View { return view('customers.form', ['customer'=>new Customer,'salesOwners'=>User::where('is_active',true)->orderBy('name')->get(['id','name'])]); }

    public function store(StoreCustomerRequest $request, NumberGenerator $numbers, DocumentService $documents): RedirectResponse
    {
        $data = $request->safe()->except('attachment');
        $data['code'] = $numbers->uniqueCode('customers','CUS');
        $customer = Customer::create($data);
        if ($request->hasFile('attachment')) $documents->attach($customer, $request->file('attachment'), 'مرفق العميل');
        return redirect()->route('customers.show',$customer)->with('success','تمت إضافة العميل بنجاح.');
    }

    public function quickStore(StoreCustomerRequest $request, NumberGenerator $numbers): JsonResponse
    {
        $data = $request->safe()->except('attachment');
        $data['code'] = $numbers->uniqueCode('customers','CUS');
        $data['status'] = 'active';
        $customer = Customer::create($data);
        return response()->json(['id'=>$customer->id,'name'=>$customer->name,'code'=>$customer->code,'segment'=>$customer->segment], 201);
    }

    public function show(Customer $customer, CustomerSuccessService $customerSuccess): View
    {
        $viewer = auth()->user();
        if ($viewer->hasPermission('customer-success.view')) {
            $customerSuccess->ensureProfile($customer);
            $customerSuccess->ensureAutomaticFollowUp($customer);
        }
        $customer->loadCount([
            'contracts as contracts_count'=>fn($q)=>OwnRecordVisibility::apply($q, $viewer),
            'stations',
            'quotations as quotations_count'=>fn($q)=>OwnRecordVisibility::apply($q, $viewer)->where('is_current_version',true),
        ])->load([
            'contracts'=>fn($q)=>OwnRecordVisibility::apply($q, $viewer)->latest('contract_date')->limit(10),
            'quotations'=>fn($q)=>OwnRecordVisibility::apply($q, $viewer)->where('is_current_version',true)->latest('quotation_date')->limit(8),
            'receivables'=>fn($q)=>OwnRecordVisibility::apply($q, $viewer)->outstanding()->orderBy('due_date')->limit(10),
            'collections'=>fn($q)=>OwnRecordVisibility::apply($q, $viewer)->where('status','confirmed')->latest('collection_date')->limit(10),
            'stations'=>fn($q)=>$q->orderBy('name')->limit(12),
            'timelineEvents'=>fn($q)=>OwnRecordVisibility::apply($q, $viewer)->latest('event_at')->latest('id')->limit(12),
            'attachments'=>fn($q)=>$q->latest(),
        ]);

        if ($viewer->hasPermission('sales-leads.view')) {
            $customer->load(['sourceSalesLead' => function ($q) use ($viewer) {
                if (OwnRecordVisibility::restricts($viewer)) {
                    $q->where(function ($x) use ($viewer) {
                        $x->where('owner_id', $viewer->id)->orWhere('created_by', $viewer->id);
                    });
                }
            }]);
        }

        $successData = null;
        if ($viewer->hasPermission('customer-success.view')) {
            $customer->load([
                'successProfile.lifecycleStatus',
                'attentionFlags'=>fn($q)=>$q->with(['type','playbookRun'])->latest('opened_at'),
                'commercialSignals'=>fn($q)=>$q->with('owner')->latest(),
                'contacts'=>fn($q)=>$q->orderByDesc('is_active')->orderByDesc('is_primary')->latest('id'),
                'playbookRuns'=>fn($q)=>$q->with(['playbook','tasks.assignee'])->latest('started_at')->limit(15),
                'successTasks'=>fn($q)=>$q->with(['assignee','completedBy','playbookRun.playbook'])->orderByRaw("CASE status WHEN 'open' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END")->orderBy('due_at')->latest('id')->limit(60),
                'successStatusHistory'=>fn($q)=>$q->with(['fromStatus','toStatus','changedBy'])->latest('changed_at')->limit(40),
                'successEvents'=>fn($q)=>$q->latest('occurred_at')->limit(25),
            ]);
            $successData = [
                'statuses'=>CustomerSuccessStatus::where('is_active',true)->orderBy('sort_order')->get(),
                'flagTypes'=>CustomerAttentionFlagType::where('is_active',true)->orderBy('name')->get(),
                'playbooks'=>CustomerPlaybook::where('is_active',true)->orderBy('name')->get(),
                'users'=>User::where('is_active',true)->orderBy('name')->get(['id','name']),
                'health'=>CustomerSuccessService::HEALTH,
                'signalTypes'=>CustomerSuccessService::SIGNALS,
                'runResultOptions'=>CustomerSuccessService::RESULT_OPTIONS,
            ];
        }

        $receivableSummary = DB::table('receivables')
            ->where('customer_id',$customer->id)->whereNull('deleted_at')->when(OwnRecordVisibility::restricts($viewer), fn($q)=>$q->where('created_by',$viewer->id))->where('status','!=','cancelled')
            ->selectRaw("currency, SUM(total_amount) billed, SUM(collected_amount) collected, SUM(discounted_amount) discounted, SUM(remaining_amount) outstanding, SUM(CASE WHEN remaining_amount > 0 AND due_date < ? THEN remaining_amount ELSE 0 END) overdue", [today()->toDateString()])
            ->groupBy('currency')->get()->keyBy('currency');
        $contractSummary = DB::table('contracts')->where('customer_id',$customer->id)->whereNull('deleted_at')->when(OwnRecordVisibility::restricts($viewer), fn($q)=>$q->where('created_by',$viewer->id))->where('status','active')
            ->selectRaw('currency, COUNT(*) contracts_count, SUM(net_total) contract_net')->groupBy('currency')->get()->keyBy('currency');
        $expenseSummary = DB::table('expenses')->join('contracts','contracts.id','=','expenses.contract_id')
            ->where('contracts.customer_id',$customer->id)->where('expenses.status','approved')->when(OwnRecordVisibility::restricts($viewer), fn($q)=>$q->where('expenses.created_by',$viewer->id))
            ->selectRaw('contracts.currency currency, SUM(expenses.amount) expenses')->groupBy('contracts.currency')->get()->keyBy('currency');
        $purchaseSummary = DB::table('purchase_allocations')->join('purchase_items','purchase_items.id','=','purchase_allocations.purchase_item_id')->join('purchases','purchases.id','=','purchase_items.purchase_id')->join('contracts','contracts.id','=','purchase_allocations.contract_id')
            ->where('contracts.customer_id',$customer->id)->where('purchases.status','confirmed')->when(OwnRecordVisibility::restricts($viewer), fn($q)=>$q->where('purchases.created_by',$viewer->id))
            ->selectRaw('contracts.currency currency, SUM(purchase_allocations.amount) purchases')->groupBy('contracts.currency')->get()->keyBy('currency');
        $commissionSummary = DB::table('intermediary_commissions')->join('contracts','contracts.id','=','intermediary_commissions.contract_id')
            ->where('contracts.customer_id',$customer->id)->where('intermediary_commissions.status','!=','cancelled')->when(OwnRecordVisibility::restricts($viewer), fn($q)=>$q->where('intermediary_commissions.created_by',$viewer->id))
            ->selectRaw('intermediary_commissions.currency currency, SUM(intermediary_commissions.amount) commissions, SUM(intermediary_commissions.paid_amount) commission_paid')->groupBy('intermediary_commissions.currency')->get()->keyBy('currency');
        $quoteSummary = DB::table('sales_quotations')->where('customer_id',$customer->id)->where('is_current_version',true)->when(OwnRecordVisibility::restricts($viewer), fn($q)=>$q->where('created_by',$viewer->id))
            ->selectRaw('currency, COUNT(*) quotations_count, SUM(grand_total) quotation_value')->groupBy('currency')->get()->keyBy('currency');

        $currencies = collect([$receivableSummary,$contractSummary,$expenseSummary,$purchaseSummary,$commissionSummary,$quoteSummary])
            ->flatMap(fn($rows)=>$rows->keys())->unique()->sort()->values();
        $financeSummary = $currencies->map(function($currency) use($receivableSummary,$contractSummary,$expenseSummary,$purchaseSummary,$commissionSummary,$quoteSummary){
            return (object)[
                'currency'=>$currency,
                'contracts_count'=>(int)($contractSummary->get($currency)->contracts_count??0),
                'contract_net'=>(float)($contractSummary->get($currency)->contract_net??0),
                'quotations_count'=>(int)($quoteSummary->get($currency)->quotations_count??0),
                'quotation_value'=>(float)($quoteSummary->get($currency)->quotation_value??0),
                'billed'=>(float)($receivableSummary->get($currency)->billed??0),
                'collected'=>(float)($receivableSummary->get($currency)->collected??0),
                'discounted'=>(float)($receivableSummary->get($currency)->discounted??0),
                'outstanding'=>(float)($receivableSummary->get($currency)->outstanding??0),
                'overdue'=>(float)($receivableSummary->get($currency)->overdue??0),
                'expenses'=>(float)($expenseSummary->get($currency)->expenses??0),
                'purchases'=>(float)($purchaseSummary->get($currency)->purchases??0),
                'commissions'=>(float)($commissionSummary->get($currency)->commissions??0),
                'commission_paid'=>(float)($commissionSummary->get($currency)->commission_paid??0),
            ];
        });

        return view('customers.show', compact('customer','financeSummary','successData'));
    }

    public function edit(Customer $customer): View { $salesOwners=User::where('is_active',true)->orderBy('name')->get(['id','name']); return view('customers.form', compact('customer','salesOwners')); }
    public function update(StoreCustomerRequest $request, Customer $customer, DocumentService $documents): RedirectResponse
    {
        $customer->update($request->safe()->except(['code','attachment']));
        if ($request->hasFile('attachment')) $documents->attach($customer, $request->file('attachment'), 'مرفق العميل');
        return redirect()->route('customers.show',$customer)->with('success','تم تحديث بيانات العميل.');
    }

}
