<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequest;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Station;
use App\Models\Supplier;
use App\Services\DocumentService;
use App\Services\PurchaseService;
use App\Support\FinanceOptions;
use App\Support\OwnRecordVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $purchases = OwnRecordVisibility::apply(Purchase::with(['supplier:id,name', 'attachments']))->when($request->q, fn ($q, $v) => $q->where(fn ($x) => $x->where('number', 'like', "%$v%")->orWhere('supplier_invoice_no', 'like', "%$v%")->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%$v%"))))->latest('purchase_date')->paginate(config('finance.pagination'))->withQueryString();

        return view('purchases.index', compact('purchases'));
    }

    public function create(): View
    {
        return view('purchases.form', $this->formData(new Purchase));
    }

    public function edit(Purchase $purchase): View
    {
        $purchase->load('items.allocations');

        return view('purchases.form', $this->formData($purchase));
    }

    private function formData(Purchase $purchase): array
    {
        $productIds = $purchase->items?->pluck('product_id')->filter() ?? collect();
        $products = Product::active()->orderBy('name')->limit(100)->get();
        if ($productIds->isNotEmpty()) {
            $products = $products->concat(Product::whereIn('id', $productIds)->get())->unique('id')->values();
        }$customerIds = $purchase->items?->flatMap(fn ($i) => $i->allocations->pluck('customer_id'))->filter() ?? collect();
        $contractIds = $purchase->items?->flatMap(fn ($i) => $i->allocations->pluck('contract_id'))->filter() ?? collect();
        $stationIds = $purchase->items?->flatMap(fn ($i) => $i->allocations->pluck('station_id'))->filter() ?? collect();
        $customers = Customer::active()->orderBy('name')->limit(50)->get(['id', 'name'])->concat(Customer::whereIn('id', $customerIds)->get(['id', 'name']))->unique('id')->values();
        $contracts = Contract::with('customer:id,name')->where('status', 'active')->orderByDesc('contract_date')->limit(100)->get(['id', 'number', 'customer_id'])->concat(Contract::with('customer:id,name')->whereIn('id', $contractIds)->get(['id', 'number', 'customer_id']))->unique('id')->values();
        $stations = Station::where('is_active', true)->orderBy('name')->limit(100)->get(['id', 'name', 'customer_id'])->concat(Station::whereIn('id', $stationIds)->get(['id', 'name', 'customer_id']))->unique('id')->values();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->limit(100)->get();
        if ($purchase->supplier_id) {
            $suppliers = $suppliers->concat(Supplier::whereKey($purchase->supplier_id)->get())->unique('id')->values();
        }

return ['purchase' => $purchase, 'suppliers' => $suppliers, 'products' => $products, 'customers' => $customers, 'contracts' => $contracts, 'stations' => $stations, 'currencies' => FinanceOptions::currencies()];
    }

    public function store(StorePurchaseRequest $request, PurchaseService $service, DocumentService $documents): RedirectResponse
    {
        $data = $request->safe()->except('attachment');
        try {
            $purchase = $service->create($data);
            if ($request->hasFile('attachment')) {
                $documents->attach($purchase, $request->file('attachment'), 'فاتورة المورد');
            }
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['purchase' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return redirect()->route('purchases.show', $purchase)->with('success', 'تم تسجيل فاتورة الشراء كمعتمدة.');
    }

    public function update(StorePurchaseRequest $request, Purchase $purchase, PurchaseService $service, DocumentService $documents): RedirectResponse
    {
        $data = $request->safe()->except('attachment');
        try {
            $service->update($purchase, $data);
            if ($request->hasFile('attachment')) {
                $documents->attach($purchase, $request->file('attachment'), 'فاتورة المورد');
            }
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['purchase' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return redirect()->route('purchases.show', $purchase)->with('success', 'تم تعديل فاتورة الشراء.');
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load('supplier', 'items.product', 'items.allocations.customer', 'items.allocations.contract', 'items.allocations.station', 'attachments');

        return view('purchases.show', compact('purchase'));
    }

    public function destroy(Purchase $purchase, PurchaseService $service): RedirectResponse
    {
        $service->delete($purchase);

        return redirect()->route('purchases.index')->with('success','تم حذف فاتورة الشراء نهائيًا بكل توزيعاتها.');
    }
}
