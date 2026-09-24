<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnRecordVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_own_movements_permission_filters_lists_and_blocks_direct_access(): void
    {
        $owner = $this->userWithPermissions(['collections.view', 'collections.update', 'transactions.view-own'], 'owner@example.test');
        $other = $this->userWithPermissions(['collections.view'], 'other@example.test');
        $customer = Customer::create(['code' => 'C-OWN', 'name' => 'عميل اختبار الحركات', 'segment' => 'standard', 'status' => 'active']);

        $ownCollection = $this->collection($customer, $owner, 'COL-OWN');
        $otherCollection = $this->collection($customer, $other, 'COL-OTHER');

        $this->actingAs($owner)
            ->get(route('collections.index'))
            ->assertOk()
            ->assertSee($ownCollection->number)
            ->assertDontSee($otherCollection->number);

        $this->actingAs($owner)
            ->get(route('collections.edit', $otherCollection))
            ->assertNotFound();
    }

    public function test_regular_view_permission_keeps_access_to_all_movements(): void
    {
        $viewer = $this->userWithPermissions(['collections.view'], 'viewer@example.test');
        $creator = $this->userWithPermissions(['collections.view'], 'creator@example.test');
        $customer = Customer::create(['code' => 'C-ALL', 'name' => 'عميل اختبار العرض', 'segment' => 'standard', 'status' => 'active']);

        $first = $this->collection($customer, $viewer, 'COL-FIRST');
        $second = $this->collection($customer, $creator, 'COL-SECOND');

        $this->actingAs($viewer)
            ->get(route('collections.index'))
            ->assertOk()
            ->assertSee($first->number)
            ->assertSee($second->number);
    }

    private function userWithPermissions(array $codes, string $email): User
    {
        $role = Role::create(['name' => 'دور اختبار '.uniqid(), 'code' => 'test-'.uniqid(), 'is_system' => false]);
        $permissions = collect($codes)->map(fn (string $code) => Permission::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'group_name' => str($code)->before('.')->toString()],
        ));
        $role->permissions()->sync($permissions->pluck('id'));

        return User::create(['name' => 'مستخدم اختبار', 'email' => $email, 'password' => 'password123', 'is_active' => true, 'role_id' => $role->id]);
    }

    private function collection(Customer $customer, User $creator, string $number): Collection
    {
        return Collection::create([
            'number' => $number,
            'customer_id' => $customer->id,
            'collection_date' => today(),
            'currency' => 'SAR',
            'amount' => 100,
            'allocated_amount' => 0,
            'unallocated_amount' => 100,
            'payment_method' => 'cash',
            'status' => 'confirmed',
            'created_by' => $creator->id,
        ]);
    }
}
