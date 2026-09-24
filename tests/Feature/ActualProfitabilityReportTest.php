<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CollectionService;
use App\Services\ContractService;
use App\Services\ExpenseService;
use App\Services\ProfitabilityService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActualProfitabilityReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_uses_net_of_vat_collection_revenue_and_includes_general_costs(): void
    {
        $user = User::create([
            'name' => 'Profit Test Admin',
            'email' => 'profit-admin@example.test',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $role = Role::create(['name' => 'Admin', 'code' => 'admin', 'is_system' => true]);
        $user->update(['role_id' => $role->id]);
        $this->actingAs($user);

        $customer = Customer::create([
            'code' => 'CUS-PROFIT',
            'name' => 'عميل اختبار الربحية',
            'segment' => 'standard',
            'status' => 'active',
        ]);
        $product = Product::create([
            'code' => 'ERP-PROFIT',
            'name' => 'منتج اختبار الربحية',
            'type' => 'erp_module',
            'unit' => 'license',
            'default_sale_price' => 1000,
            'billing_cycle' => 'one_time',
            'supports_user_pricing' => false,
            'included_users_one_time' => 0,
            'extra_user_price_one_time' => 0,
            'user_price_monthly' => 0,
            'user_price_annual' => 0,
            'default_maintenance_rate' => 0,
            'is_active' => true,
        ]);

        $contract = app(ContractService::class)->create([
            'customer_id' => $customer->id,
            'activity_type' => 'erp',
            'contract_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(),
            'billing_cycle' => 'one_time',
            'currency' => 'SAR',
            'commission_due_basis' => 'collection',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'requested_users' => 0,
                'unit_price' => 1000,
                'user_unit_price' => 0,
                'discount_value' => 0,
                'maintenance_rate' => 0,
            ]],
            'installments' => [[
                'name' => 'دفعة الاختبار',
                'due_date' => today()->toDateString(),
                'net_amount' => 1000,
            ]],
        ]);
        $receivable = $contract->receivables()->firstOrFail();

        app(CollectionService::class)->create([
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'collection_date' => today()->toDateString(),
            'currency' => 'SAR',
            'amount' => 1150,
            'payment_method' => 'transfer',
            'allocations' => [['receivable_id' => $receivable->id, 'amount' => 1150]],
        ]);

        $category = ExpenseCategory::create(['name' => 'مصروف عام', 'is_active' => true]);
        app(ExpenseService::class)->create([
            'expense_date' => today()->toDateString(),
            'expense_category_id' => $category->id,
            'description' => 'مصروف غير مرتبط بعقد',
            'amount' => 100,
            'currency' => 'SAR',
            'payment_method' => 'transfer',
        ]);

        $supplier = Supplier::create(['name' => 'مورد اختبار الربحية', 'is_active' => true]);
        app(PurchaseService::class)->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => today()->toDateString(),
            'currency' => 'SAR',
            'items' => [[
                'description' => 'مشتريات عامة',
                'quantity' => 1,
                'unit_cost' => 200,
            ]],
        ]);

        $report = app(ProfitabilityService::class)->report(
            null,
            'SAR',
            today()->toDateString(),
            today()->toDateString(),
        );

        $this->assertSame(1000.0, (float) $report['summary']['revenue']);
        $this->assertSame(200.0, (float) $report['summary']['purchase']);
        $this->assertSame(100.0, (float) $report['summary']['expenses']);
        $this->assertSame(700.0, (float) $report['summary']['profit']);
        $this->assertSame(0.0, (float) $report['summary']['commissions']);

        $contractRow = $report['rows']->firstWhere('type', 'contract');
        $generalRow = $report['rows']->firstWhere('type', 'general');
        $this->assertSame(1000.0, (float) $contractRow['revenue']);
        $this->assertSame(200.0, (float) $generalRow['purchase']);
        $this->assertSame(100.0, (float) $generalRow['expenses']);

        $contract->update(['status' => 'cancelled']);
        $lifetime = app(ProfitabilityService::class)->lifetimeReport(null, 'SAR');
        $this->assertSame($contract->number, $lifetime['rows']->first()['label']);
        $this->assertSame(1000.0, $lifetime['summary']['revenue']);
        $this->assertSame(0.0, app(ProfitabilityService::class)->lifetimeReport(null, 'USD')['summary']['revenue']);
        $this->get(route('reports.profitability', ['mode' => 'lifetime', 'currency' => 'SAR']))
            ->assertOk()
            ->assertSee('ربحية العميل/العقد طوال التعامل')
            ->assertSee($contract->number);
    }
}
