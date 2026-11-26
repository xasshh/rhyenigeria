<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MemberProfile;
use App\Models\User;
use App\Models\VoiceCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoordinatorCommandCentreTest extends TestCase
{
    use RefreshDatabase;

    private function stateCoordinator(array $geo): User
    {
        return User::factory()->coordinator($geo['state'])->create(['must_change_password' => false, 'phone' => '+2348030000099']);
    }

    public function test_a_state_coordinator_opens_the_command_centre_for_their_state(): void
    {
        $geo = $this->geography();
        $coordinator = $this->stateCoordinator($geo);

        $this->actingAs($coordinator)->get('/coordinator')->assertOk()->assertSee(route('coordinator.command'), false);
        $this->actingAs($coordinator)->get('/coordinator/command')->assertOk()
            ->assertSee('Election Command Centre')->assertSee('STATE COORDINATOR')->assertSee($geo['state']->name.' State');
        $this->assertDatabaseHas('audit_logs', ['action' => 'command_centre.opened', 'user_id' => $coordinator->id]);
    }

    public function test_figures_people_and_incidents_are_only_from_their_state(): void
    {
        $geo = $this->geography();
        $coordinator = $this->stateCoordinator($geo);
        $here = User::factory()->member($geo['wardA'])->create(['name' => 'Home Member']);
        $away = User::factory()->member($geo['farWard'])->create(['name' => 'Away Member']);
        MemberProfile::factory()->for($here)->create();
        MemberProfile::factory()->for($away)->create();

        $this->actingAs($coordinator)->getJson('/coordinator/command/feed')->assertOk()->assertJsonPath('summary.total', 1);
        $this->actingAs($coordinator)->getJson('/coordinator/command/contacts')->assertOk()
            ->assertJsonFragment(['name' => 'Home Member'])->assertJsonMissing(['name' => 'Away Member']);

        $mine = Incident::factory()->create(['state_id' => $geo['state']->id, 'user_id' => $here->id]);
        $theirs = Incident::factory()->create(['state_id' => $geo['otherState']->id, 'user_id' => $away->id]);
        $this->actingAs($coordinator)->getJson('/coordinator/command/incidents')->assertOk()
            ->assertJsonCount(1, 'incidents')->assertJsonPath('incidents.0.id', $mine->id);
        $this->actingAs($coordinator)->getJson("/coordinator/command/incidents/{$mine->id}")->assertOk();
        $this->actingAs($coordinator)->getJson("/coordinator/command/incidents/{$theirs->id}")->assertNotFound();
        $this->actingAs($coordinator)->patchJson("/coordinator/command/incidents/{$theirs->id}", ['status' => 'resolved'])->assertNotFound();

        // Calling someone from another state is refused.
        $this->actingAs($coordinator)->postJson('/coordinator/command/calls/direct', ['user_id' => $away->id])->assertUnprocessable();
    }

    public function test_the_phone_log_shows_their_own_calls_and_their_state_only(): void
    {
        $geo = $this->geography();
        $coordinator = $this->stateCoordinator($geo);
        $here = User::factory()->member($geo['wardA'])->create();
        $away = User::factory()->member($geo['farWard'])->create();

        VoiceCall::factory()->create(['contact_user_id' => $here->id, 'contact_phone' => $here->phone]);
        VoiceCall::factory()->create(['contact_user_id' => $away->id, 'contact_phone' => $away->phone]);
        VoiceCall::factory()->create(['contact_phone' => '+2348099990000']); // unknown caller into the line

        $this->actingAs($coordinator)->getJson('/coordinator/command/calls')->assertOk()
            ->assertJsonCount(1, 'calls')->assertJsonPath('calls.0.phone', $here->phone)->assertJsonPath('waiting', 1);
        $this->actingAs(User::factory()->superAdmin()->create())->getJson('/admin/command/calls')->assertJsonCount(3, 'calls');
    }

    public function test_lga_and_ward_coordinators_and_members_cannot_open_it(): void
    {
        $geo = $this->geography();
        $lgaCoordinator = User::factory()->coordinator($geo['lga'])->create(['must_change_password' => false]);
        $wardCoordinator = User::factory()->coordinator($geo['wardA'])->create(['must_change_password' => false]);

        foreach ([$lgaCoordinator, $wardCoordinator] as $user) {
            $this->actingAs($user)->get('/coordinator/command')->assertForbidden();
            $this->actingAs($user)->getJson('/coordinator/command/feed')->assertForbidden();
            $this->actingAs($user)->get('/coordinator')->assertOk()->assertDontSee(route('coordinator.command'), false);
        }
    }
}
