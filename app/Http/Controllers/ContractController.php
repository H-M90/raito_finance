<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Customer;
use App\Models\Intermediary;
use App\Models\PricingOffer;
use App\Models\Product;
use App\Models\SalesQuotation;
use App\Models\User;
use App\Services\ContractItemLifecycleService;
use App\Services\ContractItemPricingService;
use App\Services\ContractMaintenanceService;
use App\Services\ContractService;
use App\Services\DocumentService;
use App\Services\RecurringReceivableGenerator;
use App\Support\FinanceOptions;
use App\Support\OwnRecordVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function index(Request $request, RecurringReceivableGenerator $generator): View
    {
        $contractsQuery = OwnRecordVisibility::apply(Contract::with(['customer:id,name', 'items.product', 'maintenanceSettings']))
            ->when($request->filled('number'), fn ($query) => $query->where('number', 'like', '%'.$request->string('number')->trim().'%'))
            ->when($request->filled('customer'), fn ($query) => $query->whereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$request->string('customer')->trim().'%')))
            ->when($request->filled('service_start_from'), fn ($query) => $query->whereDate('service_start_date', '>=', $request->input('service_start_from')))
            ->when($request->filled('service_start_to'), fn ($query) => $query->whereDate('service_start_date', '<=', $request->input('service_start_to')))
            ->when($request->filled('billing_cycle'), fn ($query) => $query->where('billing_cycle', $request->input('billing_cycle')))
            ->when($request->filled('next_due_from'), fn ($query) => $query->whereDate(DB::raw('COALESCE(next_maintenance_date, next_billing_date)'), '>=', $request->input('next_due_from')))
            ->when($request->filled('next_due_to'), fn ($query) => $query->whereDate(DB::raw('COALESCE(next_maintenance_date, next_billing_date)'), '<=', $request->input('next_due_to')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest('contract_date');

        $withCurrentMaintenance = function ($contracts) use ($generator) {
            return $contracts->map(function (Contract $contract) use ($generator) {
                $contract->setAttribute('current_maintenance_total', $contract->billing_cycle === 'one_time' ? $generator->maintenanceNetAt($contract, today()) : 0);

                return $contract;
            });
        };

        $contracts = $contractsQuery->paginate(config('finance.pagination'))->withQueryString();
        $contracts->setCollection($withCurrentMaintenance($contracts->getCollection()));

        return view('contracts.index', ['contracts' => $contracts, 'statuses' => ['active' => 'مفعل', 'cancelled' => 'ملغي']]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->filled('quotation_id')) {
            abort_unless($request->user()->hasPermission('quotations.convert'), 403);
        }
        $quotation = $request->integer('quotation_id')
            ? SalesQuotation::with('items.product', 'pricingOffers', 'contract')->findOrFail($request->integer('quotation_id'))
            : null;

        if ($quotation && ($quotation->status !== 'accepted' || ! $quotation->is_current_version || $quotation->converted_at || $quotation->contract)) {
            return redirect()->route('quotations.show', $quotation)
                ->withErrors(['quotation' => 'لا يمكن التحويل إلا من آخر إصدار مقبول ولم يتحول إلى عقد من قبل.']);
        }

        return view('contracts.form', $this->formData(new Contract, $quotation));
    }

    public function store(StoreContractRequest $request, ContractService $service, DocumentService $documents): RedirectResponse
    {
        $data = $request->validated();
        if (! empty($data['sales_quotation_id'])) {
            abort_unless($request->user()->hasPermission('quotations.convert'), 403);
        }unset($data['attachment']);
        try {
            $contract = $service->create($data);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['contract' => \App\Support\SafeExceptionMessage::from($e)]);
        }if ($request->hasFile('attachment')) {
            $documents->attach($contract, $request->file('attachment'), 'العقد الموقع');
        }

return redirect()->route('contracts.show', $contract)->with('success', 'تم إنشاء العقد وتفعيله وإنشاء استحقاقاته.');
    }

    public function show(Contract $contract, RecurringReceivableGenerator $generator): View
    {
        $contract->load(['customer', 'salesOwner', 'intermediary', 'quotation', 'items.product', 'items.stoppedBy', 'maintenanceSettings.creator', 'installments', 'receivables' => fn ($q) => $q->orderBy('due_date'), 'addendums.items.product', 'pricingOffers', 'purchaseAllocations', 'expenses', 'installations.stations', 'stations', 'attachments']);
        $purchaseCost = (float) $contract->purchaseAllocations->sum('amount');
        $expenseCost = (float) $contract->expenses->where('status', 'approved')->sum('amount');
        $revenue = (float) $contract->net_total + (float) $contract->addendums->where('status', 'active')->sum('net_total');
        $profit = $revenue - $purchaseCost - $expenseCost - (float) $contract->commission_total;
        $currentRecurringNet = in_array($contract->billing_cycle, ['monthly', 'annual'], true) ? $generator->recurringNetAt($contract, today()) : null;
        $currentMaintenanceNet = $contract->billing_cycle === 'one_time' ? $generator->maintenanceNetAt($contract, today()) : null;
        $currentMaintenanceSetting = $contract->maintenanceSettings->filter(fn ($row) => $row->effective_from->lte(today()))->sortByDesc(fn ($row) => $row->effective_from->format('Y-m-d').'#'.str_pad((string) $row->id, 20, '0', STR_PAD_LEFT))->first();

        return view('contracts.show', compact('contract', 'purchaseCost', 'expenseCost', 'revenue', 'profit', 'currentRecurringNet', 'currentMaintenanceNet', 'currentMaintenanceSetting'));
    }

    public function edit(Contract $contract): View
    {
        $contract->load('items.product', 'installments', 'pricingOffers', 'stations');

        return view('contracts.form', $this->formData($contract, $contract->quotation));
    }

    public function update(StoreContractRequest $request, Contract $contract, ContractService $service, DocumentService $documents): RedirectResponse
    {
        $data = $request->validated();
        unset($data['attachment']);
        try {
            $service->update($contract, $data);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['contract' => \App\Support\SafeExceptionMessage::from($e)]);
        }if ($request->hasFile('attachment')) {
            $documents->attach($contract, $request->file('attachment'), 'تعديل العقد');
        }

