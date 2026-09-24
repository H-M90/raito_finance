<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\ContractItemLifecycleService;
use App\Services\ContractService;
use App\Services\RecurringReceivableGenerator;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ContractLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-07 10:00:00');
        config(['finance.receivable_generation_lead_days' => 15]);

        $this->user = User::create([
            'name' => 'Lifecycle Admin',
            'email' => 'lifecycle@example.test',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $this->actingAs($this->user);
        $this->customer = Customer::create([
            'code' => 'C-LIFECYCLE',
            'name' => 'Lifecycle Customer',
            'segment' => 'standard',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_historical_generation_backfills_monthly_cycles_without_duplicates(): void
    {
        $product = $this->product('HIST-MONTHLY', 1000, 0, 'monthly');
        $contract = app(ContractService::class)->create([
            'customer_id' => $this->customer->id,
            'activity_type' => 'erp',
            'contract_date' => '2025-10-01',
            'service_start_date' => '2025-10-01',
            'calculation_start_date' => today()->toDateString(),
            'billing_cycle' => 'monthly',
            'currency' => 'SAR',
            'items' => [$this->item($product, 1000, 0)],
            'installments' => [],
        ]);

        $this->assertSame(0, $contract->receivables()->where('type', 'monthly')->count());

        $service = app(ContractService::class);
        $created = $service->generateHistoricalReceivables($contract->fresh(), '2025-11-01', today()->toDateString());
        $this->assertSame(10, $created);
        $this->assertSame(10, $contract->receivables()->where('type', 'monthly')->count());
        $this->assertDatabaseHas('receivables', ['contract_id' => $contract->id, 'type' => 'monthly', 'due_date' => '2025-11-01', 'net_amount' => 1000.00]);
        $this->assertDatabaseHas('receivables', ['contract_id' => $contract->id, 'type' => 'monthly', 'due_date' => '2026-08-01', 'net_amount' => 1000.00]);
        $this->assertSame('2025-11-01', $contract->fresh()->calculation_start_date?->toDateString());

        $this->assertSame(0, $service->generateHistoricalReceivables($contract->fresh(), '2025-11-01', today()->toDateString()));
        $this->assertSame(10, $contract->receivables()->where('type', 'monthly')->count());
    }

    public function test_stopping_one_item_reprices_existing_and_future_monthly_receivables(): void
    {
        $p1 = $this->product('MONTH-A', 1000, 0, 'monthly');
        $p2 = $this->product('MONTH-B', 1000, 0, 'monthly');
        $serviceStart = today()->subMonth()->addDays(10)->toDateString(); // 2026-07-17

        $contract = app(ContractService::class)->create([
            'customer_id' => $this->customer->id,
            'activity_type' => 'erp',
            'contract_date' => $serviceStart,
            'service_start_date' => $serviceStart,
            'calculation_start_date' => $serviceStart,
            'billing_cycle' => 'monthly',
            'currency' => 'SAR',
            'items' => [$this->item($p1, 1000, 0), $this->item($p2, 1000, 0)],
            'installments' => [],
        ]);

        $july = $contract->receivables()->where('type', 'monthly')->whereDate('due_date', '2026-07-17')->firstOrFail();
        $august = $contract->receivables()->where('type', 'monthly')->whereDate('due_date', '2026-08-17')->firstOrFail();
        $this->assertSame(2000.0, (float) $july->net_amount);
        $this->assertSame(2000.0, (float) $august->net_amount);

        $item = $contract->items()->where('product_id', $p1->id)->firstOrFail();
        $result = app(ContractItemLifecycleService::class)->stop($contract->fresh(), $item, '2026-08-17', 'إيقاف الموديول');
        $this->assertSame(1, $result['adjusted_receivables']);
        $this->assertSame(2000.0, (float) $july->fresh()->net_amount);
        $this->assertSame(1000.0, (float) $august->fresh()->net_amount);
        $this->assertSame('2026-08-17', $item->fresh()->stopped_at?->toDateString());

        app(RecurringReceivableGenerator::class)->generateForContract($contract->fresh(), '2026-09-17');
        $september = $contract->receivables()->where('type', 'monthly')->whereDate('due_date', '2026-09-17')->firstOrFail();
        $this->assertSame(1000.0, (float) $september->net_amount);
    }

    public function test_stopping_item_reduces_generated_maintenance_due_on_or_after_stop_date(): void
    {
        $p1 = $this->product('MAINT-A', 1000, 10, 'one_time');
        $p2 = $this->product('MAINT-B', 2000, 10, 'one_time');
        $serviceStart = today()->subYear()->addDays(10)->toDateString(); // 2025-08-17

        $contract = app(ContractService::class)->create([
            'customer_id' => $this->customer->id,
            'activity_type' => 'erp',
            'contract_date' => $serviceStart,
            'service_start_date' => $serviceStart,
            'calculation_start_date' => $serviceStart,
            'billing_cycle' => 'one_time',
            'currency' => 'SAR',
            'items' => [$this->item($p1, 1000, 10), $this->item($p2, 2000, 10)],
            'installments' => [[
                'name' => 'دفعة العقد',
                'due_date' => $serviceStart,
                'net_amount' => 3000,
            ]],
        ]);

        $maintenance = $contract->receivables()->where('type', 'maintenance')->whereDate('due_date', '2026-08-17')->firstOrFail();
        $this->assertSame(300.0, (float) $maintenance->net_amount);

        $item = $contract->items()->where('product_id', $p2->id)->firstOrFail();
        app(ContractItemLifecycleService::class)->stop($contract->fresh(), $item, '2026-08-17', 'العميل أوقف المنتج');

        $this->assertSame(100.0, (float) $maintenance->fresh()->net_amount);
        $this->assertSame(15.0, (float) $maintenance->fresh()->tax_amount);
        $this->assertSame(115.0, (float) $maintenance->fresh()->total_amount);
    }

    public function test_item_stop_is_blocked_if_affected_receivable_has_financial_movement(): void
    {
        $p1 = $this->product('LOCK-A', 1000, 0, 'monthly');
        $p2 = $this->product('LOCK-B', 1000, 0, 'monthly');
        $serviceStart = today()->subMonth()->addDays(10)->toDateString();
        $contract = app(ContractService::class)->create([
            'customer_id' => $this->customer->id,
            'activity_type' => 'erp',
            'contract_date' => $serviceStart,
            'service_start_date' => $serviceStart,
            'billing_cycle' => 'monthly',
            'currency' => 'SAR',
            'items' => [$this->item($p1, 1000, 0), $this->item($p2, 1000, 0)],
            'installments' => [],
        ]);
        $contract->receivables()->whereDate('due_date', '2026-08-17')->firstOrFail()->update(['collected_amount' => 100]);

        $this->expectException(DomainException::class);
        app(ContractItemLifecycleService::class)->stop(
            $contract->fresh(),
            $contract->items()->where('product_id', $p1->id)->firstOrFail(),
            '2026-08-17',
            null,
        );
    }


    public function test_required_product_cannot_be_stopped_while_delegate_app_is_active(): void
    {
        $inventory = $this->product('ERP-INV-DEP', 15000, 15, 'one_time');
        $delegate = Product::create([
            'code' => 'APP-DELEGATES',
            'name' => 'تطبيق المناديب',
            'type' => 'application',
            'unit' => 'user',
            'default_sale_price' => 0,
            'billing_cycle' => 'one_time',
            'supports_user_pricing' => true,
            'required_product_id' => $inventory->id,
            'included_users_one_time' => 0,
            'extra_user_price_one_time' => 2400,
            'user_price_monthly' => 200,
            'user_price_annual' => 2000,
            'default_maintenance_rate' => 0,
            'is_active' => true,
        ]);

        $contract = app(ContractService::class)->create([
            'customer_id' => $this->customer->id,
            'activity_type' => 'erp',
            'contract_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(),
            'billing_cycle' => 'one_time',
            'currency' => 'SAR',
            'items' => [
                $this->item($inventory, 15000, 15),
                [
                    'product_id' => $delegate->id,
                    'quantity' => 1,
                    'requested_users' => 2,
                    'unit_price' => 0,
                    'user_unit_price' => 2400,
                    'discount_value' => 0,
                    'maintenance_rate' => 0,
                ],
            ],
            'installments' => [[
                'name' => 'دفعة العقد',
                'due_date' => today()->toDateString(),
                'net_amount' => 19800,
            ]],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('لا يمكن إيقاف');
        app(ContractItemLifecycleService::class)->stop(
            $contract->fresh(),
            $contract->items()->where('product_id', $inventory->id)->firstOrFail(),
            today()->toDateString(),
            'إيقاف المخزون',
        );
    }

    private function product(string $code, float $price, float $maintenance, string $cycle): Product
    {
        return Product::create([
            'code' => $code,
            'name' => $code,
            'type' => 'erp_module',
            'unit' => 'license',
            'default_sale_price' => $price,
            'billing_cycle' => $cycle,
            'default_maintenance_rate' => $maintenance,
            'supports_user_pricing' => false,
            'included_users_one_time' => 0,
            'is_active' => true,
        ]);
    }

    private function item(Product $product, float $price, float $maintenance): array
    {
        return [
            'product_id' => $product->id,
            'quantity' => 1,
            'requested_users' => 0,
            'unit_price' => $price,
            'user_unit_price' => 0,
            'discount_value' => 0,
            'maintenance_rate' => $maintenance,
        ];
    }
}
