<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuotationRequest;
use App\Models\Customer;
use App\Models\Intermediary;
use App\Models\PricingOffer;
use App\Models\Product;
use App\Models\SalesQuotation;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\QuotationService;
use App\Support\FinanceOptions;
use App\Support\OwnRecordVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(Request $request): View
    {
        SalesQuotation::current()->whereNotIn('status', ['converted', 'accepted', 'rejected', 'cancelled', 'expired'])->whereNotNull('valid_until')->whereDate('valid_until', '<', today())->get()->each(fn (SalesQuotation $quotation) => $quotation->update(['status' => 'expired', 'updated_by' => auth()->id()]));
        $quotations = OwnRecordVisibility::apply(SalesQuotation::with('customer:id,name')->current())->when($request->q, fn ($q, $v) => $q->where(fn ($x) => $x->where('number', 'like', "%$v%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$v%"))))->when($request->status, fn ($q, $v) => $q->where('status', $v))->latest('quotation_date')->paginate(config('finance.pagination'))->withQueryString();

        return view('quotations.index', ['quotations' => $quotations, 'statuses' => FinanceOptions::quotationStatuses()]);
    }

    public function create(): View
    {
        return view('quotations.form', $this->formData(new SalesQuotation));
    }

    public function store(StoreQuotationRequest $request, QuotationService $service, DocumentService $documents): RedirectResponse
    {
        $data = $request->validated();
        unset($data['attachment']);
        try {
            $quotation = $service->create($data);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['quotation' => \App\Support\SafeExceptionMessage::from($e)]);
        }if ($request->hasFile('attachment')) {
            $documents->attach($quotation, $request->file('attachment'), 'عرض المبيعات');
        }

return redirect()->route('quotations.show', $quotation)->with('success', 'تم حفظ عرض المبيعات ونسخة التسعير الكاملة.');
    }

    public function show(SalesQuotation $quotation): View
    {
        abort_unless(OwnRecordVisibility::allows($quotation), 404);
        $quotation->load(['customer', 'salesOwner', 'items.product', 'intermediary', 'contract', 'pricingOffers', 'parent', 'versions', 'attachments']);

        return view('quotations.show', compact('quotation'));
    }

    public function edit(SalesQuotation $quotation): View|RedirectResponse
    {
        abort_unless(OwnRecordVisibility::allows($quotation), 404);
        if ($quotation->status !== 'draft') {
            return redirect()->route('quotations.show', $quotation)->withErrors(['quotation' => 'العرض المرسل أو الجاري التفاوض عليه محفوظ كنسخة تاريخية. أنشئ إصدارًا جديدًا لتعديل المحتوى.']);
        }$quotation->load('items.product', 'pricingOffers');

        return view('quotations.form', $this->formData($quotation));
    }

    public function update(StoreQuotationRequest $request, SalesQuotation $quotation, QuotationService $service, DocumentService $documents): RedirectResponse
    {
        abort_unless(OwnRecordVisibility::allows($quotation), 404);
        $data = $request->validated();
        unset($data['attachment']);
        try {
            $service->update($quotation, $data);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['quotation' => \App\Support\SafeExceptionMessage::from($e)]);
        }if ($request->hasFile('attachment')) {
            $documents->attach($quotation, $request->file('attachment'), 'عرض المبيعات');
        }

