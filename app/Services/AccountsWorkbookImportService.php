<?php

namespace App\Services;

use App\Jobs\CommitContractImport;
use App\Models\CollectionFollowUp;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\ContractItemUsageSnapshot;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Product;
use App\Models\Receivable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/** Maps the customer-account workbook to existing finance entities. It never creates products. */
class AccountsWorkbookImportService
{
    public const TYPE = 'raito_accounts';

    public function __construct(private NumberGenerator $numbers) {}

    public function recognizes(UploadedFile|string $file): bool
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if (! $path || ! class_exists(IOFactory::class)) {
            return false;
        }
        try {
            $names = collect(IOFactory::load($path)->getWorksheetIterator())->map(fn ($sheet) => $this->normalize($sheet->getTitle()));
            $mainAliases = array_map(fn ($value) => $this->normalize($value), config('raito_accounts_import.main_sheet_aliases'));
            $collectionAliases = array_map(fn ($value) => $this->normalize($value), config('raito_accounts_import.collections_sheet_aliases'));

            return $names->contains(fn ($name) => in_array($name, $mainAliases, true))
                && $names->contains(fn ($name) => in_array($name, $collectionAliases, true));
        } catch (\Throwable) {
            return false;
        }
    }

    public function process(ImportBatch $batch): void
    {
        $batch->update(['status' => 'processing', 'failure_message' => null, 'processed_rows' => 0, 'progress_percentage' => 0]);
        $batch->rows()->delete();
        try {
            $records = $this->read(Storage::disk('local')->path($batch->stored_path), $batch->summary['product_mapping'] ?? []);
            foreach ($records as $record) {
                $batch->rows()->create($record + ['status' => 'pending']);
            }
            $this->revalidate($batch);
            $batch->update([
                'status' => 'reviewing', 'total_rows' => count($records), 'processed_rows' => count($records), 'progress_percentage' => 100,
                'summary' => array_merge($batch->summary ?? [], [
                    'detected_columns' => collect($records)->groupBy('sheet_name')->map(fn ($rows) => array_values(array_unique(Arr::flatten($rows->pluck('payload.source_headers')->all()))))->all(),
                    'accounts' => collect($records)->where('row_type', 'account')->count(),
                    'collections' => collect($records)->where('row_type', 'collection_follow_up')->count(),
                    'unmapped_product_headers' => collect($records)->flatMap(fn ($record) => $record['payload']['unmapped_products'] ?? [])->pluck('header')->unique()->values()->all(),
                ]),
            ]);
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed', 'failure_message' => \App\Support\SafeExceptionMessage::from($e)]);
            throw $e;
        }
    }

    public function updateRow(ImportBatch $batch, ImportRow $row, array $payload): ImportBatch
    {
        if ($row->import_batch_id !== $batch->id) {
            abort(404);
        }
        if ($batch->status !== 'reviewing') {
            throw new RuntimeException('يمكن تعديل الصفوف أثناء مرحلة المراجعة فقط.');
        }
        $editable = ['customer_id', 'contract_id', 'skip', 'customer_name', 'customer_action', 'contract_action', 'allow_domain_change', 'allow_contract_change'];
        $row->update(['payload' => array_replace($row->payload, Arr::only($payload, $editable))]);
        $this->revalidate($batch);

        return $batch->fresh();
    }

    public function updateMapping(ImportBatch $batch, array $mapping): ImportBatch
    {
        if ($batch->status !== 'reviewing') {
            throw new RuntimeException('يمكن تعديل المطابقات أثناء مرحلة المراجعة فقط.');
        }
        $allowed = Product::whereIn('id', collect($mapping)->filter()->values())->pluck('code', 'id');
        $headerMap = [];
        foreach ($mapping as $header => $productId) {
            if ($productId && $allowed->has($productId)) {
                $headerMap[$header] = $allowed[$productId];
            }
        }
        $batch->update(['summary' => array_merge($batch->summary ?? [], ['product_mapping' => $headerMap])]);
        $this->process($batch->fresh());

        return $batch->fresh();
    }

    public function queueCommit(ImportBatch $batch): ImportBatch
    {
        if ($batch->status !== 'reviewing') {
            throw new RuntimeException('الدفعة ليست جاهزة للاعتماد.');
        }
        if ($batch->invalid_rows > 0) {
            throw new RuntimeException('عالج الصفوف غير المحلولة أو اختر تخطيها قبل الاعتماد.');
        }
        $batch->update(['status' => 'commit_queued']);
        CommitContractImport::dispatch($batch->id)->afterCommit();

        return $batch->fresh();
    }

    public function commit(ImportBatch $batch): ImportBatch
    {
        if (! in_array($batch->status, ['reviewing', 'commit_queued'], true)) {
            throw new RuntimeException('لا يمكن اعتماد هذه الدفعة في حالتها الحالية.');
        }
        if ($batch->invalid_rows > 0) {
            throw new RuntimeException('لا يمكن اعتماد دفعة تحتوي صفوفاً غير محلولة.');
        }
        $batch->update(['status' => 'committing']);
        $report = ['financial_dues_created' => 0, 'contract_items_created' => 0, 'contract_items_updated' => 0, 'usage_snapshots_updated' => 0, 'collections_imported' => 0, 'rows_skipped' => 0];

        try {
            DB::transaction(function () use ($batch, &$report) {
                foreach ($batch->rows()->where('status', 'valid')->orderBy('id')->lockForUpdate()->get() as $row) {
                    $payload = $row->payload;
                    if (! empty($payload['skip'])) {
                        $row->update(['status' => 'skipped']);
                        $report['rows_skipped']++;
                        continue;
                    }
                    if ($row->row_type === 'account') {
                        $this->commitAccount($batch, $row, $payload, $report);
                    } elseif ($row->row_type === 'collection_follow_up') {
                        $this->commitCollection($row, $payload, $report);
                    }
                }
                $batch->update(['status' => 'completed', 'committed_at' => now(), 'summary' => array_merge($batch->summary ?? [], $report)]);
            }, 3);
        } catch (\Throwable $exception) {
            $batch->refresh()->update(['status' => 'failed', 'failure_message' => \App\Support\SafeExceptionMessage::from($exception, 'فشل اعتماد الدفعة وتم التراجع عن جميع تغييراتها.')]);
            throw $exception;
        }

        return $batch->fresh();
    }

    private function commitAccount(ImportBatch $batch, ImportRow $row, array $p, array &$report): void
    {
        $customer = ! empty($p['customer_id']) ? Customer::findOrFail($p['customer_id']) : $this->createCustomer($p);
        $customer->fill(array_filter([
            'segment' => $p['sector'] ?: null, 'city' => $p['city'] ?: null,
            'status' => $this->customerStatus($p['customer_status']) ?: null,
        ], fn ($value) => $value !== null));
        $customer->save();
        $this->syncContacts($customer, $p['contact_name'] ?? null, $p['phone'] ?? null);

        $contract = ! empty($p['contract_id']) ? Contract::findOrFail($p['contract_id']) : $this->createContract($customer, $p);
        $contract->update(array_filter([
            'domain' => $p['domain'] ?: null,
            'contract_date' => $p['contract_date'] ?: null,
            'billing_cycle' => $p['billing_cycle'] ?: null,
        ], fn ($value) => $value !== null));

        foreach ($p['products'] as $productData) {
            $product = Product::where('code', $productData['code'])->firstOrFail();
            $item = ContractItem::firstOrNew(['contract_id' => $contract->id, 'product_id' => $product->id]);
            $created = ! $item->exists;
            $quantity = (float) $productData['quantity'];
            $values = ['status' => 'active', 'active_from' => $item->active_from ?: $contract->service_start_date, 'notes' => trim(($item->notes ? $item->notes."\n" : '').'تمت مزامنة الكمية من ملف حسابات Raito')];
            if ($product->type === 'erp_module' || $product->supports_user_pricing) {
                $values['quantity'] = 1;
                $values['requested_users'] = max(0, (int) $quantity);
            } else {
                $values['quantity'] = max(0, $quantity);
            }
            if ($created) {
                $values += ['unit_price' => 0, 'discount_value' => 0, 'line_subtotal' => 0, 'line_net' => 0, 'maintenance_rate' => 0, 'maintenance_annual' => 0, 'sort_order' => (int) $contract->items()->max('sort_order') + 1];
            }
            $item->fill($values)->save();
            $report[$created ? 'contract_items_created' : 'contract_items_updated']++;
            if ($productData['active'] !== null || $productData['inactive'] !== null) {
                ContractItemUsageSnapshot::updateOrCreate(['contract_item_id' => $item->id], [
                    'active_quantity' => (float) ($productData['active'] ?? 0), 'inactive_quantity' => (float) ($productData['inactive'] ?? 0),
                    'total_quantity' => $quantity, 'captured_at' => today(),
                ]);
                $report['usage_snapshots_updated']++;
            }
        }

        if ($p['due_date']) {
            $due = Carbon::parse($p['due_date'])->toDateString();
            $exists = Receivable::withTrashed()->where(['customer_id' => $customer->id, 'contract_id' => $contract->id, 'type' => 'legacy_import_due'])->whereDate('due_date', $due)->exists();
            if (! $exists) {
                $dueModel = Receivable::create(['number' => $this->numbers->unique('receivables', 'REC'), 'customer_id' => $customer->id, 'contract_id' => $contract->id, 'type' => 'legacy_import_due', 'name' => 'استحقاق مستورد يحتاج مراجعة القيمة', 'due_date' => $due, 'currency' => $contract->currency, 'net_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0, 'remaining_amount' => 0, 'status' => 'needs_review', 'notes' => 'مستورد من ملف حسابات Raito؛ القيمة تتطلب مراجعة.', 'created_by' => $contract->created_by ?: auth()->id()]);
                $dueModel->refreshStatus();
                $report['financial_dues_created']++;
            }
        }
        $row->update(['status' => 'imported', 'imported_model_type' => Contract::class, 'imported_model_id' => $contract->id, 'contract_id' => $contract->id]);
    }

    private function commitCollection(ImportRow $row, array $p, array &$report): void
    {
        $customer = ! empty($p['customer_id']) ? Customer::findOrFail($p['customer_id']) : $this->createCustomer($p);
        if (! $customer) {
            throw new RuntimeException('لم يتم إنشاء أو مطابقة العميل المرتبط بمتابعة التحصيل.');
        }
        $date = Carbon::parse($p['collection_start_date'] ?: now())->toDateString();
        $amount = (float) ($p['collection_amount'] ?? 0);
        $existing = CollectionFollowUp::where('customer_id', $customer->id)
            ->whereDate('start_date', $date)
            ->where('collector_name', $p['collector_name'])
            ->where('amount', $amount)
            ->first();
        $followUp = $existing ?: CollectionFollowUp::create([
            'number' => $this->numbers->unique('collection_follow_ups', 'CFU'),
            'customer_id' => $customer->id,
            'start_date' => $date,
            'end_date' => ! empty($p['collection_end_date']) ? Carbon::parse($p['collection_end_date'])->toDateString() : null,
            'currency' => config('finance.default_currency'),
            'amount' => $amount,
            'collector_name' => $p['collector_name'] ?: null,
            'follow_up_status' => $p['collection_status'] ?: null,
            'claim_status' => $p['collection_claim'] ?: null,
            'notes' => $p['collection_follow_up'] ?: null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        if (! $existing) {
            $report['collections_imported']++;
        }
        $row->update(['status' => 'imported', 'imported_model_type' => CollectionFollowUp::class, 'imported_model_id' => $followUp->id]);
    }

    private function revalidate(ImportBatch $batch): void
    {
        $valid = $invalid = 0;
        foreach ($batch->rows()->orderBy('id')->get() as $row) {
            $p = $row->payload;
            $errors = [];
            $warnings = [];
            if (! empty($p['skip'])) {
                $row->update(['status' => 'skipped', 'errors' => null]);

                continue;
            }
            $customer = $this->resolveCustomer($p, $warnings);
            $willCreateCustomer = ($p['customer_action'] ?? null) === 'create' || $this->hasCustomerCreationPlan($batch, $p['customer_name'] ?? '');
            if (! $customer && ! $willCreateCustomer) {
                $errors[] = 'العميل غير محسوم: اختر العميل الموجود أو اختر إنشاء عميل جديد صراحةً.';
            }
            $p['customer_id'] = $customer?->id;
            if ($row->row_type === 'account' && $customer) {
                $contract = $this->resolveContract($customer, $p, $warnings);
                if (! $contract && ($p['contract_action'] ?? null) !== 'create') {
                    $errors[] = 'العقد غير محسوم: اختر عقد العميل الصحيح أو اختر إنشاء عقد جديد صراحةً.';
                }
                $p['contract_id'] = $contract?->id;
                foreach ($p['products'] as $product) {
                    if (! Product::where('code', $product['code'])->exists()) {
                        $errors[] = 'المنتج غير موجود: '.$product['code'];
                    }
                }
                foreach ($p['unmapped_products'] ?? [] as $product) {
                    $errors[] = 'Unmapped Product: '.$product['header'];
                }
                if ($p['domain'] && $contract?->domain && $this->normalize($p['domain']) !== $this->normalize($contract->domain)) {
                    $warnings[] = 'الدومين الحالي مختلف وسيظهر كتغيير قبل الاعتماد.';
                }
                if ($p['due_date'] && $contract && Receivable::withTrashed()->where(['customer_id' => $customer->id, 'contract_id' => $contract->id, 'type' => 'legacy_import_due'])->whereDate('due_date', Carbon::parse($p['due_date'])->toDateString())->exists()) {
                    $warnings[] = 'Existing Financial Due';
                }
            }
            $p['_preview'] = ['customer' => $customer ? 'Existing Customer: '.$customer->name : (($p['customer_action'] ?? null) === 'create' ? 'New Customer: will be created' : 'New Customer / Possible Duplicate'), 'contract' => $p['contract_id'] ? 'Existing Contract #'.$p['contract_id'] : (($p['contract_action'] ?? null) === 'create' ? 'New Contract: will be created' : 'No Contract Found'), 'warnings' => $warnings];
            $status = $errors ? 'invalid' : 'valid';
            $row->update(['payload' => $p, 'status' => $status, 'errors' => $errors ?: null]);
            $status === 'valid' ? $valid++ : $invalid++;
        }
        $batch->update(['valid_rows' => $valid, 'invalid_rows' => $invalid]);
    }

    private function resolveCustomer(array $p, array &$warnings): ?Customer
    {
        if (! empty($p['customer_id'])) {
            return Customer::find($p['customer_id']);
        }
        $needle = $this->normalize($p['customer_name'] ?? '');
        if ($needle === '') {
            return null;
        }
        $matches = Customer::query()->get()->filter(fn ($customer) => $this->normalize($customer->name) === $needle)->values();
        if ($matches->count() > 1) {
            $warnings[] = 'Possible Duplicate Customer';

            return null;
        }

        return $matches->first();
    }

    private function customerByName(string $name): ?Customer
    {
        $needle = $this->normalize($name);

        return Customer::query()->get()->first(fn ($customer) => $this->normalize($customer->name) === $needle);
    }

    private function hasCustomerCreationPlan(ImportBatch $batch, string $name): bool
    {
        $needle = $this->normalize($name);

        return $batch->rows()->where('row_type', 'account')->get()->contains(function (ImportRow $row) use ($needle) {
            return ($row->payload['customer_action'] ?? null) === 'create' && $this->normalize($row->payload['customer_name'] ?? '') === $needle;
        });
    }

    private function createCustomer(array $p): Customer
    {
        if (($p['customer_action'] ?? null) !== 'create') {
            throw new RuntimeException('العميل غير محدد لهذه العملية.');
        }

        return $this->customerByName($p['customer_name'] ?? '') ?: Customer::create([
            'code' => $this->numbers->uniqueCode('customers', 'CUS'),
            'name' => trim((string) $p['customer_name']),
            'segment' => ($p['sector'] ?? null) ?: 'standard',
            'city' => $p['city'] ?? null,
            'status' => $this->customerStatus($p['customer_status'] ?? null) ?: 'active',
        ]);
    }

    private function createContract(Customer $customer, array $p): Contract
    {
        if (($p['contract_action'] ?? null) !== 'create') {
            throw new RuntimeException('العقد غير محدد لهذه العملية.');
        }

        $contracts = $customer->contracts()->get();
        if ($contracts->count() === 1) {
            return $contracts->first();
        }

        $contractDate = $p['contract_date'] ?: now()->toDateString();

        return Contract::create([
            'number' => $this->numbers->unique('contracts', 'CON'),
            'customer_id' => $customer->id,
            'activity_type' => 'erp',
            'domain' => $p['domain'] ?: null,
            'contract_date' => $contractDate,
            'service_start_date' => $contractDate,
            'billing_cycle' => $p['billing_cycle'] ?: 'one_time',
            'currency' => config('finance.default_currency'),
            'status' => $this->customerStatus($p['customer_status'] ?? null) === 'inactive' ? 'inactive' : 'active',
            'is_imported' => true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    private function resolveContract(Customer $customer, array $p, array &$warnings): ?Contract
    {
        if (! empty($p['contract_id'])) {
            $contract = Contract::find($p['contract_id']);

            return $contract?->customer_id === $customer->id ? $contract : null;
        }
        $contracts = $customer->contracts()->get();
        if ($contracts->count() > 1) {
            $warnings[] = 'Multiple possible contracts';

            return null;
        }

        return $contracts->first();
    }

    private function read(string $path, array $productMapping = []): array
    {
        if (! class_exists(IOFactory::class)) {
            throw new RuntimeException('مكتبة PhpSpreadsheet غير مثبتة.');
        }
        $book = IOFactory::load($path);
        $records = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            $kind = $this->sheetKind($sheet->getTitle());
            if (! $kind) {
                continue;
            }
            $rows = $sheet->toArray(null, true, true, false);
            $headers = array_map(fn ($v) => trim((string) $v), array_shift($rows) ?? []);
            foreach ($rows as $index => $values) {
                if (! collect($values)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty()) {
                    continue;
                }
                $raw = array_combine($headers, array_slice(array_pad($values, count($headers), null), 0, count($headers)));
                $payload = $this->mapRow($raw, $kind, $productMapping);
                if (! $payload['customer_name']) {
                    continue;
                }
                $records[] = ['row_number' => $index + 2, 'sheet_name' => $sheet->getTitle(), 'row_type' => $kind === 'main' ? 'account' : 'collection_follow_up', 'payload' => $payload];
            }
        }
        if ($records === []) {
            throw new RuntimeException('لم يتم العثور على صفوف صالحة في أوراق حسابات Raito أو التحصيلات.');
        }

        return $records;
    }

    private function mapRow(array $raw, string $kind, array $productMapping = []): array
    {
        $p = ['source_headers' => array_keys($raw), 'customer_id' => null, 'contract_id' => null, 'skip' => false];
        foreach (config('raito_accounts_import.fields') as $field => $aliases) {
            $p[$field] = $this->valueFor($raw, $aliases);
        }
        $p['contract_date'] = $this->dateValue($p['contract_date']);
        $p['due_date'] = $this->dateValue($p['due_date']);
        $p['collection_start_date'] = $this->dateValue($p['collection_start_date']);
        $p['collection_end_date'] = $this->dateValue($p['collection_end_date']);
        $p['billing_cycle'] = $this->billingCycle($p['billing_cycle']);
        $p['products'] = [];
        $p['unmapped_products'] = [];
        if ($kind === 'main') {
            foreach (config('raito_accounts_import.products') as $code => $aliases) {
                $quantity = $this->valueFor($raw, $aliases);
                if ($quantity === null || $quantity === '') {
                    continue;
                }
                $usage = config('raito_accounts_import.usage_headers.'.$code, []);
                $product = ['code' => $code, 'quantity' => $this->number($quantity), 'active' => isset($usage['active']) ? $this->nullableNumber($this->valueFor($raw, $usage['active'])) : null, 'inactive' => isset($usage['inactive']) ? $this->nullableNumber($this->valueFor($raw, $usage['inactive'])) : null];
                // A zero in this source means no contracted module, not an instruction to erase an existing line item.
                if ($product['quantity'] > 0 || (float) $product['active'] > 0 || (float) $product['inactive'] > 0) {
                    $p['products'][] = $product;
                }
            }
            foreach ($productMapping as $header => $code) {
                $value = $this->valueFor($raw, [$header]);
                if ($value !== null && $value !== '' && $this->number($value) > 0 && ! collect($p['products'])->contains('code', $code)) {
                    $p['products'][] = ['code' => $code, 'quantity' => $this->number($value), 'active' => null, 'inactive' => null];
                }
            }
            foreach ($raw as $header => $value) {
                if (! $this->looksLikeUnmappedProduct($header, $value, $productMapping)) {
                    continue;
                }
                $p['unmapped_products'][] = ['header' => $header, 'quantity' => $this->number($value)];
            }
            $employeeCount = $this->nullableNumber($p['employees']);
            if ($employeeCount !== null && ! collect($p['products'])->contains('code', 'ERP-HR')) {
                $p['products'][] = ['code' => 'ERP-HR', 'quantity' => $employeeCount, 'active' => null, 'inactive' => null];
            }
        }

        return $p;
    }

    private function syncContacts(Customer $customer, ?string $names, ?string $phones): void
    {
        $nameRows = preg_split('/[\r\n]+/', (string) $names) ?: [];
        $phoneRows = preg_split('/[\r\n]+/', (string) $phones) ?: [];
        foreach ($nameRows as $i => $name) {
            $name = trim($name);
            $phone = trim($phoneRows[$i] ?? '');
            if ($name === '' && $phone === '') {
                continue;
            } CustomerContact::firstOrCreate(['customer_id' => $customer->id, 'name' => $name ?: 'جهة اتصال', 'phone' => $phone ?: null], ['is_primary' => ! $customer->contacts()->exists()]);
        }
    }

    private function sheetKind(string $name): ?string
    {
        $normalized = $this->normalize($name);
        if (in_array($normalized, array_map(fn ($value) => $this->normalize($value), config('raito_accounts_import.collections_sheet_aliases')), true)) {
            return 'collections';
        }
        if (in_array($normalized, array_map(fn ($value) => $this->normalize($value), config('raito_accounts_import.main_sheet_aliases')), true)) {
            return 'main';
        }

        return str_contains($normalized, 'raito accounts') ? 'main' : null;
    }

    private function looksLikeUnmappedProduct(string $header, mixed $value, array $productMapping): bool
    {
        if ($value === null || $value === '' || ! is_numeric(str_replace([',', ' '], '', (string) $value))) {
            return false;
        }
        if (array_key_exists($header, $productMapping)) {
            return false;
        }
        $known = ['التسلسل', 'مسلسل'];
        foreach (config('raito_accounts_import.fields') as $aliases) {
            $known = [...$known, ...$aliases];
        }
        foreach (config('raito_accounts_import.products') as $aliases) {
            $known = [...$known, ...$aliases];
        }
        foreach (config('raito_accounts_import.usage_headers') as $usage) {
            foreach ($usage as $aliases) {
                $known = [...$known, ...$aliases];
            }
        }

        return ! in_array($this->normalize($header), array_map(fn ($item) => $this->normalize($item), $known), true);
    }

    private function valueFor(array $raw, array $aliases): mixed
    {
        $aliases = array_map(fn ($value) => $this->normalize($value), $aliases);
        foreach ($raw as $header => $value) {
            if (in_array($this->normalize($header), $aliases, true)) {
                return is_string($value) ? trim($value) : $value;
            }
        }

        return null;
    }

    private function normalize(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = str_replace(['أ', 'إ', 'آ', 'ى', 'ة'], ['ا', 'ا', 'ا', 'ي', 'ه'], $value);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?: '';
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        } try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function number(mixed $value): float
    {
        return (float) str_replace([',', ' '], '', (string) $value);
    }

    private function nullableNumber(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : $this->number($value);
    }

    private function billingCycle(?string $value): ?string
    {
        if (! $value) {
            return null;
        } foreach (config('raito_accounts_import.billing_cycles') as $cycle => $aliases) {
            if (in_array($this->normalize($value), array_map(fn ($alias) => $this->normalize($alias), $aliases), true)) {
                return $cycle;
            }
        }

        return null;
    }

    private function customerStatus(?string $value): ?string
    {
        return match ($this->normalize($value)) {
            'يعمل', 'active', 'نشط' => 'active', 'لايعمل', 'inactive', 'غيرنشط' => 'inactive', default => null
        };
    }
}
