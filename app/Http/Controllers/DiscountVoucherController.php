<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiscountVoucherRequest;
use App\Models\Customer;
use App\Models\DiscountVoucher;
use App\Models\Receivable;
use App\Services\DiscountVoucherService;
use App\Services\DocumentService;
use App\Support\FinanceOptions;
use App\Support\OwnRecordVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscountVoucherController extends Controller
{
    public function index(Request $request): View
    {
        $vouchers = OwnRecordVisibility::apply(DiscountVoucher::with('customer:id,name'))->when($request->q, fn ($q, $v) => $q->where(fn ($x) => $x->where('number', 'like', "%$v%")->orWhere('reason', 'like', "%$v%")))->latest('voucher_date')->paginate(config('finance.pagination'))->withQueryString();

        return view('discount-vouchers.index', compact('vouchers'));
    }

    public function create(Request $request): View
    {
        $customerId = $request->integer('customer_id') ?: null;
        $currency = $request->input('currency', 'SAR');
        $receivables = $customerId ? OwnRecordVisibility::apply(Receivable::outstanding()->where('customer_id', $customerId)->where('currency', $currency))->orderBy('due_date')->get() : collect();
        $customers = Customer::active()->orderBy('name')->limit(50)->get(['id', 'name', 'code']);
        if ($customerId) {
            $customers = $customers->concat(Customer::whereKey($customerId)->get(['id', 'name', 'code']))->unique('id')->values();
        }

return view('discount-vouchers.form', ['voucher' => new DiscountVoucher, 'customers' => $customers, 'receivables' => $receivables, 'selectedCustomer' => $customerId, 'selectedCurrency' => $currency, 'currencies' => FinanceOptions::currencies()]);
    }

    public function store(StoreDiscountVoucherRequest $request, DiscountVoucherService $service, DocumentService $documents): RedirectResponse
    {
        $data = $request->validated();
        unset($data['attachment']);
        try {
            $voucher = $service->create($data);
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['voucher' => \App\Support\SafeExceptionMessage::from($e)]);
        }if ($request->hasFile('attachment')) {
            $documents->attach($voucher, $request->file('attachment'), 'سند الخصم');
        }

return redirect()->route('discount-vouchers.index')->with('success', 'تم إنشاء سند الخصم وربطه بالاستحقاق.');
    }

    public function edit(DiscountVoucher $discountVoucher): View
    {
        return view('discount-vouchers.form', ['voucher' => $discountVoucher, 'customers' => Customer::whereKey($discountVoucher->customer_id)->get(['id', 'name', 'code']), 'receivables' => Receivable::where('customer_id', $discountVoucher->customer_id)->where('currency', $discountVoucher->currency)->where(fn ($q) => $q->outstanding()->orWhereKey($discountVoucher->receivable_id))->orderBy('due_date')->get(), 'selectedCustomer' => $discountVoucher->customer_id, 'selectedCurrency' => $discountVoucher->currency, 'currencies' => FinanceOptions::currencies()]);
    }

    public function update(StoreDiscountVoucherRequest $request, DiscountVoucher $discountVoucher, DiscountVoucherService $service, DocumentService $documents): RedirectResponse
    {
        $data = $request->validated();
        unset($data['attachment']);
        try {
            $service->update($discountVoucher, $data);
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['voucher' => \App\Support\SafeExceptionMessage::from($e)]);
        }if ($request->hasFile('attachment')) {
            $documents->attach($discountVoucher, $request->file('attachment'), 'سند الخصم');
        }

return redirect()->route('discount-vouchers.index')->with('success', 'تم تعديل سند الخصم.');
    }

    public function cancel(Request $request, DiscountVoucher $discountVoucher, DiscountVoucherService $service): RedirectResponse
    {
        $data = $request->validate(['cancellation_reason' => 'required|string|max:1000']);
        try {
            $service->cancel($discountVoucher, $data['cancellation_reason']);
        } catch (\DomainException $e) {
            return back()->withErrors(['voucher' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return back()->with('success', 'تم إلغاء سند الخصم.');
    }

    public function reopen(DiscountVoucher $discountVoucher, DiscountVoucherService $service): RedirectResponse
    {
        try {
            $service->reopen($discountVoucher);
        } catch (\DomainException $e) {
            return back()->withErrors(['voucher' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return back()->with('success','تمت إعادة فتح سند الخصم.');
    }
}
