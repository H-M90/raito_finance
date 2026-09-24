<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Receivable;
use App\Services\ContractService;
use App\Services\ReceivableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualReceivableAndLegacyContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_receivable_creates_an_open_balance_and_ledger_entry(): void
    {
        $customer = Customer::create(['code' => 'C-MANUAL-001', 'name' => 'عميل يدوي', 'status' => 'active']);
        $contract = Contract::create([
            'number' => 'CTR-MANUAL-001',
            'customer_id' => $customer->id,
            'activity_type' => 'erp',
            'contract_date' => '2026-01-01',
            'service_start_date' => '2026-01-01',
            'billing_cycle' => 'one_time',
            'currency' => 'SAR',
        ]);

        $receivable = app(ReceivableService::class)->createManual($contract, [
            'name' => 'استحقاق يدوي للاختبار',
            'due_date' => '2026-09-10',
            'total_amount' => 880,
            'notes' => 'اختبار',
        ]);

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'type' => 'manual',
            'total_amount' => 880,
            'remaining_amount' => 880,
        ]);
        $this->assertDatabaseHas('customer_ledger_entries', [
            'source_type' => Receivable::class,
            'source_id' => $receivable->id,
            'entry_type' => 'receivable',
            'debit' => 880,
        ]);
    }

    public function test_legacy_contract_accepts_a_manual_total_without_installments(): void
    {
        $customer = Customer::create(['code' => 'C-LEGACY-001', 'name' => 'عميل عقد قديم', 'status' => 'active']);
        $product = Product::create([
            'code' => 'ERP-LEGACY',
            'name' => 'موديول عقد قديم',
            'type' => 'erp_module',
            'unit' => 'license',
            'billing_cycle' => 'one_time',
            'is_active' => true,
        ]);

        $contract = app(ContractService::class)->create([
            'customer_id' => $customer->id,
            'activity_type' => 'erp',
            'contract_date' => '2024-01-01',
            'service_start_date' => '2024-01-01',
            'billing_cycle' => 'one_time',
            'currency' => 'SAR',
            'is_imported' => true,
            'manual_contract_total' => 2300,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'requested_users' => 0,
                'unit_price' => 0,
                'user_unit_price' => 0,
                'discount_value' => 0,
                'maintenance_rate' => 0,
            ]],
            'installments' => [],
        ]);

        $this->assertTrue($contract->is_imported);
        $this->assertSame(2300.0, (float) $contract->grand_total);
        $this->assertSame(2000.0, (float) $contract->net_total);
        $this->assertSame(300.0, (float) $contract->tax_total);
        $this->assertCount(0, $contract->installments);
        $this->assertCount(0, $contract->receivables);
        $this->assertSame('legacy_manual_total', $contract->pricing_snapshot['pricing_mode']);
    }
}
