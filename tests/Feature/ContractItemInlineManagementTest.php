<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\ContractItemLifecycleService;
use App\Services\ContractItemPricingService;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractItemInlineManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_list_renders_product_names_without_lazy_loading(): void
    {
        $role = Role::create(['name' => 'Admin', 'code' => 'admin', 'is_system' => true]);
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'contract-list@example.test', 'password' => 'password', 'is_active' => true, 'role_id' => $role->id]));
        $customer = Customer::create(['code' => 'C-LIST-001', 'name' => 'عميل العقود', 'status' => 'active']);
        $product = Product::create(['code' => 'ERP-LIST-001', 'name' => 'منتج القائمة', 'type' => 'erp_module', 'unit' => 'license', 'billing_cycle' => 'monthly', 'is_active' => true]);
        app(ContractService::class)->create([
            'customer_id' => $customer->id,
            'activity_type' => 'erp',
            'contract_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(),
            'billing_cycle' => 'monthly',
            'currency' => 'SAR',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'requested_users' => 0, 'unit_price' => 500, 'user_unit_price' => 0, 'discount_value' => 0, 'maintenance_rate' => 0]],
            'installments' => [],
        ]);

        $this->get(route('contracts.index'))->assertOk()->assertSee('منتج القائمة');
    }

    public function test_inline_item_pricing_recalculates_an_unsettled_subscription_receivable(): void
    {
        $this->actingAs(User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'password', 'is_active' => true]));
        $customer = Customer::create(['code' => 'C-ITEM-001', 'name' => 'عميل البنود', 'status' => 'active']);
        $product = Product::create([
            'code' => 'ERP-ITEM-001',
            'name' => 'موديول قابل للتعديل',
            'type' => 'erp_module',
            'unit' => 'license',
            'billing_cycle' => 'monthly',
            'supports_user_pricing' => true,
            'included_users_one_time' => 0,
            'is_active' => true,
        ]);
        $contract = app(ContractService::class)->create([
            'customer_id' => $customer->id,
            'activity_type' => 'erp',
            'contract_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(),
            'billing_cycle' => 'monthly',
            'currency' => 'SAR',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'requested_users' => 1,
                'unit_price' => 1000,
                'user_unit_price' => 100,
                'discount_value' => 0,
                'maintenance_rate' => 0,
            ]],
            'installments' => [],
        ]);
        $item = $contract->items()->firstOrFail();

        $result = app(ContractItemPricingService::class)->update($contract, $item, [
            'requested_users' => 3,
            'unit_price' => 900,
            'user_unit_price' => 120,
        ]);

        $this->assertSame(3, (int) $result['item']->requested_users);
        $this->assertSame(1260.0, (float) $result['item']->line_net);
        $this->assertSame(1260.0, (float) $contract->fresh()->net_total);
        $this->assertSame(1260.0, (float) $contract->receivables()->where('type', 'monthly')->firstOrFail()->net_amount);
    }

    public function test_stopped_item_can_be_reactivated_when_no_financial_movement_exists(): void
    {
        $this->actingAs(User::create(['name' => 'Lifecycle editor', 'email' => 'lifecycle-editor@example.test', 'password' => 'password', 'is_active' => true]));
        $customer = Customer::create(['code' => 'C-ITEM-002', 'name' => 'عميل الإيقاف', 'status' => 'active']);
        $product = Product::create(['code' => 'ERP-ITEM-002', 'name' => 'موديول الإيقاف', 'type' => 'erp_module', 'unit' => 'license', 'billing_cycle' => 'monthly', 'is_active' => true]);
        $contract = app(ContractService::class)->create([
            'customer_id' => $customer->id,
            'activity_type' => 'erp',
            'contract_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(),
            'billing_cycle' => 'monthly',
            'currency' => 'SAR',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'requested_users' => 0, 'unit_price' => 500, 'user_unit_price' => 0, 'discount_value' => 0, 'maintenance_rate' => 0]],
            'installments' => [],
        ]);
        $item = $contract->items()->firstOrFail();
        $lifecycle = app(ContractItemLifecycleService::class);

        $lifecycle->stop($contract, $item, today()->toDateString(), 'اختبار الإيقاف');
        $result = $lifecycle->reopen($contract->fresh(), $item->fresh());

        $this->assertNull($result['item']->stopped_at);
        $this->assertSame('active', $result['item']->status);
    }
}
