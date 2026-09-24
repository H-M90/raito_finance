<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthorizationAndAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_permission_cannot_open_customer_list(): void
    {
        $user = User::create([
            'name' => 'No Permissions',
            'email' => 'none@example.test',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('customers.index'))->assertForbidden();
    }

    public function test_user_with_permission_can_open_customer_list_and_receives_security_headers(): void
    {
        $user = $this->userWithPermissions(['customers.view']);

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_attachment_requires_both_attachment_and_parent_document_permissions(): void
    {
        Storage::fake('local');
        [$contract, $attachment] = $this->contractAttachment();

        $genericOnly = $this->userWithPermissions(['attachments.download'], 'generic-only@example.test');
        $this->actingAs($genericOnly)->get(route('attachments.download', $attachment))->assertForbidden();

        $authorized = $this->userWithPermissions(['attachments.download', 'contracts.view'], 'authorized@example.test');
        $this->actingAs($authorized)->get(route('attachments.download', $attachment))->assertOk();
    }

    public function test_attachment_delete_requires_update_permission_on_parent_document(): void
    {
        Storage::fake('local');
        [$contract, $attachment] = $this->contractAttachment();

        $genericOnly = $this->userWithPermissions(['attachments.delete'], 'delete-generic@example.test');
        $this->actingAs($genericOnly)->delete(route('attachments.destroy', $attachment))->assertForbidden();
        Storage::disk('local')->assertExists($attachment->path);

        $authorized = $this->userWithPermissions(['attachments.delete', 'contracts.update'], 'delete-authorized@example.test');
        $this->actingAs($authorized)->delete(route('attachments.destroy', $attachment))->assertRedirect();
        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }


    public function test_login_without_role_returns_clear_error_instead_of_permission_403(): void
    {
        $user = User::create([
            'name' => 'Roleless User',
            'email' => 'roleless@example.test',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_role_can_login_and_open_dashboard_without_explicit_permission_rows(): void
    {
        $role = Role::create([
            'name' => 'مدير النظام',
            'code' => 'admin',
            'is_system' => true,
        ]);
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-login@example.test',
            'password' => 'password123',
            'is_active' => true,
            'role_id' => $role->id,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_intermediary_can_be_created_by_ajax_and_returned_for_immediate_selection(): void
    {
        $user = $this->userWithPermissions(['intermediaries.create'], 'intermediary-create@example.test');

        $response = $this->actingAs($user)->postJson(route('intermediaries.quick-store'), [
            'name' => 'وسيط تجريبي',
            'phone' => '0500000000',
            'email' => 'broker@example.test',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'وسيط تجريبي')
            ->assertJsonStructure(['id','name','phone','email']);

        $this->assertDatabaseHas('intermediaries', [
            'name' => 'وسيط تجريبي',
            'is_active' => 1,
        ]);
    }

    public function test_create_admin_command_seeds_missing_admin_role_and_assigns_it(): void
    {
        $this->assertDatabaseMissing('roles', ['code' => 'admin']);

        $this->artisan('finance:create-admin', ['email' => 'created-admin@example.test'])
            ->expectsQuestion('Admin name', 'Created Admin')
            ->expectsQuestion('Password', 'password123')
            ->assertSuccessful();

        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $this->assertDatabaseHas('users', [
            'email' => 'created-admin@example.test',
            'role_id' => $adminRole->id,
            'is_active' => 1,
        ]);
    }

    private function userWithPermissions(array $codes, string $email = 'allowed@example.test'): User
    {
        $role = Role::create([
            'name' => 'Test Role '.uniqid(),
            'code' => 'test-'.uniqid(),
            'is_system' => false,
        ]);

        $permissions = collect($codes)->map(fn (string $code) => Permission::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'group_name' => str($code)->before('.')->toString()]
        ));
        $role->permissions()->sync($permissions->pluck('id'));

        return User::create([
            'name' => 'Allowed User',
            'email' => $email,
            'password' => 'password123',
            'is_active' => true,
            'role_id' => $role->id,
        ]);
    }

    private function contractAttachment(): array
    {
        $customer = Customer::create([
            'code' => 'C-ATTACH',
            'name' => 'Attachment Customer',
            'segment' => 'standard',
            'status' => 'active',
        ]);

        $contract = Contract::create([
            'number' => 'CT-ATTACH',
            'customer_id' => $customer->id,
            'activity_type' => 'erp',
            'contract_date' => today(),
            'service_start_date' => today(),
            'billing_cycle' => 'one_time',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        $path = 'private-documents/contracts/'.$contract->id.'/test.pdf';
        Storage::disk('local')->put($path, '%PDF test');
        $attachment = $contract->attachments()->create([
            'label' => 'عقد',
            'path' => $path,
            'original_name' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 9,
        ]);

        return [$contract, $attachment];
    }
}