return redirect()->route('contracts.show', $contract)->with('success', 'تم تحديث العقد مع الحفاظ على سلامة الاستحقاقات والحركات المالية.');
    }

    public function generateHistorical(Request $request, Contract $contract, ContractService $service): RedirectResponse
    {
        $data = $request->validate([
            'from_date' => 'required|date',
            'until_date' => 'nullable|date|after_or_equal:from_date|before_or_equal:today',
        ]);
        try {
            $created = $service->generateHistoricalReceivables($contract, $data['from_date'], $data['until_date'] ?? null);
        } catch (\DomainException $e) {
            return back()->withErrors(['historical_generation' => \App\Support\SafeExceptionMessage::from($e)]);
        }

        return back()->with('success', 'تم التوليد التاريخي بنجاح. الاستحقاقات الجديدة: '.$created.'، وأي استحقاق موجود مسبقًا لم يتكرر.');
    }

    public function updateMaintenance(Request $request, Contract $contract, ContractMaintenanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'mode' => 'required|in:auto,manual',
            'annual_amount' => 'nullable|required_if:mode,manual|numeric|min:0',
            'effective_from' => 'required|date',
            'reason' => 'nullable|string|max:1000',
        ]);
        try {
            $result = $service->update($contract, $data['mode'], isset($data['annual_amount']) ? (float) $data['annual_amount'] : null, $data['effective_from'], $data['reason'] ?? null);
        } catch (\DomainException $e) {
            return back()->withErrors(['maintenance_setting' => \App\Support\SafeExceptionMessage::from($e)]);
        }

        return back()->with('success', 'تم تحديث طريقة احتساب الصيانة وتعديل '.$result['adjusted_receivables'].' استحقاق غير محصل.');
    }

    public function stopItem(Request $request, Contract $contract, ContractItem $item, ContractItemLifecycleService $service): RedirectResponse
    {
        abort_unless((int) $item->contract_id === (int) $contract->id, 404);
        $data = $request->validate([
            'effective_date' => 'required|date',
            'stop_reason' => 'nullable|string|max:1000',
        ]);
        try {
            $result = $service->stop($contract, $item, $data['effective_date'], $data['stop_reason'] ?? null);
        } catch (\DomainException $e) {
            return back()->withErrors(['contract_item' => \App\Support\SafeExceptionMessage::from($e)]);
        }

        return back()->with('success', 'تم إيقاف البند اعتبارًا من '.$data['effective_date'].' وتحديث '.$result['adjusted_receivables'].' استحقاق غير محصل.');
    }

    public function updateItem(Request $request, Contract $contract, ContractItem $item, ContractItemPricingService $service): RedirectResponse
    {
        abort_unless((int) $item->contract_id === (int) $contract->id, 404);
        $data = $request->validate([
            'requested_users' => ['nullable', 'integer', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'user_unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        try {
            $result = $service->update($contract, $item, $data);
        } catch (\DomainException $exception) {
            return back()->withErrors(['contract_item' => \App\Support\SafeExceptionMessage::from($exception)]);
        }

        return back()->with('success', 'تم تحديث البند واحتساب '.$result['adjusted_receivables'].' استحقاق غير محصل.');
    }

    public function reopenItem(Contract $contract, ContractItem $item, ContractItemLifecycleService $service): RedirectResponse
    {
        abort_unless((int) $item->contract_id === (int) $contract->id, 404);
        try {
            $result = $service->reopen($contract, $item);
        } catch (\DomainException $exception) {
            return back()->withErrors(['contract_item' => \App\Support\SafeExceptionMessage::from($exception)]);
        }

        return back()->with('success', 'تمت إعادة تفعيل البند وتحديث '.$result['adjusted_receivables'].' استحقاق غير محصل.');
    }

    public function cancel(Request $request, Contract $contract, ContractService $service): RedirectResponse
    {
        $data = $request->validate(['cancellation_reason' => 'required|string|max:1000']);
        try {
            $service->cancel($contract, $data['cancellation_reason']);
        } catch (\DomainException $e) {
            return back()->withErrors(['contract' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return back()->with('success', 'تم إلغاء العقد.');
    }

    public function reopen(Contract $contract, ContractService $service): RedirectResponse
    {
        try {
            $service->reopen($contract);
        } catch (\DomainException $e) {
            return back()->withErrors(['contract' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return back()->with('success', 'تمت إعادة فتح العقد.');
    }

    private function formData(Contract $contract, ?SalesQuotation $quotation = null): array
    {
        $customerId = $contract->customer_id ?: $quotation?->customer_id;
        $customers = Customer::active()->orderBy('name')->limit(50)->get(['id', 'name', 'code', 'segment']);
        if ($customerId) {
            $customers = $customers->concat(Customer::whereKey($customerId)->get(['id', 'name', 'code', 'segment']))->unique('id')->values();
        }$products = Product::active()->with('requiredProduct:id,name,code')->orderBy('name')->limit(100)->get();
        $selected = ($contract->items?->pluck('product_id') ?? collect())->merge($quotation?->items?->pluck('product_id') ?? collect());
        if ($selected->isNotEmpty()) {
            $products = $products->concat(Product::with('requiredProduct:id,name,code')->whereIn('id', $selected)->get())->unique('id')->values();
        }

return ['contract' => $contract, 'quotation' => $quotation, 'customers' => $customers, 'products' => $products, 'intermediaries' => Intermediary::where('is_active', true)->orderBy('name')->get(), 'salesOwners' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']), 'pricingOffers' => PricingOffer::with('products:id')->available()->orderBy('priority')->get(), 'activities' => FinanceOptions::activityTypes(), 'cycles' => FinanceOptions::billingCycles(), 'currencies' => FinanceOptions::currencies(), 'statuses' => ['active' => 'مفعل']];
    }
}
