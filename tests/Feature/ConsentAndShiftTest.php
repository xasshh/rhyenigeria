<?php

namespace Tests\Feature;

use App\Models\LocationPoint;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsentAndShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_posts_are_refused_until_consent_is_accepted(): void
    {
        $agent = User::factory()->agent()->create();
        Shift::factory()->for($agent)->create();
        Sanctum::actingAs($agent);

        $this->postJson('/api/locations', ['points' => [$this->point(6.6, 3.34)]])
            ->assertForbidden()
            ->assertJsonPath('message', 'Location tracking is not allowed until the tracking consent notice has been accepted.');

        $this->assertSame(0, LocationPoint::count());
    }

    public function test_accepting_consent_records_when_it_happened(): void
    {
        $this->freezeSecond();
        Sanctum::actingAs($agent = User::factory()->agent()->create());

        $this->getJson('/api/consent/tracking')->assertOk()->assertJson(['accepted' => false, 'accepted_at' => null]);

        $this->postJson('/api/consent/tracking', ['accepted' => false])->assertStatus(422);
        $this->postJson('/api/consent/tracking')->assertStatus(422);
        $this->assertNull($agent->fresh()->tracking_consent_accepted_at);

        $this->postJson('/api/consent/tracking', ['accepted' => true])
            ->assertOk()
            ->assertJson(['accepted' => true, 'accepted_version' => '1', 'accepted_at' => now()->toIso8601ZuluString()]);

        $this->assertTrue($agent->fresh()->tracking_consent_accepted_at->equalTo(now()));

        // Accepting again does not overwrite the original time.
        $this->travel(2)->days();
        $this->postJson('/api/consent/tracking', ['accepted' => true])->assertOk();
        $this->assertTrue($agent->fresh()->tracking_consent_accepted_at->equalTo(now()->subDays(2)));
    }

    public function test_a_shift_cannot_be_started_without_consent(): void
    {
        Sanctum::actingAs(User::factory()->agent()->create());

        $this->postJson('/api/shifts/start')->assertForbidden();
        $this->assertSame(0, Shift::count());
    }

    public function test_an_agent_starts_and_ends_their_own_shift(): void
    {
        Sanctum::actingAs($agent = $this->agent(onShift: false));

        $this->getJson('/api/shifts/current')->assertOk()->assertJson(['shift' => null]);

        $this->postJson('/api/shifts/start')->assertCreated()->assertJsonPath('shift.active', true);
        $this->postJson('/api/shifts/start')->assertStatus(409);
        $this->assertSame(1, Shift::where('user_id', $agent->id)->count());

        $this->getJson('/api/shifts/current')->assertOk()->assertJsonPath('shift.active', true);

        $this->postJson('/api/shifts/end')->assertOk()->assertJsonPath('shift.active', false);
        $this->postJson('/api/shifts/end')->assertStatus(409);
        $this->assertNotNull(Shift::first()->ended_at);

        // A new shift can be started afterwards.
        $this->postJson('/api/shifts/start')->assertCreated();
    }

    public function test_the_database_allows_only_one_open_shift_per_agent(): void
    {
        $agent = $this->agent();

        $this->expectException(UniqueConstraintViolationException::class);
        Shift::factory()->for($agent)->create();
    }

    public function test_locations_are_refused_when_no_shift_is_in_progress(): void
    {
        Sanctum::actingAs($this->agent(onShift: false));

        $this->postJson('/api/locations', ['points' => [$this->point(6.6, 3.34)]])
            ->assertStatus(409)
            ->assertJson(['stored' => 0, 'outside_shift' => 1]);

        $this->assertSame(0, LocationPoint::count());
    }

    public function test_tracking_stops_as_soon_as_the_agent_ends_the_shift(): void
    {
        Sanctum::actingAs($this->agent());

        $this->postJson('/api/locations', ['points' => [$this->point(6.6, 3.34)]])->assertOk()->assertJson(['stored' => 1]);

        $this->travel(5)->minutes();
        $this->postJson('/api/shifts/end')->assertOk();

        $this->travel(5)->minutes();
        $this->postJson('/api/locations', ['points' => [$this->point(6.61, 3.35)]])->assertStatus(409)->assertJson(['stored' => 0]);

        $this->assertSame(1, LocationPoint::count());
    }

    public function test_points_recorded_during_a_shift_are_accepted_when_they_sync_after_it_ended(): void
    {
        Sanctum::actingAs($agent = $this->agent(onShift: false));
        Shift::factory()->for($agent)->create(['started_at' => now()->subHours(3), 'ended_at' => now()->subHours(2)]);

        $this->postJson('/api/locations', ['points' => [
            $this->point(6.60, 3.34, now()->subHours(4)),               // before the shift
            $this->point(6.61, 3.34, now()->subHours(2)->subMinutes(30)), // during it
            $this->point(6.62, 3.34, now()->subHour()),                 // after it ended
        ]])->assertOk()->assertJson(['stored' => 1, 'outside_shift' => 2, 'shift_active' => false]);

        $this->assertSame(1, LocationPoint::count());
        $this->assertEqualsWithDelta(6.61, (float) \DB::scalar('SELECT ST_Y(location) FROM location_points'), 0.0000001);
    }

    public function test_points_recorded_before_the_current_shift_started_are_dropped(): void
    {
        Sanctum::actingAs($this->agent());

        $this->postJson('/api/locations', ['points' => [
            $this->point(6.60, 3.34, now()->subHours(2)),
            $this->point(6.61, 3.34, now()->subMinutes(10)),
        ]])->assertOk()->assertJson(['stored' => 1, 'outside_shift' => 1]);
    }

    public function test_withdrawing_consent_ends_the_shift_and_stops_tracking(): void
    {
        Sanctum::actingAs($agent = $this->agent());

        $this->deleteJson('/api/consent/tracking')->assertOk()->assertJson(['accepted' => false]);

        $this->assertNull($agent->fresh()->tracking_consent_accepted_at);
        $this->assertNotNull(Shift::first()->ended_at);
        $this->postJson('/api/locations', ['points' => [$this->point(6.6, 3.34)]])->assertForbidden();
    }

    public function test_one_agents_shift_does_not_enable_tracking_for_another(): void
    {
        $this->agent();
        Sanctum::actingAs($this->agent(onShift: false));

        $this->postJson('/api/locations', ['points' => [$this->point(6.6, 3.34)]])->assertStatus(409);
    }
}
