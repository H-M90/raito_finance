<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Product;
use App\Models\User;
use App\Services\AccountsWorkbookImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountsWorkbookImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_workbook_maps_existing_entities_without_creating_a_duplicate_customer_or_product(): void
    {
        $user = User::create(['name' => 'Import Admin', 'email' => 'accounts-import@example.test', 'password' => 'password', 'is_active' => true]);
        $this->actingAs($user);
        $customer = Customer::create(['code' => 'C-001', 'name' => 'شركة الاختبار', 'status' => 'active']);
        $contract = Contract::create(['number' => 'CTR-001', 'customer_id' => $customer->id, 'activity_type' => 'erp', 'contract_date' => '2025-01-01', 'service_start_date' => '2025-01-01', 'billing_cycle' => 'annual', 'currency' => 'SAR']);
        $erp = Product::create(['code' => 'ERP-ACC', 'name' => 'الحسابات', 'type' => 'erp_module', 'unit' => 'license', 'billing_cycle' => 'one_time', 'is_active' => true]);

        $batch = ImportBatch::create(['uuid' => (string) Str::uuid(), 'type' => AccountsWorkbookImportService::TYPE, 'original_filename' => 'accounts.xlsx', 'stored_path' => 'imports/accounts.xlsx', 'status' => 'reviewing', 'valid_rows' => 2, 'created_by' => $user->id]);
        ImportRow::create(['import_batch_id' => $batch->id, 'sheet_name' => 'Raito Accounts - Main file', 'row_number' => 2, 'row_type' => 'account', 'status' => 'valid', 'payload' => ['customer_name' => $customer->name, 'customer_id' => $customer->id, 'contract_id' => $contract->id, 'sector' => null, 'city' => null, 'customer_status' => 'يعمل', 'contact_name' => 'أحمد', 'phone' => '0500000000', 'domain' => 'demo.raitosystem.com', 'contract_date' => '2025-01-01', 'billing_cycle' => 'one_time', 'due_date' => '2026-01-01', 'products' => [['code' => 'ERP-ACC', 'quantity' => 12, 'active' => 10, 'inactive' => 2]], 'unmapped_products' => []]]);
        ImportRow::create(['import_batch_id' => $batch->id, 'sheet_name' => 'التحصيلات', 'row_number' => 2, 'row_type' => 'collection_follow_up', 'status' => 'valid', 'payload' => ['customer_name' => $customer->name, 'customer_id' => $customer->id, 'collection_start_date' => '2026-01-02', 'collection_amount' => 500, 'collector_name' => 'سارة', 'collection_status' => 'جاري التحصيل', 'collection_claim' => 'مطالبة أولى', 'collection_follow_up' => 'اتصال أسبوعي', 'collection_end_date' => null]]);
        $service = app(AccountsWorkbookImportService::class);

        $service->commit($batch->fresh());

        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'domain' => 'demo.raitosystem.com', 'billing_cycle' => 'one_time']);
        $this->assertDatabaseHas('customer_contacts', ['customer_id' => $customer->id, 'name' => 'أحمد', 'phone' => '0500000000']);
        $this->assertDatabaseHas('contract_items', ['contract_id' => $contract->id, 'product_id' => $erp->id, 'requested_users' => 12]);
        $this->assertDatabaseHas('contract_item_usage_snapshots', ['active_quantity' => 10, 'inactive_quantity' => 2, 'total_quantity' => 12]);
        $this->assertDatabaseHas('receivables', ['customer_id' => $customer->id, 'contract_id' => $contract->id, 'type' => 'legacy_import_due', 'total_amount' => 0, 'status' => 'needs_review']);
        $this->assertDatabaseHas('collection_follow_ups', ['customer_id' => $customer->id, 'amount' => 500, 'follow_up_status' => 'جاري التحصيل', 'claim_status' => 'مطالبة أولى']);
        $this->assertDatabaseMissing('collections', ['customer_id' => $customer->id, 'amount' => 500]);
    }

    public function test_unmatched_customer_is_held_for_review_instead_of_being_created(): void
    {
        $batch = ImportBatch::create(['uuid' => (string) Str::uuid(), 'type' => AccountsWorkbookImportService::TYPE, 'original_filename' => 'accounts.xlsx', 'stored_path' => 'imports/missing.xlsx', 'status' => 'reviewing', 'invalid_rows' => 1]);
        ImportRow::create(['import_batch_id' => $batch->id, 'sheet_name' => 'Raito Accounts - Main file', 'row_number' => 2, 'row_type' => 'account', 'status' => 'invalid', 'errors' => ['العميل غير محسوم'], 'payload' => ['customer_name' => 'عميل غير موجود']]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('صفوفاً غير محلولة');
        app(AccountsWorkbookImportService::class)->commit($batch);
    }
}
