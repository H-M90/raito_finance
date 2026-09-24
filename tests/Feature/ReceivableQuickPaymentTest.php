<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Receivable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivableQuickPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_payment_creates_a_collection_and_updates_the_receivable_balance(): void
    {
        $role = Role::create(['name' => 'Collection test role', 'code' => 'collection-test']);
        $permission = Permission::create(['name' => 'Create collections', 'code' => 'collections.create', 'group_name' => 'collections']);
        $role->permissions()->attach($permission);
        $user = User::create([
            'name' => 'Collector',
            'email' => 'collector@example.test',
            'password' => 'password',
            'is_active' => true,
            'role_id' => $role->id,
        ]);
        $customer = Customer::create(['code' => 'C-PAY-001', 'name' => 'عميل السداد', 'status' => 'active']);
        $contract = Contract::create([
            'number' => 'CTR-PAY-001',
            'customer_id' => $customer->id,
            'activity_type' => 'erp',
            'contract_date' => '2026-01-01',
            'service_start_date' => '2026-01-01',
            'billing_cycle' => 'annual',
            'currency' => 'SAR',
        ]);
        $receivable = Receivable::create([
            'number' => 'REC-PAY-001',
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'type' => 'legacy_import_due',
            'name' => 'استحقاق للاختبار',
            'due_date' => '2026-09-01',
            'currency' => 'SAR',
            'net_amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'remaining_amount' => 1000,
        ]);

        $response = $this->actingAs($user)->post(route('receivables.payment.store', $receivable), [
            'collection_date' => '2026-09-03',
            'amount' => 375.50,
        ]);

        $response->assertRedirect(route('receivables.index', ['q' => $receivable->number]));
        $this->assertDatabaseHas('collections', [
            'customer_id' => $customer->id,
            'amount' => 375.50,
            'allocated_amount' => 375.50,
        ]);
        $this->assertSame('2026-09-03', Collection::firstOrFail()->collection_date->toDateString());
        $this->assertDatabaseHas('collection_allocations', [
            'receivable_id' => $receivable->id,
            'amount' => 375.50,
        ]);
        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'collected_amount' => 375.50,
            'remaining_amount' => 624.50,
            'status' => 'partially_paid',
        ]);
    }
}
