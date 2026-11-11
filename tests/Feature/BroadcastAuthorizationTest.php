<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BroadcastAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function join(string $channel): TestResponse
    {
        return $this->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-'.$channel]);
    }

    private function assertCanJoin(string $channel): void
    {
        $response = $this->join($channel)->assertOk();

        // A signed Pusher-protocol auth string: "<app key>:<signature>".
        $this->assertStringStartsWith('testing-key:', $response->json('auth'), "Expected to be allowed on {$channel}");
    }

    private function assertCannotJoin(string $channel): void
    {
        $this->assertSame(403, $this->join($channel)->status(), "Expected to be refused on {$channel}");
    }

    public function test_a_guest_cannot_join_any_channel(): void
    {
        $this->join('tracking.all')->assertUnauthorized();
    }

    public function test_a_super_admin_can_join_every_tracking_channel(): void
    {
        $geo = $this->geography();
        $agent = $this->agent($geo['wardA']);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->assertCanJoin('tracking.all');
        $this->assertCanJoin('tracking.state.'.$geo['state']->id);
        $this->assertCanJoin('tracking.lga.'.$geo['lga']->id);
        $this->assertCanJoin('tracking.ward.'.$geo['wardA']->id);
        $this->assertCanJoin('tracking.agent.'.$agent->id);
    }

    public function test_a_ward_coordinator_can_join_only_their_own_ward(): void
    {
        $geo = $this->geography();
        $inside = $this->agent($geo['wardA']);
        $outside = $this->agent($geo['wardB']);
        Sanctum::actingAs(User::factory()->coordinator($geo['wardA'])->create());

        $this->assertCanJoin('tracking.ward.'.$geo['wardA']->id);
        $this->assertCanJoin('tracking.agent.'.$inside->id);

        $this->assertCannotJoin('tracking.all');
        $this->assertCannotJoin('tracking.ward.'.$geo['wardB']->id);
        $this->assertCannotJoin('tracking.lga.'.$geo['lga']->id);     // wider than their scope
        $this->assertCannotJoin('tracking.state.'.$geo['state']->id);
        $this->assertCannotJoin('tracking.agent.'.$outside->id);
    }

    public function test_a_state_coordinator_can_join_channels_for_areas_inside_the_state(): void
    {
        $geo = $this->geography();
        $inside = $this->agent($geo['wardB']);
        $outside = $this->agent($geo['farWard']);
        Sanctum::actingAs(User::factory()->coordinator($geo['state'])->create());

        $this->assertCanJoin('tracking.state.'.$geo['state']->id);
        $this->assertCanJoin('tracking.lga.'.$geo['lga']->id);
        $this->assertCanJoin('tracking.ward.'.$geo['wardB']->id);
        $this->assertCanJoin('tracking.agent.'.$inside->id);

        $this->assertCannotJoin('tracking.all');
        $this->assertCannotJoin('tracking.state.'.$geo['otherState']->id);
        $this->assertCannotJoin('tracking.ward.'.$geo['farWard']->id);
        $this->assertCannotJoin('tracking.agent.'.$outside->id);
    }

    public function test_an_lga_coordinator_cannot_join_another_lga(): void
    {
        $geo = $this->geography();
        Sanctum::actingAs(User::factory()->coordinator($geo['otherLga'])->create());

        $this->assertCanJoin('tracking.lga.'.$geo['otherLga']->id);
        $this->assertCannotJoin('tracking.lga.'.$geo['lga']->id);
        $this->assertCannotJoin('tracking.ward.'.$geo['wardA']->id);
    }

    public function test_a_coordinator_with_no_area_cannot_join_anything(): void
    {
        $geo = $this->geography();
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->assertCannotJoin('tracking.all');
        $this->assertCannotJoin('tracking.state.'.$geo['state']->id);
        $this->assertCannotJoin('tracking.agent.'.$this->agent($geo['wardA'])->id);
    }

    public function test_field_agents_cannot_join_tracking_channels_not_even_their_own(): void
    {
        $geo = $this->geography();
        Sanctum::actingAs($agent = $this->agent($geo['wardA']));

        $this->assertCannotJoin('tracking.all');
        $this->assertCannotJoin('tracking.ward.'.$geo['wardA']->id);
        $this->assertCannotJoin('tracking.agent.'.$agent->id);
    }

    public function test_unknown_channels_and_malformed_ids_are_refused(): void
    {
        $geo = $this->geography();
        Sanctum::actingAs(User::factory()->coordinator($geo['state'])->create());

        $this->assertCannotJoin('tracking.country.1');
        $this->assertCannotJoin('tracking.state.abc');
        $this->assertCannotJoin('something.else');
    }
}
