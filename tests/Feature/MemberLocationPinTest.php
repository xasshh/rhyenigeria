<?php

namespace Tests\Feature;

use App\Enums\CanvasserStatus;
use App\Models\AuditLog;
use App\Models\MemberProfile;
use App\Models\State;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Members pin one location (at sign-up or later); staff see the pins on a map scoped like every member list. */
class MemberLocationPinTest extends TestCase
{
    use RefreshDatabase;

    private function member(State $state, ?array $at = null, string $name = 'A Member'): User
    {
        $user = User::factory()->member($state)->create(['name' => $name]);
        MemberProfile::factory()->for($user)->create($at ? ['signup_lat' => $at[0], 'signup_lng' => $at[1], 'location_pinned_at' => now()] : []);

        return $user;
    }

    public function test_a_member_can_pin_move_and_remove_their_location(): void
    {
        $member = $this->member(State::factory()->create());
        $this->actingAs($member);

        $this->get('/membership')->assertOk()->assertSee('Pin your location on the map');

        $this->put('/membership/location', ['latitude' => 6.5244, 'longitude' => 3.3792])->assertRedirect()->assertSessionHas('status');
        $profile = $member->memberProfile->fresh();
        $this->assertSame([6.5244, 3.3792], [$profile->signup_lat, $profile->signup_lng]);
        $this->assertNotNull($profile->location_pinned_at);
        $this->get('/membership')->assertDontSee('Pin your location on the map');
        $this->get('/membership/card')->assertSee('Your location is pinned');

        $this->delete('/membership/location')->assertRedirect();
        $profile->refresh();
        $this->assertNull($profile->signup_lat);
        $this->assertNull($profile->location_pinned_at);
    }

    public function test_a_pin_needs_valid_coordinates(): void
    {
        $this->actingAs($this->member(State::factory()->create()))
            ->put('/membership/location', ['latitude' => 120, 'longitude' => 3.3])
            ->assertSessionHasErrors('latitude');
        $this->put('/membership/location', ['latitude' => 6.5])->assertSessionHasErrors('longitude');
    }

    public function test_the_coordinator_map_only_has_pinned_members_of_their_own_state(): void
    {
        $state = State::factory()->create();
        $mine = $this->member($state, [6.5, 3.3], 'Pinned Mine');
        $this->member($state, null, 'Unpinned Mine');
        $this->member(State::factory()->create(), [9.0, 7.4], 'Pinned Elsewhere');
        $canvasser = $this->member($state, [6.6, 3.4], 'Pinned Canvasser');
        $canvasser->memberProfile->forceFill(['canvasser_status' => CanvasserStatus::Approved])->save();
        $coordinator = User::factory()->coordinator($state)->create();

        $this->actingAs($coordinator)->get('/coordinator/members/map')->assertOk()
            ->assertViewHas('total', 3)->assertViewHas('pinned', 2);

        $pins = $this->getJson('/coordinator/members/map/pins')->assertOk()->json('features');
        $byName = collect($pins)->keyBy('properties.name');
        $this->assertEqualsCanonicalizing(['Pinned Mine', 'Pinned Canvasser'], $byName->keys()->all());
        $this->assertSame([3.3, 6.5], $byName['Pinned Mine']['geometry']['coordinates']);
        $this->assertTrue($byName['Pinned Canvasser']['properties']['canvasser']);
        $this->assertSame($mine->id, $byName['Pinned Mine']['properties']['id']);

        // Asking for another state changes nothing; the view is audit-logged.
        $this->assertCount(2, $this->getJson('/coordinator/members/map/pins?state_id=999')->json('features'));
        $this->assertSame(2, AuditLog::where('action', AuditLogger::MEMBERS_MAP_VIEWED)->where('user_id', $coordinator->id)->count());
    }

    public function test_a_super_admin_sees_every_pin_or_one_state(): void
    {
        $state = State::factory()->create();
        $this->member($state, [6.5, 3.3]);
        $this->member(State::factory()->create(), [9.0, 7.4]);
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->assertCount(2, $this->getJson('/admin/members/map/pins')->json('features'));
        $this->assertCount(1, $this->getJson('/admin/members/map/pins?state_id='.$state->id)->json('features'));
    }

    public function test_members_cannot_see_the_map(): void
    {
        $this->actingAs($this->member(State::factory()->create(), [6.5, 3.3]))
            ->getJson('/coordinator/members/map/pins')->assertForbidden();
    }
}
