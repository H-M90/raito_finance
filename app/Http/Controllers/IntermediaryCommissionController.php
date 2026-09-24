<?php

namespace App\Http\Controllers;

use App\Models\Intermediary;
use App\Models\IntermediaryCommission;
use App\Services\IntermediaryCommissionService;
use App\Support\FinanceOptions;
use App\Support\OwnRecordVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntermediaryCommissionController extends Controller
{
    public function index(Request $request): View
    {
        $query = OwnRecordVisibility::apply(IntermediaryCommission::with([
            'intermediary:id,name',
            'contract:id,number,net_total,commission_total,commission_type,commission_value',
            'collection:id,number,collection_date',
            'payments' => fn ($q) => $q->latest('payment_date'),
        ]))
            ->when($request->filled('intermediary_id'), fn ($q) => $q->where('intermediary_id', $request->integer('intermediary_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('currency'), fn ($q) => $q->where('currency', $request->string('currency')->toString()))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('due_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('due_date', '<=', $request->input('to')));

        $summaryByCurrency = (clone $query)
            ->selectRaw('currency, SUM(amount) total_amount, SUM(paid_amount) paid_amount, SUM(remaining_amount) remaining_amount')
            ->groupBy('currency')->get()->keyBy('currency');

        $commissions = $query->latest('due_date')->paginate(config('finance.pagination'))->withQueryString();

        return view('intermediary-commissions.index', [
            'commissions' => $commissions,
            'summaryByCurrency' => $summaryByCurrency,
            'intermediaries' => Intermediary::orderBy('name')->get(['id', 'name']),
            'currencies' => FinanceOptions::currencies(),
            'methods' => FinanceOptions::paymentMethods(),
        ]);
    }

    public function pay(Request $request, IntermediaryCommission $commission, IntermediaryCommissionService $service): RedirectResponse
    {
        $data = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,transfer,cheque,card,other',
            'reference_no' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            $service->pay($commission, $data);
        } catch (\DomainException $e) {
            return back()->withErrors(['commission' => \App\Support\SafeExceptionMessage::from($e)]);
        }

        return back()->with('success', 'تم تسجيل سند صرف العمولة.');
    }
}