return redirect()->route('quotations.show', $quotation)->with('success', 'تم تحديث العرض وحفظ Snapshot جديد.');
    }

    public function revise(SalesQuotation $quotation, QuotationService $service): RedirectResponse
    {
        abort_unless(OwnRecordVisibility::allows($quotation), 404);
        try {
            $revision = $service->revise($quotation);
        } catch (\DomainException $e) {
            return back()->withErrors(['quotation' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return redirect()->route('quotations.edit', $revision)->with('success', 'تم إنشاء إصدار جديد من العرض.');
    }

    public function updateStatus(Request $request, SalesQuotation $quotation): RedirectResponse
    {
        abort_unless(OwnRecordVisibility::allows($quotation), 404);
        if ($quotation->status === 'converted') {
            return back()->withErrors(['quotation' => 'لا يمكن تغيير حالة عرض تم تحويله إلى عقد.']);
        }
        $data = $request->validate(['status' => 'required|in:draft,sent,negotiation,accepted,rejected,expired,cancelled', 'rejection_reason' => 'nullable|string', 'lost_to_competitor' => 'nullable|string|max:255']);
        if ($data['status'] === 'accepted' && (! $quotation->is_current_version || empty($quotation->pricing_snapshot))) {
            return back()->withErrors(['quotation' => 'يجب قبول آخر إصدار محفوظ ويحتوي على نسخة تسعير.']);
        }
        $quotation->update($data + ['sent_at' => $data['status'] === 'sent' ? now() : $quotation->sent_at, 'updated_by' => auth()->id()]);

        return back()->with('success', 'تم تحديث حالة العرض.');
    }

    public function report(Request $request): View
    {
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', today()->toDateString());
        $currency = $request->input('currency', 'SAR');
        abort_unless(array_key_exists($currency, FinanceOptions::currencies()), 422);

        $base = OwnRecordVisibility::apply(SalesQuotation::current())
            ->where('currency', $currency)
            ->whereBetween('quotation_date', [$from, $to]);

        $eligible = (clone $base)->whereNotIn('status', ['draft', 'cancelled'])->count();
        $converted = (clone $base)->where('status', 'converted')->count();
        $closeDaysExpression = DB::connection()->getDriverName() === 'sqlite'
            ? 'AVG(julianday(converted_at) - julianday(quotation_date)) average_days'
            : 'AVG(DATEDIFF(converted_at, quotation_date)) average_days';

        $summary = [
            'count' => (clone $base)->count(),
            'value' => (float) (clone $base)->sum('grand_total'),
            'open_value' => (float) (clone $base)->whereIn('status', ['sent', 'negotiation'])->sum('grand_total'),
            'accepted' => (float) (clone $base)->whereIn('status', ['accepted', 'converted'])->sum('grand_total'),
            'rejected' => (float) (clone $base)->where('status', 'rejected')->sum('grand_total'),
            'converted' => $converted,
            'conversion_rate' => $eligible > 0 ? round($converted / $eligible * 100, 2) : 0,
            'manual_discount' => (float) (clone $base)->selectRaw('COALESCE(SUM(discount_total - promotional_discount_total),0) total')->value('total'),
            'promotional_discount' => (float) (clone $base)->sum('promotional_discount_total'),
            'average_value' => (float) ((clone $base)->avg('grand_total') ?? 0),
            'average_close_days' => round((float) ((clone $base)->where('status', 'converted')->whereNotNull('converted_at')->selectRaw($closeDaysExpression)->value('average_days') ?? 0), 1),
        ];

        $expiringSoon = OwnRecordVisibility::apply(SalesQuotation::current())
            ->with('customer:id,name')
            ->where('currency', $currency)
            ->whereIn('status', ['sent', 'negotiation'])
            ->whereBetween('valid_until', [today(), today()->addDays(15)])
            ->orderBy('valid_until')
            ->limit(20)
            ->get();

        $byStatus = (clone $base)
            ->select('status', DB::raw('COUNT(*) count'), DB::raw('SUM(grand_total) value'))
            ->groupBy('status')->get();

        $byOwner = (clone $base)
            ->leftJoin('users', 'users.id', '=', 'sales_quotations.sales_owner_id')
            ->selectRaw("COALESCE(users.name,'غير محدد') owner, COUNT(*) count, SUM(sales_quotations.grand_total) value, SUM(CASE WHEN sales_quotations.status = 'converted' THEN 1 ELSE 0 END) converted_count")
            ->groupBy('users.name')->orderByDesc('value')->get();

        $byActivity = (clone $base)
            ->selectRaw("activity_type, COUNT(*) count, SUM(grand_total) value, SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) converted_count")
            ->groupBy('activity_type')->orderByDesc('value')->get();

        $reasons = (clone $base)
            ->where('status', 'rejected')->whereNotNull('rejection_reason')
            ->select('rejection_reason', DB::raw('COUNT(*) count'))
            ->groupBy('rejection_reason')->orderByDesc('count')->get();

        $purchaseCosts = DB::table('purchase_allocations')
            ->join('purchase_items', 'purchase_items.id', '=', 'purchase_allocations.purchase_item_id')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->whereNotNull('purchase_allocations.contract_id')
            ->when(OwnRecordVisibility::restricts(), fn ($q) => $q->where('purchases.created_by', auth()->id()))
            ->selectRaw('purchase_allocations.contract_id, SUM(purchase_allocations.amount) purchase_cost')
            ->groupBy('purchase_allocations.contract_id');
        $expenseCosts = DB::table('expenses')
            ->whereNotNull('contract_id')->where('status', 'approved')
            ->when(OwnRecordVisibility::restricts(), fn ($q) => $q->where('expenses.created_by', auth()->id()))
            ->selectRaw('contract_id, SUM(amount) expense_cost')
            ->groupBy('contract_id');

        $offers = DB::table('sales_quotation_pricing_offer')
            ->join('pricing_offers', 'pricing_offers.id', '=', 'sales_quotation_pricing_offer.pricing_offer_id')
            ->join('sales_quotations', 'sales_quotations.id', '=', 'sales_quotation_pricing_offer.sales_quotation_id')
            ->leftJoin('contracts', 'contracts.sales_quotation_id', '=', 'sales_quotations.id')
            ->leftJoinSub($purchaseCosts, 'promotion_purchase_costs', fn ($join) => $join->on('promotion_purchase_costs.contract_id', '=', 'contracts.id'))
            ->leftJoinSub($expenseCosts, 'promotion_expense_costs', fn ($join) => $join->on('promotion_expense_costs.contract_id', '=', 'contracts.id'))
            ->where('sales_quotations.is_current_version', true)
            ->when(OwnRecordVisibility::restricts(), fn ($q) => $q->where('sales_quotations.created_by', auth()->id()))
            ->where('sales_quotations.currency', $currency)
            ->whereBetween('sales_quotations.quotation_date', [$from, $to])
            ->selectRaw("pricing_offers.name, pricing_offers.type, COUNT(*) uses_count, SUM(sales_quotations.promotional_discount_total) discount_total, SUM(sales_quotations.grand_total) sales_value, SUM(CASE WHEN sales_quotations.status = 'converted' THEN 1 ELSE 0 END) converted_count, SUM(CASE WHEN contracts.status = 'active' THEN contracts.net_total - COALESCE(promotion_purchase_costs.purchase_cost,0) - COALESCE(promotion_expense_costs.expense_cost,0) - contracts.commission_total ELSE 0 END) contractual_profit")
            ->groupBy('pricing_offers.id', 'pricing_offers.name', 'pricing_offers.type')
            ->orderByDesc('uses_count')->get();

        $topProducts = DB::table('sales_quotation_items')
            ->join('sales_quotations', 'sales_quotations.id', '=', 'sales_quotation_items.sales_quotation_id')
            ->join('products', 'products.id', '=', 'sales_quotation_items.product_id')
            ->where('sales_quotations.is_current_version', true)
            ->when(OwnRecordVisibility::restricts(), fn ($q) => $q->where('sales_quotations.created_by', auth()->id()))
            ->where('sales_quotations.currency', $currency)
            ->whereBetween('sales_quotations.quotation_date', [$from, $to])
            ->selectRaw('products.name, SUM(sales_quotation_items.quantity) requested_quantity, COUNT(DISTINCT sales_quotations.id) quotation_count, SUM(sales_quotation_items.line_net) quoted_value')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('quotation_count')->limit(15)->get();

        $bySource = (clone $base)
            ->selectRaw("COALESCE(lead_source,'غير محدد') source, COUNT(*) count, SUM(grand_total) value, SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) converted_count")
            ->groupBy('lead_source')->orderByDesc('count')->get();

        $startupSummary = (clone $base)
            ->join('customers', 'customers.id', '=', 'sales_quotations.customer_id')
            ->where('customers.segment', 'startup')
            ->selectRaw('COUNT(*) count, COALESCE(SUM(sales_quotations.promotional_discount_total),0) discount_total, COALESCE(SUM(sales_quotations.grand_total),0) sales_value')
            ->first();

        return view('quotations.report', compact(
            'summary', 'byStatus', 'byOwner', 'byActivity', 'reasons', 'offers', 'topProducts', 'bySource',
            'startupSummary', 'expiringSoon', 'from', 'to', 'currency'
        ) + ['currencies' => FinanceOptions::currencies(), 'statuses' => FinanceOptions::quotationStatuses(), 'activities' => FinanceOptions::activityTypes()]);
    }

    private function formData(SalesQuotation $quotation): array
    {
        $customers = Customer::active()->orderBy('name')->limit(50)->get(['id', 'name', 'code', 'segment']);
        if ($quotation->customer_id) {
            $customers = $customers->concat(Customer::whereKey($quotation->customer_id)->get(['id', 'name', 'code', 'segment']))->unique('id')->values();
        }$products = Product::active()->with('requiredProduct:id,name,code')->orderBy('name')->limit(100)->get();
        $selected = $quotation->items?->pluck('product_id') ?? collect();
        if ($selected->isNotEmpty()) {
            $products = $products->concat(Product::with('requiredProduct:id,name,code')->whereIn('id', $selected)->get())->unique('id')->values();
        }

return ['quotation' => $quotation, 'customers' => $customers, 'products' => $products, 'intermediaries' => Intermediary::where('is_active', true)->orderBy('name')->get(), 'salesOwners' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']), 'pricingOffers' => PricingOffer::with('products:id')->available()->orderBy('priority')->get(), 'activities' => FinanceOptions::activityTypes(), 'cycles' => FinanceOptions::billingCycles(), 'currencies' => FinanceOptions::currencies(), 'statuses' => FinanceOptions::quotationStatuses()];
    }
}
