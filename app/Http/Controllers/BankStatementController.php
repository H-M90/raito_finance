<?php

namespace App\Http\Controllers;

use App\Models\BankStatementBatch;
use App\Models\BankStatementRow;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Receivable;
use App\Services\BankStatementService;
use App\Support\FinanceOptions;
use App\Support\OwnRecordVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankStatementController extends Controller
{
    public function index(): View
    {
        $batches = OwnRecordVisibility::apply(BankStatementBatch::with(['creator:id,name', 'approver:id,name']))->latest()->paginate(15);

        return view('bank-statements.index', ['batches' => $batches, 'currencies' => FinanceOptions::currencies()]);
    }

    public function upload(Request $request, BankStatementService $service): RedirectResponse
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
            'bank_name' => 'nullable|string|max:255', 'account_name' => 'nullable|string|max:255', 'currency' => 'required|in:SAR,USD,EGP',
        ]);
        try {
            $batch = $service->upload($request->file('file'), $data);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => \App\Support\SafeExceptionMessage::from($e)]);
        }

        return redirect()->route('bank-statements.mapping', $batch)->with('success', 'تم رفع كشف البنك. راجع ربط أعمدة الملف قبل قراءة الحركات.');
    }

    public function mapping(BankStatementBatch $batch): View
    {
        abort_if($batch->status === 'approved', 404);

        return view('bank-statements.mapping', compact('batch'));
    }

    public function parse(Request $request, BankStatementBatch $batch, BankStatementService $service): RedirectResponse
    {
        $data = $request->validate([
            'mapping.date' => 'required|integer|min:0', 'mapping.description' => 'required|integer|min:0', 'mapping.reference' => 'nullable|integer|min:0',
            'mapping.debit' => 'required|integer|min:0', 'mapping.credit' => 'required|integer|min:0',
        ]);
        $mapping = $data['mapping'];
        try {
            $service->parse($batch, $mapping);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['mapping' => \App\Support\SafeExceptionMessage::from($e)]);
        }

        return redirect()->route('bank-statements.show', $batch)->with('success', 'تمت قراءة الحركات. راجع كل سطر واحفظ تصنيفه قبل الاعتماد.');
    }

    public function show(Request $request, BankStatementBatch $batch): View
    {
        if ($batch->status === 'mapping') {
            return view('bank-statements.mapping', compact('batch'));
        }
        $rows = $batch->rows()->with(['expenseCategory:id,name', 'expenseCustomer:id,name', 'expenseContract:id,number', 'collectionCustomer:id,name', 'generated'])
            ->when($request->classification, fn ($q, $v) => $q->where('classification', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('row_number')->paginate(25)->withQueryString();
        $allocationIds = $rows->getCollection()->flatMap(fn ($r) => collect($r->allocation_data ?? [])->pluck('receivable_id'))->unique()->filter();
        $allocationReceivables = Receivable::with('contract:id,number')->whereIn('id', $allocationIds)->get()->keyBy('id');
        $customers = Customer::active()->orderBy('name')->limit(50)->get(['id', 'name', 'code']);
        $selectedCustomerIds = $rows->getCollection()->flatMap(fn ($r) => [$r->expense_customer_id, $r->collection_customer_id])->filter()->unique();
        if ($selectedCustomerIds->isNotEmpty()) {
            $customers = $customers->concat(Customer::whereIn('id', $selectedCustomerIds)->get(['id', 'name', 'code']))->unique('id')->values();
        }
        $expenseCategories = ExpenseCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $contracts = Contract::where('status', 'active')->orderByDesc('contract_date')->limit(100)->get(['id', 'number', 'customer_id']);
        $counts = ['ready' => $batch->rows()->where('status', 'ready')->count(), 'draft' => $batch->rows()->where('status', 'draft')->count(), 'ignored' => $batch->rows()->where('status', 'ignored')->count(), 'posted' => $batch->rows()->where('status', 'posted')->count()];

        return view('bank-statements.show', compact('batch', 'rows', 'customers', 'expenseCategories', 'contracts', 'allocationReceivables', 'counts'));
    }

    public function updateRow(Request $request, BankStatementBatch $batch, BankStatementRow $row, BankStatementService $service): RedirectResponse
    {
        abort_unless($row->bank_statement_batch_id === $batch->id, 404);
        $data = $request->validate([
            'classification' => 'required|in:expense,collection,ignored',
            'expense_category_id' => 'nullable|exists:expense_categories,id', 'expense_description' => 'nullable|string|max:255', 'beneficiary' => 'nullable|string|max:255',
            'expense_customer_id' => 'nullable|exists:customers,id', 'expense_contract_id' => 'nullable|exists:contracts,id',
            'collection_customer_id' => 'nullable|exists:customers,id', 'collection_notes' => 'nullable|string|max:2000',
            'allocations' => 'nullable|array', 'allocations.*.receivable_id' => 'required_with:allocations|exists:receivables,id', 'allocations.*.amount' => 'required_with:allocations|numeric|min:0',
        ]);
        try {
            $service->saveRow($row, $data);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(["row_{$row->id}" => \App\Support\SafeExceptionMessage::from($e)]);
        }

        return back()->with('success', "تم حفظ تصنيف السطر {$row->row_number}.");
    }

    public function outstanding(Request $request, BankStatementBatch $batch): JsonResponse
    {
        $data = $request->validate(['customer_id' => 'required|exists:customers,id']);
        $items = OwnRecordVisibility::apply(Receivable::outstanding()->where('customer_id', $data['customer_id'])->where('currency', $batch->currency))->orderBy('due_date')->get(['id', 'number', 'name', 'due_date', 'remaining_amount']);

        return response()->json($items);
    }

    public function approve(BankStatementBatch $batch, BankStatementService $service): RedirectResponse
    {
        try {
            $service->approve($batch);
        } catch (\Throwable $e) {
            return back()->withErrors(['approve' => \App\Support\SafeExceptionMessage::from($e)]);
        }

        return back()->with('success', 'تم اعتماد كشف البنك وإنشاء سندات المصروف والتحصيل بنجاح.');
    }

    public function original(BankStatementBatch $batch): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($batch->stored_path), 404);

        return Storage::disk('local')->download($batch->stored_path, $batch->original_filename);
    }

    public function destroy(BankStatementBatch $batch, BankStatementService $service): RedirectResponse
    {
        try {
            $service->delete($batch);
        } catch (\Throwable $e) {
            return back()->withErrors(['batch' => \App\Support\SafeExceptionMessage::from($e)]);
        }

        return redirect()->route('bank-statements.index')->with('success','تم حذف كشف البنك غير المعتمد.');
    }
}
