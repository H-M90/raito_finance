<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\User;
use App\Services\AddendumService;
use App\Services\ContractService;
use App\Services\RecurringReceivableGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurringReceivableLeadTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-07 10:00:00');
        config(['finance.receivable_generation_lead_days' => 15]);
        $this->actingAs(User::create([
            'name' => 'Recurring Admin',
            'email' => 'recurring@example.test',
            'password' => 'password123',
            'is_active' => true,
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_monthly_receivable_is_generated_when_due_within_fifteen_days(): void
    {
        $contract = $this->createSubscription('monthly', today()->addDays(15)->toDateString());

        $this->assertDatabaseHas('receivables', [
            'contract_id' => $contract->id,
            'type' => 'monthly',
            'due_date' => today()->addDays(15)->toDateString(),
        ]);
    }

    public function test_receivable_is_not_generated_sixteen_days_early_but_is_generated_when_window_reaches_fifteen_days(): void
    {
        $contract = $this->createSubscription('monthly', today()->addDays(16)->toDateString());

        $this->assertSame(0, Receivable::where('contract_id', $contract->id)->where('type', 'monthly')->count());

        app(RecurringReceivableGenerator::class)->generateForContract($contract->fresh(), today()->addDays(15));
        $this->assertSame(0, Receivable::where('contract_id', $contract->id)->where('type', 'monthly')->count());

        app(RecurringReceivableGenerator::class)->generateForContract($contract->fresh(), today()->addDays(16));
        $this->assertSame(1, Receivable::where('contract_id', $contract->id)->where('type', 'monthly')->count());
    }

    public function test_maintenance_is_generated_fifteen_days_before_first_annual_due_date(): void
    {
        $customer = $this->customer('C-MAINT');
        $product = Product::create([
            'code' => 'ERP-MAINT', 'name' => 'Maintenance Module', 'type' => 'erp_module', 'unit' => 'license',
            'default_sale_price' => 1000, 'billing_cycle' => 'one_time', 'default_maintenance_rate' => 15,
            'supports_user_pricing' => false, 'included_users_one_time' => 3, 'is_active' => true,
        ]);
        $serviceStart = today()->subYear()->addDays(15);

        $contract = app(ContractService::class)->create([
            'customer_id' => $customer->id,
            'activity_type' => 'erp',
            'contract_date' => today()->toDateString(),
            'service_start_date' => $serviceStart->toDateString(),
            'billing_cycle' => 'one_time',
            'currency' => 'SAR',
            'items' => [[
                'product_id' => $product->id, 'quantity' => 1, 'requested_users' => 0,
                'unit_price' => 1000, 'user_unit_price' => 0, 'discount_value' => 0, 'maintenance_rate' => 15,
            ]],
            'installments' => [[
                'name' => 'الدفعة الأولى', 'due_date' => today()->toDateString(), 'net_amount' => 1000,
            ]],
        ]);

        $this->assertDatabaseHas('receivables', [
            'contract_id' => $contract->id,
            'type' => 'maintenance',
            'due_date' => today()->addDays(15)->toDateString(),
        ]);
    }


    public function test_annual_receivable_is_generated_fifteen_days_before_due_date_and_is_idempotent(): void
    {
        $contract = $this->createSubscription('annual', today()->addDays(15)->toDateString());

        $this->assertSame(1, $contract->receivables()->where('type', 'annual')->count());
        app(RecurringReceivableGenerator::class)->generateForContract($contract->fresh());
        $this->assertSame(1, $contract->receivables()->where('type', 'annual')->count());
    }

    public function test_addendum_maintenance_is_generated_fifteen_days_before_its_own_annual_due_date(): void
    {
        $customer = $this->customer('C-ADD-MAINT');
        $product = Product::create([
            'code' => 'ADD-MAINT', 'name' => 'Addendum Maintenance', 'type' => 'other_service', 'unit' => 'service',
            'default_sale_price' => 500, 'billing_cycle' => 'one_time', 'default_maintenance_rate' => 10,
            'supports_user_pricing' => false, 'included_users_one_time' => 0, 'is_active' => true,
        ]);
        $contract = app(ContractService::class)->create([
            'customer_id' => $customer->id, 'activity_type' => 'erp', 'contract_date' => today()->subYears(2)->toDateString(),
            'service_start_date' => today()->subYears(2)->toDateString(), 'billing_cycle' => 'one_time', 'currency' => 'SAR',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'requested_users' => 0, 'unit_price' => 500, 'user_unit_price' => 0, 'discount_value' => 0, 'maintenance_rate' => 0]],
            'installments' => [['name' => 'دفعة العقد', 'due_date' => today()->subYears(2)->toDateString(), 'net_amount' => 500]],
        ]);

        $addendum = app(AddendumService::class)->create($contract, [
            'addendum_date' => today()->subYear()->addDays(15)->toDateString(),
            'service_start_date' => today()->subYear()->addDays(15)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'requested_users' => 0, 'unit_price' => 500, 'user_unit_price' => 0, 'discount_value' => 0, 'maintenance_rate' => 10]],
            'installments' => [['name' => 'دفعة الملحق', 'due_date' => today()->toDateString(), 'net_amount' => 500]],
        ]);

        $this->assertDatabaseHas('receivables', [
            'contract_id' => $contract->id,
            'contract_addendum_id' => $addendum->id,
            'type' => 'maintenance',
            'due_date' => today()->addDays(15)->toDateString(),
        ]);
    }

    private function createSubscription(string $cycle, string $serviceStart): Contract
    {
        $customer = $this->customer('C-'.strtoupper($cycle).'-'.str_replace('-', '', $serviceStart));
        $product = Product::create([
            'code' => 'SUB-'.strtoupper($cycle).'-'.uniqid(), 'name' => 'Subscription', 'type' => 'erp_module', 'unit' => 'license',
            'default_sale_price' => 1000, 'billing_cycle' => $cycle, 'default_maintenance_rate' => 0,
            'supports_user_pricing' => false, 'included_users_one_time' => 0, 'is_active' => true,
        ]);

        return app(ContractService::class)->create([
            'customer_id' => $customer->id,
            'activity_type' => 'erp',
            'contract_date' => today()->toDateString(),
            'service_start_date' => $serviceStart,
            'billing_cycle' => $cycle,
            'currency' => 'SAR',
            'items' => [[
                'product_id' => $product->id, 'quantity' => 1, 'requested_users' => 0,
                'unit_price' => 1000, 'user_unit_price' => 0, 'discount_value' => 0, 'maintenance_rate' => 0,
            ]],
            'installments' => [],
        ]);
    }

    private function customer(string $code): Customer
    {
        return Customer::create([
            'code' => $code,
            'name' => 'Recurring Customer '.$code,
            'segment' => 'standard',
            'status' => 'active',
        ]);
    }
}
