<?php

namespace App\Services;

use App\Jobs\CommitContractImport;
use App\Jobs\ProcessContractImport;
use App\Models\CollectionAllocation;
use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\DiscountVoucher;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Intermediary;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Station;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ContractImportService
{
    public const SHEETS = [
        'Customers' => ['customer_code','customer_name','customer_segment','country','city','phone','email','status'],
        'Contracts' => ['customer_code','contract_number','activity_type','contract_date','service_start_date','billing_cycle','currency','calculation_start_date','maintenance_paid_until','previous_collections_total','opening_receivable_balance','opening_maintenance_balance','pts_count','sensor_count','intermediary_name','commission_type','commission_value'],
        'Contract Items' => ['contract_number','item_code','item_name','item_type','quantity','unit_price','discount_value','maintenance_rate','supports_user_pricing','included_users_one_time','extra_user_price_one_time','user_price_monthly','user_price_annual','requested_users','user_unit_price'],
        'Future Installments' => ['contract_number','installment_name','due_date','net_amount'],
        'Addendums' => ['contract_number','addendum_number','addendum_date','service_start_date','pts_count','sensor_count'],
        'Addendum Items' => ['addendum_number','item_code','item_name','item_type','quantity','unit_price','discount_value','maintenance_rate','supports_user_pricing','included_users_one_time','extra_user_price_one_time','user_price_monthly','user_price_annual','requested_users','user_unit_price'],
        'Addendum Installments' => ['addendum_number','installment_name','due_date','net_amount'],
        'Stations' => ['customer_code','station_name','city','phone','is_active'],
    ];

    public function __construct(
        private ContractService $contracts,
        private AddendumService $addendums,
    ) {}

    public function templateFile(): string
    {
        if (! class_exists(Spreadsheet::class)) {
            throw new RuntimeException('شغّل composer install لتثبيت مكتبة Excel.');
        }

        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);
        foreach (self::SHEETS as $sheetName => $headers) {
            $sheet = $book->createSheet();
            $sheet->setTitle($sheetName);
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
            $sheet->freezePane('A2');
            foreach (range('A', $sheet->getHighestColumn()) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }

        $book->getSheetByName('Customers')->fromArray(['C-001','شركة تجريبية','standard','السعودية','الرياض','0500000000','client@example.com','active'], null, 'A2');
        $book->getSheetByName('Contracts')->fromArray(['C-001','OLD-001','erp','2023-01-01','2023-01-15','one_time','SAR','2026-01-01','2026-01-14',80000,0,5000,0,0,'','percentage',0], null, 'A2');
        $book->getSheetByName('Contract Items')->fromArray(['OLD-001','ERP-ACC','الحسابات','erp_module',1,20000,0,15,1,3,2000,200,2000,0,2000], null, 'A2');
        $book->getSheetByName('Future Installments')->fromArray(['OLD-001','رصيد مستحق عند بداية الاحتساب','2026-01-01',20000], null, 'A2');
        $book->getSheetByName('Addendums')->fromArray(['OLD-001','ADD-OLD-001','2024-01-01','2024-01-15',0,0], null, 'A2');
        $book->getSheetByName('Addendum Items')->fromArray(['ADD-OLD-001','ERP-INV','المخزون','erp_module',1,15000,0,15,1,3,2000,200,2000,0,2000], null, 'A2');
        $book->getSheetByName('Addendum Installments')->fromArray(['ADD-OLD-001','دفعة ملحق مستقبلية','2026-02-01',5000], null, 'A2');
        $book->getSheetByName('Stations')->fromArray(['C-001','محطة تجريبية','الرياض','0500000000',1], null, 'A2');

        $path = storage_path('app/tmp/raito-contracts-import-'.Str::uuid().'.xlsx');
        if (! is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function upload(UploadedFile $file): ImportBatch
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
            throw new RuntimeException('الاستيراد يدعم ملف Excel بصيغة XLSX فقط.');
        }

        $accountsImport = app(AccountsWorkbookImportService::class);
        $type = $accountsImport->recognizes($file) ? AccountsWorkbookImportService::TYPE : 'contracts';
        $path = $file->store('imports', 'local');
        $batch = ImportBatch::create([
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'original_filename' => basename($file->getClientOriginalName()),
            'stored_path' => $path,
            'status' => 'queued',
            'created_by' => auth()->id(),
        ]);
        ProcessContractImport::dispatch($batch->id)->afterCommit();

        return $batch;
    }

    public function process(ImportBatch $batch): void
    {
        if ($batch->type === AccountsWorkbookImportService::TYPE) {
            app(AccountsWorkbookImportService::class)->process($batch);
            return;
        }
        $batch->update([
            'status' => 'processing',
            'failure_message' => null,
            'processed_rows' => 0,
            'progress_percentage' => 0,
            'error_file_path' => null,
        ]);
        $batch->rows()->delete();

        try {
            $records = $this->readWorkbook(Storage::disk('local')->path($batch->stored_path));
            $errorsByKey = $this->validateAllRecords($records);
            $total = max(1, count($records));
            $valid = 0;
            $invalid = 0;

            foreach ($records as $index => $record) {
                $key = $this->recordKey($record);
                $errors = $errorsByKey[$key] ?? [];
                $status = $errors === [] ? 'valid' : 'invalid';
                $batch->rows()->create([
                    'row_number' => $record['row_number'],
                    'sheet_name' => $record['sheet_name'],
                    'row_type' => $record['row_type'],
                    'payload' => $record['payload'],
                    'status' => $status,
                    'errors' => $errors ?: null,
                ]);
                $status === 'valid' ? $valid++ : $invalid++;
                if (($index + 1) % 25 === 0 || $index + 1 === count($records)) {
                    $batch->update([
                        'processed_rows' => $index + 1,
                        'progress_percentage' => (int) round(($index + 1) / $total * 100),
                    ]);
                }
            }

            $batch->update([
                'status' => 'reviewing',
                'total_rows' => count($records),
                'processed_rows' => count($records),
                'progress_percentage' => 100,
                'valid_rows' => $valid,
                'invalid_rows' => $invalid,
                'summary' => [
                    'contracts' => collect($records)->where('row_type', 'contract')->count(),
                    'addendums' => collect($records)->where('row_type', 'addendum')->count(),
                    'stations' => collect($records)->where('row_type', 'station')->count(),
                ],
            ]);
            $this->generateErrorFile($batch->fresh());
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed', 'failure_message' => \App\Support\SafeExceptionMessage::from($e)]);
            throw $e;
        }
    }

    public function updateRow(ImportBatch $batch, ImportRow $row, array $payload): ImportBatch
    {
        if ($batch->type === AccountsWorkbookImportService::TYPE) return app(AccountsWorkbookImportService::class)->updateRow($batch, $row, $payload);
        if ($row->import_batch_id !== $batch->id) abort(404);
        if ($batch->status !== 'reviewing') throw new RuntimeException('يمكن تعديل الصفوف أثناء مرحلة المراجعة فقط.');

        $allowed = self::SHEETS[$row->sheet_name] ?? [];
        $row->update(['payload' => collect($payload)->only($allowed)->map(fn ($value) => is_string($value) ? trim($value) : $value)->all()]);
        $this->revalidateStoredRows($batch);

        return $batch->fresh();
    }

    private function revalidateStoredRows(ImportBatch $batch): void
    {
        $records = $batch->rows()->orderBy('sheet_name')->orderBy('row_number')->get()->map(fn (ImportRow $row) => [
            'sheet_name' => $row->sheet_name,
            'row_type' => $row->row_type,
            'row_number' => $row->row_number,
            'payload' => $row->payload,
            'model' => $row,
        ])->all();
        $errorsByKey = $this->validateAllRecords($records);
        $valid = 0;
        $invalid = 0;
        foreach ($records as $record) {
            $errors = $errorsByKey[$this->recordKey($record)] ?? [];
            $status = $errors === [] ? 'valid' : 'invalid';
            $record['model']->update(['status' => $status, 'errors' => $errors ?: null]);
            $status === 'valid' ? $valid++ : $invalid++;
        }
        $batch->update(['valid_rows' => $valid, 'invalid_rows' => $invalid]);
        $this->generateErrorFile($batch->fresh());
    }

    private function readWorkbook(string $path): array
    {
        if (! class_exists(IOFactory::class)) throw new RuntimeException('مكتبة PhpSpreadsheet غير مثبتة. شغّل composer install.');
        $book = IOFactory::load($path);
        $records = [];

        foreach (self::SHEETS as $sheetName => $expected) {
            $sheet = $book->getSheetByName($sheetName);
            if (! $sheet) throw new RuntimeException("ورقة {$sheetName} غير موجودة.");
            $data = $sheet->toArray(null, true, true, false);
            $headers = array_map(fn ($value) => trim((string) $value), array_shift($data) ?? []);
            if (array_slice($headers, 0, count($expected)) !== $expected) {
                throw new RuntimeException("عناوين ورقة {$sheetName} لا تطابق القالب.");
            }
            foreach ($data as $index => $values) {
                if (count(array_filter($values, fn ($value) => $value !== null && $value !== '')) === 0) continue;
                $payload = array_combine($expected, array_slice(array_pad($values, count($expected), null), 0, count($expected)));
                $records[] = [
                    'sheet_name' => $sheetName,
                    'row_type' => $this->rowType($sheetName),
                    'row_number' => $index + 2,
                    'payload' => array_map(fn ($value) => is_string($value) ? trim($value) : $value, $payload),
                ];
            }
        }

        return $records;
    }

    private function validateAllRecords(array $records): array
    {
        $collection = collect($records);
        $errors = [];
        foreach ($records as $record) {
            $errors[$this->recordKey($record)] = $this->validateRecord($record);
        }

        $customerCodes = $collection->where('row_type', 'customer')->pluck('payload.customer_code')->filter();
        $contractNumbers = $collection->where('row_type', 'contract')->pluck('payload.contract_number')->filter();
        $addendumNumbers = $collection->where('row_type', 'addendum')->pluck('payload.addendum_number')->filter();

        $this->appendDuplicateErrors($collection->where('row_type', 'customer'), 'customer_code', 'كود العميل مكرر داخل الملف', $errors);
        $this->appendDuplicateErrors($collection->where('row_type', 'contract'), 'contract_number', 'رقم العقد مكرر داخل الملف', $errors);
        $this->appendDuplicateErrors($collection->where('row_type', 'addendum'), 'addendum_number', 'رقم الملحق مكرر داخل الملف', $errors);

        foreach ($records as $record) {
            $p = $record['payload'];
            $key = $this->recordKey($record);
            if (in_array($record['row_type'], ['contract','station'], true)) {
                $code = $p['customer_code'] ?? null;
                if ($code && ! $customerCodes->contains($code) && ! Customer::where('code', $code)->exists()) {
                    $errors[$key][] = 'كود العميل غير موجود في ورقة العملاء أو النظام.';
                }
            }
            if (in_array($record['row_type'], ['contract_item','installment','addendum'], true)) {
                $number = $p['contract_number'] ?? null;
                if ($number && ! $contractNumbers->contains($number) && ! Contract::where('number', $number)->exists()) {
                    $errors[$key][] = 'رقم العقد المرجعي غير موجود في الملف أو النظام.';
                }
            }
            if (in_array($record['row_type'], ['addendum_item','addendum_installment'], true)) {
                $number = $p['addendum_number'] ?? null;
                if ($number && ! $addendumNumbers->contains($number) && ! ContractAddendum::where('number', $number)->exists()) {
                    $errors[$key][] = 'رقم الملحق المرجعي غير موجود في الملف أو النظام.';
                }
            }
        }

        foreach ($collection->where('row_type', 'contract_item')->groupBy('payload.contract_number') as $rows) {
            $duplicates = $rows->pluck('payload.item_code')->filter()->duplicates()->unique();
            if ($duplicates->isNotEmpty()) foreach ($rows as $record) if ($duplicates->contains($record['payload']['item_code'] ?? null)) $errors[$this->recordKey($record)][] = 'نفس البند مكرر داخل العقد.';
        }
        foreach ($collection->where('row_type', 'addendum_item')->groupBy('payload.addendum_number') as $rows) {
            $duplicates = $rows->pluck('payload.item_code')->filter()->duplicates()->unique();
            if ($duplicates->isNotEmpty()) foreach ($rows as $record) if ($duplicates->contains($record['payload']['item_code'] ?? null)) $errors[$this->recordKey($record)][] = 'نفس البند مكرر داخل الملحق.';
        }

        foreach ($collection->where('row_type', 'contract') as $record) {
            $number = $record['payload']['contract_number'] ?? null;
            if ($number && $collection->where('row_type', 'contract_item')->where('payload.contract_number', $number)->isEmpty()) {
                $errors[$this->recordKey($record)][] = 'العقد يجب أن يحتوي على بند واحد على الأقل.';
            }
        }
        foreach ($collection->where('row_type', 'addendum') as $record) {
            $number = $record['payload']['addendum_number'] ?? null;
            if ($number && $collection->where('row_type', 'addendum_item')->where('payload.addendum_number', $number)->isEmpty()) {
                $errors[$this->recordKey($record)][] = 'الملحق يجب أن يحتوي على بند واحد على الأقل.';
            }
        }

        return collect($errors)->map(fn ($items) => array_values(array_unique(array_filter($items))))->all();
    }

    private function appendDuplicateErrors(Collection $records, string $field, string $message, array &$errors): void
    {
        $duplicates = $records->pluck('payload.'.$field)->filter()->duplicates()->unique();
        if ($duplicates->isEmpty()) return;
        foreach ($records as $record) {
            if ($duplicates->contains($record['payload'][$field] ?? null)) $errors[$this->recordKey($record)][] = $message;
        }
    }

    private function validateRecord(array $record): array
    {
        $p = $record['payload'];
        $errors = [];
        $required = match ($record['row_type']) {
            'customer' => ['customer_code','customer_name'],
            'contract' => ['customer_code','contract_number','activity_type','contract_date','service_start_date','billing_cycle','currency'],
            'contract_item' => ['contract_number','item_code','item_name','quantity','unit_price'],
            'installment' => ['contract_number','installment_name','due_date','net_amount'],
            'addendum' => ['contract_number','addendum_number','addendum_date','service_start_date'],
            'addendum_item' => ['addendum_number','item_code','item_name','quantity','unit_price'],
            'addendum_installment' => ['addendum_number','installment_name','due_date','net_amount'],
            'station' => ['customer_code','station_name'],
            default => [],
        };
        foreach ($required as $field) if (blank($p[$field] ?? null)) $errors[] = "الحقل {$field} مطلوب";

        if ($record['row_type'] === 'contract') {
            if (! in_array($p['activity_type'] ?? '', ['erp','stations','other'], true)) $errors[] = 'نوع النشاط غير صحيح';
            if (! in_array($p['billing_cycle'] ?? '', ['one_time','monthly','annual'], true)) $errors[] = 'دورية الفوترة غير صحيحة';
            if (! in_array($p['currency'] ?? '', ['SAR','USD','EGP'], true)) $errors[] = 'العملة غير صحيحة';
            if (Contract::withTrashed()->where('number', $p['contract_number'] ?? '')->exists()) $errors[] = 'رقم العقد موجود بالفعل';
        }
        if ($record['row_type'] === 'addendum' && ContractAddendum::where('number', $p['addendum_number'] ?? '')->exists()) $errors[] = 'رقم الملحق موجود بالفعل';
        if (in_array($record['row_type'], ['contract_item','addendum_item'], true)) {
            if (! is_numeric($p['quantity'] ?? null) || (int) $p['quantity'] < 1 || (float) $p['quantity'] !== (float) (int) $p['quantity']) $errors[] = 'الكمية يجب أن تكون عددًا صحيحًا موجبًا';
            if (! is_numeric($p['unit_price'] ?? null) || (float) $p['unit_price'] < 0) $errors[] = 'سعر الوحدة غير صحيح';
        }
        if (in_array($record['row_type'], ['installment','addendum_installment'], true) && (! is_numeric($p['net_amount'] ?? null) || (float) $p['net_amount'] <= 0)) $errors[] = 'قيمة الدفعة غير صحيحة';
        foreach (match ($record['row_type']) {
            'contract' => ['contract_date','service_start_date','calculation_start_date','maintenance_paid_until'],
            'installment','addendum_installment' => ['due_date'],
            'addendum' => ['addendum_date','service_start_date'],
            default => [],
        } as $dateField) {
            $value = $p[$dateField] ?? null;
            if (filled($value) && ! $this->isValidIsoDate((string) $value)) {
                $errors[] = "التاريخ {$dateField} يجب أن يكون تاريخًا صحيحًا بصيغة YYYY-MM-DD";
            }
        }
        if (in_array($record['row_type'], ['contract_item','addendum_item'], true)) {
            $requested = (int) ($p['requested_users'] ?? 0);
            if ($requested < 0) $errors[] = 'عدد المستخدمين الإضافيين غير صحيح';
            if ($requested > 0 && (! is_numeric($p['user_unit_price'] ?? null) || (float) $p['user_unit_price'] < 0)) $errors[] = 'سعر المستخدم التاريخي مطلوب عند وجود مستخدمين إضافيين';
        }

        return $errors;
    }


    private function isValidIsoDate(string $value): bool
    {
        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }

    public function queueCommit(ImportBatch $batch): ImportBatch
    {
        if ($batch->type === AccountsWorkbookImportService::TYPE) return app(AccountsWorkbookImportService::class)->queueCommit($batch);
        $batch = DB::transaction(function () use ($batch) {
            $locked = ImportBatch::lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== 'reviewing') throw new RuntimeException('دفعة الاستيراد غير جاهزة للاعتماد.');
            if ($locked->invalid_rows > 0) throw new RuntimeException('صحح الصفوف غير الصحيحة أولًا.');
            $locked->update([
                'status' => 'commit_queued',
                'failure_message' => null,
            ]);
            return $locked->fresh();
        });

        CommitContractImport::dispatch($batch->id)->afterCommit();

        return $batch;
    }

    public function commit(ImportBatch $batch): ImportBatch
    {
        if ($batch->type === AccountsWorkbookImportService::TYPE) return app(AccountsWorkbookImportService::class)->commit($batch);
        DB::transaction(function () use ($batch) {
            $batch = ImportBatch::lockForUpdate()->findOrFail($batch->id);
            if ($batch->status !== 'commit_queued' || $batch->invalid_rows > 0) throw new RuntimeException('دفعة الاستيراد لم تعد جاهزة للاعتماد.');
            $batch->update(['status' => 'committing', 'failure_message' => null]);
            $rows = $batch->rows()->where('status', 'valid')->get();
            $customers = [];
            $createdIntermediaryIds = [];

            foreach ($rows->where('row_type', 'customer') as $row) {
                $p = $row->payload;
                $customer = Customer::firstOrCreate(['code' => $p['customer_code']], [
                    'name' => $p['customer_name'],
                    'segment' => $p['customer_segment'] ?: 'standard',
                    'country' => $p['country'] ?: null,
                    'city' => $p['city'] ?: null,
                    'phone' => $p['phone'] ?: null,
                    'email' => $p['email'] ?: null,
                    'status' => 'active',
                    'source_type' => 'import',
                ]);
                $customers[$p['customer_code']] = $customer;
                $this->markImported($row, $customer, $customer->wasRecentlyCreated);
            }

            foreach ($rows->where('row_type', 'contract') as $contractRow) {
                $p = $contractRow->payload;
                $customer = $customers[$p['customer_code']] ?? Customer::where('code', $p['customer_code'])->firstOrFail();
                $items = $this->appendLegacyDeviceItems($this->buildItems($rows->where('row_type', 'contract_item')->filter(fn ($row) => $row->payload['contract_number'] === $p['contract_number']), $p['billing_cycle']), (int) ($p['pts_count'] ?: 0), (int) ($p['sensor_count'] ?: 0), $p['billing_cycle']);
                $installments = $rows->where('row_type', 'installment')->filter(fn ($row) => $row->payload['contract_number'] === $p['contract_number'])->map(fn ($row) => [
                    'name' => $row->payload['installment_name'],
                    'due_date' => $row->payload['due_date'],
                    'net_amount' => (float) $row->payload['net_amount'],
                ])->values()->all();
                $intermediary = filled($p['intermediary_name'] ?? null) ? Intermediary::firstOrCreate(['name' => $p['intermediary_name']], ['is_active' => true]) : null;if($intermediary?->wasRecentlyCreated)$createdIntermediaryIds[]=$intermediary->id;
                $contract = $this->contracts->create([
                    'number' => $p['contract_number'],
                    'customer_id' => $customer->id,
                    'activity_type' => $p['activity_type'],
                    'contract_date' => $p['contract_date'],
                    'service_start_date' => $p['service_start_date'],
                    'billing_cycle' => $p['billing_cycle'],
                    'currency' => $p['currency'],
                    'calculation_start_date' => $p['calculation_start_date'] ?: $p['service_start_date'],
                    'maintenance_paid_until' => $p['maintenance_paid_until'] ?: null,
                    'previous_collections_total' => (float) ($p['previous_collections_total'] ?: 0),
                    'opening_receivable_balance' => (float) ($p['opening_receivable_balance'] ?: 0),
                    'opening_maintenance_balance' => (float) ($p['opening_maintenance_balance'] ?: 0),
                    'intermediary_id' => $intermediary?->id,
                    'commission_type' => $p['commission_type'] ?: null,
                    'commission_value' => (float) ($p['commission_value'] ?: 0),
                    'commission_due_basis' => 'collection',
                    'is_imported' => true,
                    'items' => $items,
                    'installments' => $installments,
                ]);
                $contractRow->update(['status' => 'imported', 'contract_id' => $contract->id, 'imported_model_type' => Contract::class, 'imported_model_id' => $contract->id]);
                $rows->whereIn('row_type', ['contract_item','installment'])->filter(fn ($row) => ($row->payload['contract_number'] ?? null) === $p['contract_number'])->each(fn ($row) => $row->update(['status' => 'imported', 'contract_id' => $contract->id]));
            }

            foreach ($rows->where('row_type', 'addendum') as $addendumRow) {
                $p = $addendumRow->payload;
                $contract = Contract::where('number', $p['contract_number'])->firstOrFail();
                $items = $this->appendLegacyDeviceItems($this->buildItems($rows->where('row_type', 'addendum_item')->filter(fn ($row) => $row->payload['addendum_number'] === $p['addendum_number']), $contract->billing_cycle), (int) ($p['pts_count'] ?: 0), (int) ($p['sensor_count'] ?: 0), $contract->billing_cycle);
                $installments = $rows->where('row_type', 'addendum_installment')->filter(fn ($row) => $row->payload['addendum_number'] === $p['addendum_number'])->map(fn ($row) => [
                    'name' => $row->payload['installment_name'],
                    'due_date' => $row->payload['due_date'],
                    'net_amount' => (float) $row->payload['net_amount'],
                ])->values()->all();
                $addendum = $this->addendums->create($contract, [
                    'number' => $p['addendum_number'],
                    'addendum_date' => $p['addendum_date'],
                    'service_start_date' => $p['service_start_date'],
                    'is_imported' => true,
                    'items' => $items,
                    'installments' => $installments,
                ]);
                // AddendumService generates its own number for normal UI; imported historical number must be retained.
                if ($addendum->number !== $p['addendum_number']) $addendum->updateQuietly(['number' => $p['addendum_number']]);
                $addendumRow->update(['status' => 'imported', 'contract_id' => $contract->id, 'imported_model_type' => ContractAddendum::class, 'imported_model_id' => $addendum->id]);
                $rows->whereIn('row_type', ['addendum_item','addendum_installment'])->filter(fn ($row) => ($row->payload['addendum_number'] ?? null) === $p['addendum_number'])->each(fn ($row) => $row->update(['status' => 'imported', 'contract_id' => $contract->id]));
            }

            foreach ($rows->where('row_type', 'station') as $row) {
                $p = $row->payload;
                $customer = $customers[$p['customer_code']] ?? Customer::where('code', $p['customer_code'])->firstOrFail();
                $station = Station::firstOrCreate(['customer_id' => $customer->id, 'name' => $p['station_name']], [
                    'city' => $p['city'] ?: null,
                    'phone' => $p['phone'] ?: null,
                    'is_active' => filter_var($p['is_active'] ?? true, FILTER_VALIDATE_BOOL),
                ]);
                $this->markImported($row, $station, $station->wasRecentlyCreated);
            }

            $summary=$batch->summary?:[];$summary['created_intermediary_ids']=array_values(array_unique($createdIntermediaryIds));$batch->update(['status' => 'completed', 'committed_at' => now(), 'summary' => $summary]);
        });

        return $batch->fresh();
    }

    private function appendLegacyDeviceItems(array $items, int $ptsCount, int $sensorCount, string $billingCycle): array
    {
        foreach ([
            ['type'=>'pts','code'=>'PTS','name'=>'جهاز PTS','unit'=>'device','count'=>$ptsCount],
            ['type'=>'sensor','code'=>'SENSOR','name'=>'حساس خزان','unit'=>'sensor','count'=>$sensorCount],
        ] as $device) {
            if ($device['count'] <= 0) continue;
            $product = Product::firstOrCreate(['code' => $device['code']], [
                'name' => $device['name'], 'type' => $device['type'], 'unit' => $device['unit'],
                'default_sale_price' => 0, 'billing_cycle' => 'one_time', 'default_maintenance_rate' => 0,
                'supports_user_pricing' => false, 'is_active' => true,
            ]);
            $alreadyPresent = collect($items)->contains(fn ($item) => (int) ($item['product_id'] ?? 0) === (int) $product->id);
            if ($alreadyPresent) continue;
            $items[] = [
                'product_id' => $product->id, 'quantity' => $device['count'], 'unit_price' => (float) $product->default_sale_price,
                'requested_users' => 0, 'user_unit_price' => 0, 'discount_value' => 0,
                'maintenance_rate' => (float) $product->default_maintenance_rate,
            ];
        }
        return $items;
    }

    private function buildItems(Collection $rows, string $billingCycle): array
    {
        return $rows->map(function (ImportRow $row) use ($billingCycle) {
            $p = $row->payload;
            $product = Product::firstOrCreate(['code' => $p['item_code']], [
                'name' => $p['item_name'],
                'type' => $p['item_type'] ?: 'other',
                'unit' => 'service',
                'default_sale_price' => (float) $p['unit_price'],
                'billing_cycle' => $billingCycle,
                'supports_user_pricing' => filter_var($p['supports_user_pricing'] ?? false, FILTER_VALIDATE_BOOL),
                'included_users_one_time' => (int) ($p['included_users_one_time'] ?: 3),
                'extra_user_price_one_time' => (float) ($p['extra_user_price_one_time'] ?: 0),
                'user_price_monthly' => (float) ($p['user_price_monthly'] ?: 0),
                'user_price_annual' => (float) ($p['user_price_annual'] ?: 0),
                'default_maintenance_rate' => (float) ($p['maintenance_rate'] ?: 0),
                'is_active' => true,
            ]);
            if ($product->wasRecentlyCreated) $row->update(['imported_model_type' => Product::class, 'imported_model_id' => $product->id]);
            return [
                'product_id' => $product->id,
                'quantity' => $product->type === 'erp_module' ? 1 : max(1, (int) $p['quantity']),
                'unit_price' => (float) $p['unit_price'],
                'requested_users' => (int) ($p['requested_users'] ?: 0),
                'user_unit_price' => (float) ($p['user_unit_price'] ?: 0),
                'discount_value' => (float) ($p['discount_value'] ?: 0),
                'maintenance_rate' => (float) ($p['maintenance_rate'] ?: 0),
            ];
        })->values()->all();
    }

    private function markImported(ImportRow $row, object $model, bool $created): void
    {
        $row->update(array_merge(['status' => 'imported'], $created ? [
            'imported_model_type' => $model::class,
            'imported_model_id' => $model->getKey(),
        ] : []));
    }

    public function rollback(ImportBatch $batch): ImportBatch
    {
        if ($batch->type === AccountsWorkbookImportService::TYPE) throw new RuntimeException('دفعة حسابات Raito تحدّث سجلات قائمة ولا يمكن التراجع عنها تلقائياً؛ راجع سجل التدقيق قبل أي تصحيح.');
        if ($batch->status !== 'completed') throw new RuntimeException('يمكن التراجع عن دفعة مكتملة فقط.');

        return DB::transaction(function () use ($batch) {
            $contractIds = $batch->rows()->where('imported_model_type', Contract::class)->pluck('imported_model_id')->filter();
            foreach (Contract::withTrashed()->whereIn('id', $contractIds)->get() as $contract) {
                $receivableIds = Receivable::withTrashed()->where('contract_id', $contract->id)->pluck('id');
                if (CollectionAllocation::whereIn('receivable_id', $receivableIds)->exists() || DiscountVoucher::whereIn('receivable_id', $receivableIds)->exists()) {
                    throw new RuntimeException("لا يمكن التراجع عن العقد {$contract->number} لوجود سند قبض أو سند خصم لاحق.");
                }
                if ($contract->purchaseAllocations()->exists() || $contract->expenses()->exists() || $contract->installations()->exists()) {
                    throw new RuntimeException("لا يمكن التراجع عن العقد {$contract->number} لوجود مشتريات أو مصروفات أو تركيبات لاحقة.");
                }
                CustomerLedgerEntry::where('contract_id', $contract->id)->delete();
                Receivable::withTrashed()->where('contract_id', $contract->id)->forceDelete();
                $contract->addendums()->get()->each(fn (ContractAddendum $addendum) => $addendum->delete());
                $contract->forceDelete();
            }

            $createdRows = $batch->rows()->whereNotNull('imported_model_type')->whereNotIn('imported_model_type', [Contract::class, ContractAddendum::class])->orderByDesc('id')->get();
            foreach ($createdRows as $row) {
                $class = $row->imported_model_type;
                if (! class_exists($class) || ! $row->imported_model_id) continue;
                $model = $class::find($row->imported_model_id);
                if (! $model) continue;
                if ($model instanceof Station && $model->installationRows()->exists()) continue;
                if ($model instanceof Customer && ($model->contracts()->exists() || $model->stations()->exists() || $model->quotations()->exists())) continue;
                if ($model instanceof Product && ($model->contractItems()->exists() || $model->quotationItems()->exists() || $model->addendumItems()->exists())) continue;
                $model->delete();
            }

            $intermediaryIds = collect($batch->summary['created_intermediary_ids'] ?? [])->filter();
            if ($intermediaryIds->isNotEmpty()) Intermediary::whereIn('id', $intermediaryIds)->whereDoesntHave('contracts')->delete();

            $batch->rows()->update(['status' => 'rolled_back']);
            $batch->update(['status' => 'rolled_back', 'committed_at' => null]);
            return $batch->fresh();
        });
    }

    public function errorFile(ImportBatch $batch): string
    {
        if (! $batch->error_file_path || ! Storage::disk('local')->exists($batch->error_file_path)) throw new RuntimeException('لا يوجد ملف أخطاء لهذه الدفعة.');
        return Storage::disk('local')->path($batch->error_file_path);
    }

    private function generateErrorFile(ImportBatch $batch): void
    {
        if (! class_exists(Spreadsheet::class)) return;
        if ($batch->error_file_path) Storage::disk('local')->delete($batch->error_file_path);
        if ($batch->invalid_rows === 0) {
            $batch->updateQuietly(['error_file_path' => null]);
            return;
        }

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Errors');
        $sheet->fromArray(['Sheet','Row','Type','Errors','Payload'], null, 'A1');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $line = 2;
        foreach ($batch->rows()->where('status', 'invalid')->orderBy('sheet_name')->orderBy('row_number')->get() as $row) {
            $sheet->fromArray([$row->sheet_name, $row->row_number, $row->row_type, collect($row->errors)->join(' | '), json_encode($row->payload, JSON_UNESCAPED_UNICODE)], null, 'A'.$line++);
        }
        foreach (range('A', 'E') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $relative = 'import-errors/'.$batch->uuid.'-errors.xlsx';
        $absolute = Storage::disk('local')->path($relative);
        if (! is_dir(dirname($absolute))) mkdir(dirname($absolute), 0775, true);
        (new Xlsx($book))->save($absolute);
        $batch->updateQuietly(['error_file_path' => $relative]);
    }

    public function delete(ImportBatch $batch): void
    {
        if ($batch->status === 'completed') throw new RuntimeException('استخدم التراجع عن الاستيراد للدفعة المكتملة.');
        if (in_array($batch->status, ['processing','commit_queued','committing'], true)) throw new RuntimeException('لا يمكن حذف الدفعة أثناء المعالجة أو الاعتماد.');
        Storage::disk('local')->delete(array_filter([$batch->stored_path, $batch->error_file_path]));
        $batch->delete();
    }

    private function rowType(string $sheet): string
    {
        return match ($sheet) {
            'Customers' => 'customer',
            'Contracts' => 'contract',
            'Contract Items' => 'contract_item',
            'Future Installments' => 'installment',
            'Addendums' => 'addendum',
            'Addendum Items' => 'addendum_item',
            'Addendum Installments' => 'addendum_installment',
            'Stations' => 'station',
            default => 'unknown',
        };
    }

    private function recordKey(array $record): string
    {
        return $record['sheet_name'].'#'.$record['row_number'];
    }
}
