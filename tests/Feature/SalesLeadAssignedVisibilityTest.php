<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesLeadAssignedVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_only_permission_shows_only_leads_owned_by_current_user(): void
    {
        $restricted = $this->userWithPermissions([
            'sales-leads.view',
            'sales-leads.view-assigned-only',
        ], 'restricted-sales@example.test');
        $other = $this->userWithPermissions(['sales-leads.view'], 'other-sales@example.test');

        $owned = $this->lead('LEAD-OWNED', 'شركة مملوكة للمستخدم', $restricted, $other);
        $createdButReassigned = $this->lead('LEAD-REASSIGNED', 'شركة أنشأها المستخدم لغيره', $other, $restricted);

        $response = $this->actingAs($restricted)->get(route('sales-leads.index'));

        $response->assertOk()
            ->assertSee($owned->company_name)
            ->assertDontSee($createdButReassigned->company_name);
    }

    public function test_assigned_only_permission_blocks_direct_access_to_lead_owned_by_another_user(): void
    {
        $restricted = $this->userWithPermissions([
            'sales-leads.view',
            'sales-leads.view-assigned-only',
        ], 'restricted-direct@example.test');
        $other = $this->userWithPermissions(['sales-leads.view'], 'other-direct@example.test');

        $foreignLead = $this->lead('LEAD-FOREIGN', 'شركة خارج نطاق المستخدم', $other, $restricted);

        $this->actingAs($restricted)
            ->get(route('sales-leads.show', $foreignLead))
            ->assertForbidden();
    }

    public function test_normal_sales_lead_view_permission_keeps_existing_all_leads_visibility(): void
    {
        $viewer = $this->userWithPermissions(['sales-leads.view'], 'all-leads@example.test');
        $other = $this->userWithPermissions(['sales-leads.view'], 'all-leads-other@example.test');

        $own = $this->lead('LEAD-ALL-1', 'شركة أولى', $viewer, $other);
        $foreign = $this->lead('LEAD-ALL-2', 'شركة ثانية', $other, $other);

        $this->actingAs($viewer)
            ->get(route('sales-leads.index'))
            ->assertOk()
            ->assertSee($own->company_name)
            ->assertSee($foreign->company_name);
    }

    private function userWithPermissions(array $codes, string $email): User
    {
        $role = Role::create([
            'name' => 'دور اختبار '.uniqid(),
            'code' => 'lead-scope-'.uniqid(),
            'is_system' => false,
        ]);

        $permissions = collect($codes)->map(fn (string $code) => Permission::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'group_name' => 'sales-leads'],
        ));
        $role->permissions()->sync($permissions->pluck('id'));

        return User::create([
            'name' => 'مستخدم مبيعات',
            'email' => $email,
            'password' => 'password123',
            'is_active' => true,
            'role_id' => $role->id,
        ]);
    }

    private function lead(string $code, string $companyName, User $owner, User $creator): SalesLead
    {
        return SalesLead::create([
            'code' => $code,
            'company_name' => $companyName,
            'owner_id' => $owner->id,
            'stage' => 'lead',
            'rating' => 'medium',
            'qualification' => 'evaluating',
            'priority' => 'medium',
            'responded' => false,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
    }
}
