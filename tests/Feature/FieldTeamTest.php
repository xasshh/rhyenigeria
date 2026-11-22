<?php

namespace Tests\Feature;

use App\Enums\CanvasserStatus;
use App\Enums\Role;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** State coordinators add LGA coordinators, ward coordinators and polling unit agents, and hand out their sign-in details. */
class FieldTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_state_coordinator_adds_an_lga_coordinator_who_must_choose_a_password(): void
    {
        $geo = $this->geography();
        $boss = User::factory()->coordinator($geo['state'])->create();

        $this->actingAs($boss)->post('/coordinator/team', [
            'type' => 'lga_coordinator', 'name' => 'Lami Gado', 'phone' => '0803 111 2222', 'lga_id' => $geo['lga']->id,
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('credentials');

        $lami = User::where('phone', '+2348031112222')->firstOrFail();
        $this->assertSame(Role::Coordinator, $lami->role);
        $this->assertSame($geo['lga']->id, $lami->lga_id);
        $this->assertNull($lami->ward_id);
        $this->assertSame($boss->id, $lami->added_by_user_id);
        $this->assertTrue($lami->must_change_password);

        // The temporary password works with the phone number, then they must pick their own.
        $password = session('credentials')['password'];
        $this->assertTrue(Hash::check($password, $lami->password));
        auth()->logout();
        $this->post('/login', ['email' => '0803 111 2222', 'password' => $password])->assertRedirect('/coordinator');
        $this->get('/coordinator')->assertRedirect('/account/password');
        $this->put('/account/password', ['password' => 'my-own-password', 'password_confirmation' => 'my-own-password'])->assertRedirect('/coordinator');
        $this->assertFalse($lami->fresh()->must_change_password);
        $this->get('/coordinator')->assertOk();
    }

    public function test_a_state_coordinator_adds_a_ward_coordinator_and_a_polling_unit_agent(): void
    {
        $geo = $this->geography();
        $boss = User::factory()->coordinator($geo['state'])->create();

        $this->actingAs($boss)->post('/coordinator/team', [
            'type' => 'ward_coordinator', 'name' => 'Wale Ade', 'phone' => '08031112223', 'lga_id' => $geo['lga']->id, 'ward_id' => $geo['wardA']->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($geo['wardA']->id, User::where('phone', '+2348031112223')->value('ward_id'));

        $this->actingAs($boss)->post('/coordinator/team', [
            'type' => 'polling_unit_agent', 'name' => 'Pat Agent', 'phone' => '08031112224', 'lga_id' => $geo['lga']->id,
            'ward_id' => $geo['wardA']->id, 'polling_unit_id' => $geo['unitA']->id,
        ])->assertSessionHasNoErrors();

        $pat = User::where('phone', '+2348031112224')->firstOrFail();
        $this->assertSame(Role::Member, $pat->role);
        $this->assertTrue($pat->isApprovedCanvasser());
        $this->assertSame(['polling_unit_agent'], $pat->memberProfile->canvasser_roles);
        $this->assertSame($geo['unitA']->id, $pat->memberProfile->polling_unit_id);
        // Consent is theirs to give: they finish their profile on first sign-in.
        $this->assertNull($pat->memberProfile->consent_accepted_at);
        $this->actingAs($pat)->get('/membership/field')->assertRedirect('/membership/setup');

        $this->actingAs($boss)->get('/coordinator/team')->assertOk()->assertSee('Wale Ade')->assertSee('Pat Agent')->assertSee('Polling unit agent');
    }

    public function test_coordinators_cannot_add_people_outside_their_state_and_lower_coordinators_cannot_add_anyone(): void
    {
        $geo = $this->geography();
        $boss = User::factory()->coordinator($geo['state'])->create();

        $this->actingAs($boss)->post('/coordinator/team', [
            'type' => 'lga_coordinator', 'name' => 'Far Away', 'phone' => '08031112225', 'lga_id' => $geo['farWard']->lga_id,
        ])->assertSessionHasErrors('lga_id');

        $lgaCoordinator = User::factory()->coordinator($geo['lga'])->create();
        $this->actingAs($lgaCoordinator)->get('/coordinator/team')->assertForbidden();
        $this->actingAs($lgaCoordinator)->post('/coordinator/team', [
            'type' => 'ward_coordinator', 'name' => 'Sneaky', 'phone' => '08031112226', 'lga_id' => $geo['lga']->id, 'ward_id' => $geo['wardA']->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['phone' => '+2348031112225']);
        $this->assertDatabaseMissing('users', ['phone' => '+2348031112226']);
    }

    public function test_a_coordinator_can_reset_and_deactivate_only_their_own_states_team(): void
    {
        $geo = $this->geography();
        $boss = User::factory()->coordinator($geo['state'])->create();
        $mine = User::factory()->coordinator($geo['wardA'])->create(['added_by_user_id' => $boss->id]);
        $theirs = User::factory()->coordinator($geo['farWard'])->create();

        $this->actingAs($boss)->post("/coordinator/team/{$mine->id}/password")->assertSessionHas('credentials');
        $this->assertTrue($mine->fresh()->must_change_password);

        $this->actingAs($boss)->patch("/coordinator/team/{$mine->id}", ['is_active' => 0]);
        $this->assertFalse($mine->fresh()->is_active);

        $this->actingAs($boss)->post("/coordinator/team/{$theirs->id}/password")->assertNotFound();
        $this->actingAs($boss)->patch("/coordinator/team/{$theirs->id}", ['is_active' => 0])->assertNotFound();
        $this->assertTrue($theirs->fresh()->is_active);
    }

    public function test_self_signed_up_polling_unit_agents_are_listed_but_not_deactivated_from_the_team_page(): void
    {
        $geo = $this->geography();
        $boss = User::factory()->coordinator($geo['state'])->create();
        $volunteer = User::factory()->member($geo['wardA'])->create(['name' => 'Self Starter']);
        MemberProfile::factory()->for($volunteer)->create(['canvasser_status' => CanvasserStatus::Approved, 'canvasser_roles' => ['polling_unit_agent']]);

        $this->actingAs($boss)->get('/coordinator/team')->assertOk()->assertSee('Self Starter');
        $this->actingAs($boss)->patch("/coordinator/team/{$volunteer->id}", ['is_active' => 0])->assertForbidden();
    }

    public function test_a_super_admin_adds_to_any_states_team(): void
    {
        $geo = $this->geography();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/team')->assertOk()->assertSee('Choose a state above');
        $this->actingAs($admin)->post('/admin/team', [
            'type' => 'lga_coordinator', 'name' => 'Admin Added', 'phone' => '08031112227', 'state_id' => $geo['otherState']->id, 'lga_id' => $geo['farWard']->lga_id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($geo['otherState']->id, User::where('phone', '+2348031112227')->value('state_id'));
    }
}
