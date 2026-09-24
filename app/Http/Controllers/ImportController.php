<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Product;
use App\Services\AccountsWorkbookImportService;
use App\Services\ContractImportService;
use App\Support\OwnRecordVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    public function index(): View
    {
        $batches = OwnRecordVisibility::apply(ImportBatch::query())->latest()->paginate(15);

        return view('imports.index', compact('batches'));
    }

    public function template(ContractImportService $service): BinaryFileResponse
    {
        $path = $service->templateFile();

        return response()->download($path, 'raito-contracts-import-template.xlsx')->deleteFileAfterSend(true);
    }

    public function upload(Request $request, ContractImportService $service): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx|max:20480']);
        try {
            $batch = $service->upload($request->file('file'));
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => \App\Support\SafeExceptionMessage::from($e)]);
        }

        return redirect()->route('imports.show', $batch)->with('success', 'تمت إضافة ملف Excel لطابور المعالجة.');
    }

    public function show(ImportBatch $batch): View
    {
        $rows = $batch->rows()->orderBy('sheet_name')->orderBy('row_number')->paginate(50);
        $selectedCustomerIds = $rows->getCollection()->pluck('payload')->pluck('customer_id')->filter()->map(fn($id)=>(int)$id)->unique();
        $selectedContractIds = $rows->getCollection()->pluck('payload')->pluck('contract_id')->filter()->map(fn($id)=>(int)$id)->unique();
        $customers = collect();
        $contracts = collect();
        if ($batch->type === AccountsWorkbookImportService::TYPE) {
            $selectedCustomers = Customer::query()->whereIn('id', $selectedCustomerIds)->get(['id', 'code', 'name']);
            $activeCustomers = Customer::query()->where('status', 'active')->whereNotIn('id', $selectedCustomerIds)->orderBy('name')->limit(150)->get(['id', 'code', 'name']);
            $customers = $selectedCustomers->concat($activeCustomers)->unique('id')->values();

            $selectedContracts = Contract::with('customer:id,name')->whereIn('id', $selectedContractIds)->get(['id', 'customer_id', 'number', 'contract_date']);
            $activeContracts = Contract::with('customer:id,name')->where('status', 'active')->whereNotIn('id', $selectedContractIds)->orderByDesc('contract_date')->limit(150)->get(['id', 'customer_id', 'number', 'contract_date']);
            $contracts = $selectedContracts->concat($activeContracts)->unique('id')->values();
        }

        return view('imports.show', compact('batch', 'rows', 'customers', 'contracts'));
    }

    public function status(ImportBatch $batch): JsonResponse
    {
        return response()->json($batch->only([
            'status', 'total_rows', 'processed_rows', 'progress_percentage', 'valid_rows', 'invalid_rows', 'failure_message',
        ]));
    }

    public function mapping(ImportBatch $batch): View
    {
        abort_unless($batch->type === AccountsWorkbookImportService::TYPE, 404);

        return view('imports.mapping', ['batch' => $batch, 'products' => Product::active()->orderBy('name')->get(['id', 'code', 'name'])]);
    }

    public function updateMapping(Request $request, ImportBatch $batch, AccountsWorkbookImportService $service): RedirectResponse
    {
        abort_unless($batch->type === AccountsWorkbookImportService::TYPE, 404);
        $service->updateMapping($batch, $request->validate(['mapping' => 'required|array'])['mapping']);

        return redirect()->route('imports.show', $batch)->with('success', 'تم حفظ مطابقة الأعمدة وإعادة تجهيز المعاينة.');
    }

    public function updateRow(Request $request, ImportBatch $batch, ImportRow $row, ContractImportService $service): RedirectResponse
    {
        abort_unless($row->import_batch_id === $batch->id, 404);
        $data = $request->validate(['payload' => 'required|array']);
        try {
            $service->updateRow($batch, $row, $data['payload']);
        } catch (\Throwable $e) {
            return back()->withErrors(['import' => \App\Support\SafeExceptionMessage::from($e)]);
        }

        return back()->with('success', 'تم تحديث الصف وإعادة فحص الملف بالكامل.');
    }

    public function errorFile(ImportBatch $batch, ContractImportService $service): BinaryFileResponse
    {
        $path = $service->errorFile($batch);

        return response()->download($path, 'import-errors-'.$batch->uuid.'.xlsx');
    }

    public function commit(ImportBatch $batch, ContractImportService $service): RedirectResponse
    {
        try {
            $service->queueCommit($batch);

            return back()->with('success', 'تمت إضافة اعتماد الاستيراد إلى طابور المعالجة. يمكنك متابعة نسبة التقدم من هذه الصفحة.');
        } catch (\Throwable $e) {
            return back()->withErrors(['import' => \App\Support\SafeExceptionMessage::from($e)]);
        }
    }

    public function rollback(ImportBatch $batch, ContractImportService $service): RedirectResponse
    {
        try {
            $service->rollback($batch);

            return back()->with('success', 'تم التراجع عن دفعة الاستيراد وحذف سجلاتها المستوردة الآمنة.');
        } catch (\Throwable $e) {
            return back()->withErrors(['import' => \App\Support\SafeExceptionMessage::from($e)]);
        }
    }

    public function destroy(ImportBatch $batch, ContractImportService $service): RedirectResponse
    {
        try {
            $service->delete($batch);

            return redirect()->route('imports.index')->with('success', 'تم إلغاء دفعة الاستيراد وحذف ملفاتها.');
        } catch (\Throwable $e) {
            return back()->withErrors(['import' => \App\Support\SafeExceptionMessage::from($e)]);
        }
    }
}
