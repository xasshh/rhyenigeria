<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_can_add_another_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post('/admin/admins', [
            'name' => 'Ada Obi',
            'email' => ' Ada@Example.com ',
            'phone' => '08031234567',
            'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $created = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertSame(Role::SuperAdmin, $created->role);
        $this->assertTrue($created->is_active);
        $this->assertSame('+2348031234567', $created->phone);
        $this->assertTrue(Hash::check('a-long-password', $created->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.created', 'subject_id' => $created->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)->get('/admin/admins')->assertOk()->assertSee('Ada Obi');
    }

    public function test_coordinators_and_members_cannot_add_super_admins(): void
    {
        foreach ([User::factory()->coordinator()->create(), User::factory()->role(Role::Member)->create()] as $user) {
            $this->actingAs($user)->get('/admin/admins')->assertForbidden();
            $this->actingAs($user)->post('/admin/admins', [
                'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password',
            ])->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_a_super_admin_can_deactivate_another_but_not_themselves(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $other = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->patch("/admin/admins/{$admin->id}", ['is_active' => 0])->assertSessionHasErrors('admin');
        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAs($admin)->patch("/admin/admins/{$other->id}", ['is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($other->fresh()->is_active);

        $this->actingAs($admin)->patch("/admin/admins/{$other->id}", ['is_active' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_the_admin_page_cannot_change_non_admin_accounts(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($admin)->patch("/admin/admins/{$coordinator->id}", ['is_active' => 0])->assertNotFound();
        $this->assertTrue($coordinator->fresh()->is_active);
    }
}
