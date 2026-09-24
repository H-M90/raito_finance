<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisabledUserSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabling_user_revokes_existing_session_and_remember_token_even_after_reactivation(): void
    {
        $role = Role::create(['name'=>'مدير النظام','code'=>'admin','is_system'=>true]);
        $admin = User::create(['name'=>'Admin','email'=>'session-admin@example.test','password'=>'password123','role_id'=>$role->id,'is_active'=>true]);
        $target = User::create(['name'=>'Target','email'=>'session-target@example.test','password'=>'password123','role_id'=>$role->id,'is_active'=>true]);
        $target->setRememberToken('old-remember-token');
        $target->save();

        $this->post(route('login.store'), ['email'=>$target->email,'password'=>'password123'])->assertRedirect(route('dashboard'));
        $this->assertSame(0, session('auth_session_version'));

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name'=>$target->name, 'email'=>$target->email, 'role_id'=>$role->id, 'is_active'=>0,
        ])->assertRedirect();

        $target->refresh();
        $this->assertFalse($target->is_active);
        $this->assertSame(1, $target->auth_session_version);
        $this->assertNotSame('old-remember-token', $target->getRememberToken());

        $this->actingAs($target)->withSession(['auth_session_version'=>0])
            ->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.store'), ['email'=>$target->email,'password'=>'password123'])->assertSessionHasErrors('email');

        $target->update(['is_active'=>true]);
        $this->actingAs($target->fresh())->withSession(['auth_session_version'=>0])
            ->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email'=>$target->email,'password'=>'password123'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame(1, session('auth_session_version'));
    }
}
