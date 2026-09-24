<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivableReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_generation_action_creates_the_calculated_due_without_duplicates(): void
    {
        $role = Role::create(['name' => 'Contracts updater', 'code' => 'contracts-updater']);
        $permission = Permission::create(['name' => 'Update contracts', 'code' => 'contracts.update', 'group_name' => 'contracts']);
        $role->permissions()->attach($permission);
        $user = User::create(['name' => 'Updater', 'email' => 'updater@example.test', 'password' => 'password', 'is_active' => true, 'role_id' => $role->id]);
        $customer = Customer::create(['code' => 'C-GEN-001', 'name' => 'عميل التوليد', 'status' => 'active']);
        $product = Product::create(['code' => 'P-GEN-001', 'name' => 'اشتراك التوليد', 'type' => 'erp_module', 'unit' => 'license', 'billing_cycle' => 'monthly', 'is_active' => true]);

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
                'requested_users' => 0,
                'unit_price' => 500,
                'user_unit_price' => 0,
                'discount_value' => 0,
                'maintenance_rate' => 0,
            ]],
            'installments' => [],
        ]);
        $contract->update(['next_billing_date' => today()->addDay()->toDateString()]);

        $response = $this->actingAs($user)->post(route('reports.customer-products-due.generate'), [
            'until_date' => today()->addDay()->toDateString(),
        ]);

        $response->assertRedirect(route('reports.customer-products-due'));
        $this->assertSame(2, Contract::findOrFail($contract->id)->receivables()->where('type', 'monthly')->count());
    }
}
