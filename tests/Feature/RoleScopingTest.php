<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleScopingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function world(): array
    {
        $geo = $this->geography();

        $agentA = $this->agent($geo['wardA']);
        $agentB = $this->agent($geo['wardB']);
        $agentFar = $this->agent($geo['farWard']);

        // Each agent reports one position.
        foreach ([[$agentA, 6.6005, 3.3401], [$agentB, 6.5990, 3.3602], [$agentFar, 9.05, 7.49]] as [$agent, $lat, $lng]) {
            Sanctum::actingAs($agent);
            $this->postJson('/api/locations', ['points' => [$this->point($lat, $lng)]])->assertOk();
        }

        return $geo + compact('agentA', 'agentB', 'agentFar');
    }

    /** @return list<int> */
    private function visibleAgentIds(User $viewer): array
    {
        Sanctum::actingAs($viewer);

        $ids = collect($this->getJson('/api/map/positions')->assertOk()->json('features'))->pluck('properties.agent.id')->sort()->values()->all();

        // The user list must agree with the map.
        $listed = collect($this->getJson('/api/users?role=field_agent')->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame($ids, $listed);

        return $ids;
    }

    public function test_a_super_admin_sees_every_agent(): void
    {
        $w = $this->world();

        $this->assertSame(
            collect([$w['agentA']->id, $w['agentB']->id, $w['agentFar']->id])->sort()->values()->all(),
            $this->visibleAgentIds(User::factory()->superAdmin()->create()),
        );
    }

    public function test_a_ward_coordinator_sees_only_agents_in_that_ward(): void
    {
        $w = $this->world();

        $this->assertSame([$w['agentA']->id], $this->visibleAgentIds(User::factory()->coordinator($w['wardA'])->create()));
    }

    public function test_an_lga_coordinator_sees_agents_in_every_ward_of_the_lga(): void
    {
        $w = $this->world();

        $this->assertSame(
            collect([$w['agentA']->id, $w['agentB']->id])->sort()->values()->all(),
            $this->visibleAgentIds(User::factory()->coordinator($w['lga'])->create()),
        );
    }

    public function test_a_state_coordinator_sees_agents_in_the_state_but_not_other_states(): void
    {
        $w = $this->world();

        $this->assertSame(
            collect([$w['agentA']->id, $w['agentB']->id])->sort()->values()->all(),
            $this->visibleAgentIds(User::factory()->coordinator($w['state'])->create()),
        );

        $this->assertSame([$w['agentFar']->id], $this->visibleAgentIds(User::factory()->coordinator($w['otherState'])->create()));
    }

    public function test_a_coordinator_with_no_area_sees_nobody(): void
    {
        $this->world();

        $this->assertSame([], $this->visibleAgentIds(User::factory()->coordinator()->create()));
    }

    public function test_a_coordinator_does_not_see_other_coordinators_or_admins(): void
    {
        $w = $this->world();
        User::factory()->coordinator($w['wardA'])->create();
        User::factory()->superAdmin()->create();

        Sanctum::actingAs(User::factory()->coordinator($w['wardA'])->create());

        $this->assertSame([$w['agentA']->id], collect($this->getJson('/api/users')->json('data'))->pluck('id')->all());
    }

    public function test_a_field_agent_sees_only_themselves(): void
    {
        $w = $this->world();

        $this->assertSame([$w['agentA']->id], $this->visibleAgentIds($w['agentA']));

        $this->getJson("/api/agents/{$w['agentB']->id}/trail")->assertForbidden();
        $this->getJson("/api/users/{$w['agentB']->id}")->assertForbidden();
    }

    public function test_a_coordinator_cannot_open_the_trail_of_an_agent_outside_their_area(): void
    {
        $w = $this->world();
        Sanctum::actingAs(User::factory()->coordinator($w['wardA'])->create());

        $this->getJson("/api/agents/{$w['agentA']->id}/trail")->assertOk();
        $this->getJson("/api/agents/{$w['agentB']->id}/trail")->assertForbidden();
        $this->getJson("/api/users/{$w['agentB']->id}")->assertForbidden();
    }

    public function test_only_super_admins_can_manage_users_forms_and_read_the_audit_log(): void
    {
        $w = $this->geography();
        $payload = ['name' => 'New Agent', 'email' => 'new@example.com', 'password' => 'a-long-password', 'role' => 'field_agent', 'area_level' => 'ward', 'area_id' => $w['wardA']->id];

        foreach ([User::factory()->coordinator($w['state'])->create(), $this->agent($w['wardA'])] as $user) {
            Sanctum::actingAs($user);
            $this->postJson('/api/users', $payload)->assertForbidden();
            $this->patchJson("/api/users/{$user->id}", ['role' => 'super_admin'])->assertForbidden();
            $this->postJson('/api/forms', ['key' => 'x', 'name' => 'X'])->assertForbidden();
            $this->getJson('/api/audit-logs')->assertForbidden();
        }

        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->postJson('/api/users', $payload)
            ->assertCreated()
            ->assertJsonPath('user.role', 'field_agent')
            ->assertJsonPath('user.ward_id', $w['wardA']->id)
            ->assertJsonPath('user.lga_id', $w['lga']->id)
            ->assertJsonPath('user.state_id', $w['state']->id);
        $this->getJson('/api/audit-logs')->assertOk();
    }

    public function test_a_coordinator_must_be_given_an_area(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/users', ['name' => 'C', 'email' => 'c@example.com', 'password' => 'a-long-password', 'role' => 'coordinator'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('area_level');
    }

    public function test_coordinators_and_admins_cannot_report_locations_or_start_shifts(): void
    {
        $w = $this->geography();

        foreach ([User::factory()->coordinator($w['state'])->consented()->create(), User::factory()->superAdmin()->consented()->create()] as $user) {
            Sanctum::actingAs($user);
            $this->postJson('/api/shifts/start')->assertForbidden();
            $this->postJson('/api/locations', ['points' => [$this->point(6.6, 3.34)]])->assertForbidden();
        }
    }
}
