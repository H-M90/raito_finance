<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCollectionRequest;
use App\Models\Collection;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Receivable;
use App\Services\CollectionService;
use App\Services\DocumentService;
use App\Support\FinanceOptions;
use App\Support\OwnRecordVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(Request $request): View
    {
        $collections = OwnRecordVisibility::apply(Collection::with(['customer:id,name', 'attachments'])->whereIn('status', ['confirmed','cancelled']))
            ->when($request->q, fn ($q, $v) => $q->where(fn ($x) => $x->where('number', 'like', "%$v%")
                ->orWhere('reference_no', 'like', "%$v%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$v%"))))
            ->latest('collection_date')->paginate(config('finance.pagination'))->withQueryString();

        return view('collections.index', compact('collections'));
    }

    public function create(Request $request): View
    {
        return view('collections.form', $this->formData(
            new Collection,
            $request->integer('customer_id') ?: null,
            $request->integer('contract_id') ?: null,
        ));
    }

    public function edit(Collection $collection): View
    {
        abort_unless(OwnRecordVisibility::allows($collection), 404);
        $collection->load('allocations.receivable');
        $contractId = (int) ($collection->allocations->first()?->receivable?->contract_id ?? 0) ?: null;

        return view('collections.form', $this->formData($collection, $collection->customer_id, $contractId));
    }

    private function formData(Collection $collection, ?int $customerId, ?int $contractId): array
    {
        $selectedContract = $contractId ? Contract::with('customer:id,name')->find($contractId) : null;
        if ($selectedContract) {
            $customerId = $selectedContract->customer_id;
            $currency = $selectedContract->currency;
        } else {
            $currency = $collection->currency ?: config('finance.default_currency');
        }

        $receivables = $contractId
            ? Receivable::where('contract_id', $contractId)
                ->where(fn ($q) => $q->outstanding()->orWhereIn('id', $collection->allocations()->pluck('receivable_id')))
                ->orderBy('due_date')->get()
            : collect();

        $customers = Customer::active()->orderBy('name')->limit(50)->get(['id', 'name', 'code']);
        if ($customerId) {
            $customers = $customers->concat(Customer::whereKey($customerId)->get(['id', 'name', 'code']))->unique('id')->values();
        }

        $contracts = Contract::with('customer:id,name')->where('status', 'active')
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->latest('contract_date')->limit(50)->get(['id', 'customer_id', 'number', 'currency', 'contract_date']);
        if ($selectedContract) {
            $contracts = $contracts->concat(collect([$selectedContract]))->unique('id')->values();
        }

        return [
            'collection' => $collection,
            'customers' => $customers,
            'contracts' => $contracts,
            'selectedCustomer' => $customerId,
            'selectedContract' => $contractId,
            'selectedCurrency' => $currency,
            'receivables' => $receivables,
            'methods' => FinanceOptions::paymentMethods(),
            'receivableTypes' => FinanceOptions::receivableTypes(),
        ];
    }

    public function outstanding(Request $request)
    {
        $data = $request->validate(['contract_id' => 'required|exists:contracts,id']);
        $contract = Contract::findOrFail($data['contract_id']);
        $labels = FinanceOptions::receivableTypes();

        return response()->json(
            OwnRecordVisibility::apply(Receivable::outstanding()->where('contract_id', $contract->id))->orderBy('due_date')->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'number' => $r->number,
                    'name' => $r->name,
                    'type' => $r->type,
                    'type_label' => $labels[$r->type] ?? 'استحقاق',
                    'due_date' => $r->due_date?->format('Y-m-d'),
                    'remaining_amount' => (float) $r->remaining_amount,
                    'currency' => $r->currency,
                    'contract_addendum_id' => $r->contract_addendum_id,
                ])->values()
        );
    }

    public function store(StoreCollectionRequest $request, CollectionService $service, DocumentService $documents): RedirectResponse
    {
        $data = $request->validated();
        try {
            $collection = $service->create($data);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['collection' => \App\Support\SafeExceptionMessage::from($e)]);
        }
        if ($request->hasFile('attachment')) {
            $documents->attach($collection, $request->file('attachment'), 'سند القبض');
        }

        return redirect()->route('customers.statement', ['customer' => $collection->customer_id, 'currency' => $collection->currency, 'contract_id' => $data['contract_id']])->with('success', 'تم تسجيل سند القبض وربطه باستحقاقات العقد.');
    }

    public function update(StoreCollectionRequest $request, Collection $collection, CollectionService $service, DocumentService $documents): RedirectResponse
    {
        abort_unless(OwnRecordVisibility::allows($collection), 404);
        $data = $request->validated();
        unset($data['attachment']);
        try {
            $service->update($collection, $data);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['collection' => \App\Support\SafeExceptionMessage::from($e)]);
        }
        if ($request->hasFile('attachment')) {
            $documents->attach($collection, $request->file('attachment'), 'سند القبض');
        }

        return redirect()->route('collections.index')->with('success', 'تم تعديل سند القبض.');
    }

    public function cancel(Request $request, Collection $collection, CollectionService $service): RedirectResponse
    {
        abort_unless(OwnRecordVisibility::allows($collection), 404);
        $data = $request->validate(['cancellation_reason' => 'required|string|max:1000']);
        try {
            $service->cancel($collection, $data['cancellation_reason']);
        } catch (\DomainException $e) {
            return back()->withErrors(['collection' => \App\Support\SafeExceptionMessage::from($e)]);
        }

        return back()->with('success', 'تم إلغاء سند القبض وعكس أثره.');
    }

    public function reopen(Collection $collection, CollectionService $service): RedirectResponse
    {
        abort_unless(OwnRecordVisibility::allows($collection), 404);
        try {
            $service->reopen($collection);
        } catch (\DomainException $e) {
            return back()->withErrors(['collection' => \App\Support\SafeExceptionMessage::from($e)]);
        }

        return back()->with('success','تمت إعادة فتح سند القبض.');
    }
}
