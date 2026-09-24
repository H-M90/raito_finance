<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Receivable;
use App\Models\User;
use App\Services\ReceivableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportedReceivableEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_receivable_can_be_completed_and_posts_its_customer_ledger_entry(): void
    {
        $user = User::create([
            'name' => 'Finance User',
            'email' => 'finance-user@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $customer = Customer::create(['code' => 'C-REC-001', 'name' => 'عميل الاستحقاق', 'status' => 'active']);
        $contract = Contract::create([
            'number' => 'CTR-REC-001',
            'customer_id' => $customer->id,
            'activity_type' => 'erp',
            'contract_date' => '2026-01-01',
            'service_start_date' => '2026-01-01',
            'billing_cycle' => 'annual',
            'currency' => 'SAR',
        ]);
        $receivable = Receivable::create([
            'number' => 'REC-IMPORTED-001',
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'type' => 'legacy_import_due',
            'name' => 'استحقاق مستورد يحتاج مراجعة القيمة',
            'due_date' => '2026-09-01',
            'currency' => 'SAR',
            'net_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'remaining_amount' => 0,
        ]);

        app(ReceivableService::class)->updateImportedDue($receivable, [
            'name' => 'القيمة المعتمدة للاستحقاق',
            'due_date' => '2026-09-15',
            'total_amount' => 1250.50,
        ]);

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'name' => 'القيمة المعتمدة للاستحقاق',
            'total_amount' => 1250.50,
            'remaining_amount' => 1250.50,
        ]);
        $this->assertDatabaseHas('customer_ledger_entries', [
            'source_type' => Receivable::class,
            'source_id' => $receivable->id,
            'entry_type' => 'receivable',
            'debit' => 1250.50,
            'credit' => 0,
        ]);
    }
}
