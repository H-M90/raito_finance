<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManualReceivableRequest;
use App\Http\Requests\StoreReceivablePaymentRequest;
use App\Http\Requests\UpdateImportedReceivableRequest;
use App\Models\Contract;
use App\Models\Receivable;
use App\Services\CollectionService;
use App\Services\ReceivableService;
use App\Support\OwnRecordVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceivableController extends Controller
{
    public function index(Request $request): View
    {
        $query = OwnRecordVisibility::apply(Receivable::with(['customer:id,name', 'contract:id,number']))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('currency'), fn ($q) => $q->where('currency', $request->currency))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('due_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('due_date', '<=', $request->to))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($x) => $x->where('number', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$request->q.'%'))));

        $status = $request->string('status')->toString();
        match ($status) {
            'future' => $query->outstanding()->whereDate('due_date', '>', today()),
            'due' => $query->outstanding()->whereDate('due_date', today()),
            'overdue' => $query->outstanding()->whereDate('due_date', '<', today()),
            'partially_paid' => $query->where('collected_amount', '>', 0)->where('remaining_amount', '>', 0),
            'paid' => $query->where('remaining_amount', '<=', 0),
            default => null,
        };

        $summaryByCurrency = (clone $query)->selectRaw('currency, COUNT(*) count_rows, SUM(total_amount) total_amount_sum, SUM(collected_amount) collected_amount_sum, SUM(remaining_amount) remaining_amount_sum')->groupBy('currency')->get()->keyBy('currency');
        $receivables = $query->withCount('collectionAllocations')->orderBy('due_date')->paginate(config('finance.pagination'))->withQueryString();
        $types = [
            'installment' => 'دفعات العقود', 'addendum_installment' => 'دفعات الملحقات', 'monthly' => 'اشتراك شهري', 'annual' => 'اشتراك سنوي',
            'maintenance' => 'صيانة سنوية', 'opening_contract' => 'رصيد افتتاحي دفعات', 'opening_maintenance' => 'رصيد افتتاحي صيانة',
        ];
        $types['legacy_import_due'] = 'استحقاق مستورد';
        $types['manual'] = 'استحقاق يدوي';

        return view('receivables.index', compact('receivables', 'summaryByCurrency', 'types'));
    }

    public function edit(Receivable $receivable): View
    {
        abort_unless($receivable->type === 'legacy_import_due', 404);

        $receivable->load(['customer:id,name', 'contract:id,number']);

        return view('receivables.edit', compact('receivable'));
    }

    public function create(): View
    {
        $contracts = Contract::with('customer:id,name')
            ->where('status', 'active')
            ->latest('contract_date')
            ->limit(200)
            ->get(['id', 'customer_id', 'number', 'currency', 'contract_date']);

        return view('receivables.create', compact('contracts'));
    }

    public function store(StoreManualReceivableRequest $request, ReceivableService $service): RedirectResponse
    {
        $contract = Contract::findOrFail($request->integer('contract_id'));

        try {
            $receivable = $service->createManual($contract, $request->validated());
        } catch (\DomainException $exception) {
            return back()->withInput()->withErrors(['receivable' => \App\Support\SafeExceptionMessage::from($exception)]);
        }

        return redirect()
            ->route('receivables.index', ['q' => $receivable->number])
            ->with('success', 'تمت إضافة الاستحقاق وربطه بقيد حساب العميل.');
    }

    public function update(
        UpdateImportedReceivableRequest $request,
        Receivable $receivable,
        ReceivableService $service,
    ): RedirectResponse {
        try {
            $service->updateImportedDue($receivable, $request->validated());
        } catch (\DomainException $exception) {
            return back()->withInput()->withErrors(['receivable' => \App\Support\SafeExceptionMessage::from($exception)]);
        }

        return redirect()
            ->route('receivables.index', ['q' => $receivable->number])
            ->with('success', 'تم تعديل الاستحقاق وتحديث قيد حساب العميل.');
    }

    public function paymentCreate(Receivable $receivable): View
    {
        $this->ensureReceivableIsPayable($receivable);

        $receivable->load(['customer:id,name', 'contract:id,number']);

        return view('receivables.payment', compact('receivable'));
    }

    public function paymentStore(
        StoreReceivablePaymentRequest $request,
        Receivable $receivable,
        CollectionService $service,
    ): RedirectResponse {
        $receivable->refresh();
        $this->ensureReceivableIsPayable($receivable);

        try {
            $collection = $service->create([
                'customer_id' => $receivable->customer_id,
                'contract_id' => $receivable->contract_id,
                'collection_date' => $request->validated('collection_date'),
                'currency' => $receivable->currency,
                'amount' => $request->validated('amount'),
                'payment_method' => 'other',
                'notes' => 'سداد سريع من شاشة الاستحقاقات: '.$receivable->number,
                'allocations' => [[
                    'receivable_id' => $receivable->id,
                    'amount' => $request->validated('amount'),
                ]],
            ]);
        } catch (\Throwable $exception) {
            return back()->withInput()->withErrors(['receivable' => \App\Support\SafeExceptionMessage::from($exception)]);
        }

        return redirect()
            ->route('receivables.index', ['q' => $receivable->number])
            ->with('success', 'تم تسجيل سند القبض '.$collection->number.' وتحديث الاستحقاق.');
    }

    private function ensureReceivableIsPayable(Receivable $receivable): void
    {
        abort_if(
            (float) $receivable->remaining_amount <= 0
            || ! $receivable->contract_id
            || in_array($receivable->status, ['cancelled', 'waived'], true),
            404,
        );
    }
}
