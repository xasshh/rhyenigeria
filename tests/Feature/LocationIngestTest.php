<?php

namespace Tests\Feature;

use App\Events\AgentPositionUpdated;
use App\Jobs\ResolveLocationContext;
use App\Models\AgentPosition;
use App\Models\LocationPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationIngestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_batch_is_stored_and_the_latest_position_is_maintained(): void
    {
        $geo = $this->geography();
        Sanctum::actingAs($agent = $this->agent($geo['wardA']));

        $this->postJson('/api/locations', ['points' => [
            $this->point(6.6001, 3.3400, now()->subMinutes(3)),
            $this->point(6.6002, 3.3410, now()->subMinutes(2)),
            $this->point(6.6005, 3.3401, now()->subMinute()),
        ]])->assertOk()->assertJson(['received' => 3, 'stored' => 3, 'duplicates' => 0, 'outside_shift' => 0, 'shift_active' => true]);

        $this->assertSame(3, LocationPoint::where('user_id', $agent->id)->count());

        $position = AgentPosition::query()->withCoordinates()->find($agent->id);
        $this->assertEqualsWithDelta(6.6005, $position->latitude, 0.0000001);
        $this->assertEqualsWithDelta(3.3401, $position->longitude, 0.0000001);
        $this->assertSame(1, AgentPosition::count());

        // Stored as real PostGIS geometry in SRID 4326.
        $this->assertSame(4326, (int) \DB::scalar('SELECT ST_SRID(location) FROM location_points LIMIT 1'));
    }

    public function test_duplicate_uuids_are_ignored_not_rejected(): void
    {
        Sanctum::actingAs($agent = $this->agent());

        $first = $this->point(6.6001, 3.3400, now()->subMinutes(2));
        $second = $this->point(6.6002, 3.3410, now()->subMinute());

        $this->postJson('/api/locations', ['points' => [$first, $second]])->assertOk()->assertJson(['stored' => 2]);

        // The device re-sends its queue with one new point, and repeats one inside the batch.
        $third = $this->point(6.6003, 3.3420, now());
        $this->postJson('/api/locations', ['points' => [$first, $second, $third, $third]])
            ->assertOk()
            ->assertJson(['received' => 4, 'stored' => 1, 'duplicates' => 3]);

        // An upper-case copy of a UUID is the same UUID.
        $this->postJson('/api/locations', ['points' => [['uuid' => strtoupper($first['uuid'])] + $first]])
            ->assertOk()
            ->assertJson(['stored' => 0, 'duplicates' => 1]);

        $this->assertSame(3, LocationPoint::where('user_id', $agent->id)->count());
    }

    public function test_out_of_order_points_are_stored_but_never_move_the_latest_position_backwards(): void
    {
        Sanctum::actingAs($agent = $this->agent());

        // Newest first inside the batch.
        $this->postJson('/api/locations', ['points' => [
            $this->point(6.6030, 3.3430, now()->subMinutes(1)),
            $this->point(6.6010, 3.3410, now()->subMinutes(20)),
            $this->point(6.6020, 3.3420, now()->subMinutes(10)),
        ]])->assertOk()->assertJson(['stored' => 3]);

        $position = AgentPosition::query()->withCoordinates()->find($agent->id);
        $this->assertEqualsWithDelta(6.6030, $position->latitude, 0.0000001);

        Event::assertDispatchedTimes(AgentPositionUpdated::class, 1);

        // A late batch of older points, queued while the device was offline.
        $this->postJson('/api/locations', ['points' => [
            $this->point(6.6000, 3.3400, now()->subMinutes(40)),
            $this->point(6.6005, 3.3405, now()->subMinutes(30)),
        ]])->assertOk()->assertJson(['stored' => 2]);

        $position = AgentPosition::query()->withCoordinates()->find($agent->id);
        $this->assertEqualsWithDelta(6.6030, $position->latitude, 0.0000001, 'An older point must not replace the latest position.');
        $this->assertSame(5, LocationPoint::count());

        // Nothing new for the live map, so nothing is broadcast.
        Event::assertDispatchedTimes(AgentPositionUpdated::class, 1);

        // The trail is always in recorded order, whatever order points arrived in.
        $times = LocationPoint::orderBy('recorded_at')->pluck('recorded_at')->map->timestamp->all();
        $sorted = $times;
        sort($sorted);
        $this->assertSame($sorted, $times);
    }

    public function test_the_ward_and_nearest_polling_unit_are_resolved_by_a_queued_job(): void
    {
        $geo = $this->geography();
        Sanctum::actingAs($agent = $this->agent($geo['wardA']));

        Bus::fake([ResolveLocationContext::class]);

        // 6.6004, 3.3402 is inside ward A, a few metres from unit A.
        $this->postJson('/api/locations', ['points' => [$this->point(6.6004, 3.3402)]])->assertOk();

        // The request itself does not do the spatial lookup...
        $point = LocationPoint::first();
        $this->assertNull($point->ward_id);
        $this->assertNull($point->context_resolved_at);

        // ...it queues a job that does.
        Bus::assertDispatched(ResolveLocationContext::class, fn ($job) => $job->userId === $agent->id && $job->pointIds === [$point->id]);

        (new ResolveLocationContext($agent->id, [$point->id]))->handle();

        $point->refresh();
        $this->assertSame($geo['wardA']->id, $point->ward_id);
        $this->assertSame($geo['unitA']->id, $point->nearest_polling_unit_id);
        $this->assertEqualsWithDelta(15.6, $point->nearest_polling_unit_distance_m, 2.0);
        $this->assertNotNull($point->context_resolved_at);

        $position = AgentPosition::find($agent->id);
        $this->assertSame($geo['wardA']->id, $position->ward_id);
        $this->assertSame($geo['unitA']->id, $position->nearest_polling_unit_id);
    }

    public function test_a_point_in_the_neighbouring_ward_resolves_to_that_ward(): void
    {
        $geo = $this->geography();
        Sanctum::actingAs($this->agent($geo['wardA']));

        $this->postJson('/api/locations', ['points' => [$this->point(6.5995, 3.3600)]])->assertOk();

        $point = LocationPoint::first();
        $this->assertSame($geo['wardB']->id, $point->ward_id);
        $this->assertSame($geo['unitB']->id, $point->nearest_polling_unit_id);
    }

    public function test_the_new_position_is_broadcast_on_the_agents_area_channels(): void
    {
        $geo = $this->geography();
        Sanctum::actingAs($agent = $this->agent($geo['wardA']));

        $this->postJson('/api/locations', ['points' => [$this->point(6.6004, 3.3402)]])->assertOk();

        Event::assertDispatched(AgentPositionUpdated::class, function (AgentPositionUpdated $event) use ($agent, $geo) {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

            $this->assertEqualsCanonicalizing([
                'private-tracking.all',
                'private-tracking.agent.'.$agent->id,
                'private-tracking.state.'.$geo['state']->id,
                'private-tracking.lga.'.$geo['lga']->id,
                'private-tracking.ward.'.$geo['wardA']->id,
            ], $channels);

            $payload = $event->broadcastWith();
            $this->assertSame($agent->id, $payload['agent']['id']);
            $this->assertEqualsWithDelta(6.6004, $payload['latitude'], 0.0000001);
            $this->assertSame($geo['wardA']->id, $payload['ward']['id']);
            $this->assertSame($geo['unitA']->id, $payload['nearest_polling_unit']['id']);
            $this->assertSame('agent.position.updated', $event->broadcastAs());

            return true;
        });
    }

    public function test_points_with_timestamps_in_the_future_are_dropped(): void
    {
        Sanctum::actingAs($this->agent());

        $this->postJson('/api/locations', ['points' => [
            $this->point(6.60, 3.34, now()->addHours(3)),
            $this->point(6.60, 3.34, now()),
        ]])->assertOk()->assertJson(['stored' => 1, 'invalid_time' => 1]);
    }

    public function test_invalid_batches_are_rejected(): void
    {
        Sanctum::actingAs($this->agent());

        $this->postJson('/api/locations', [])->assertStatus(422)->assertJsonValidationErrors('points');
        $this->postJson('/api/locations', ['points' => []])->assertStatus(422);

        $this->postJson('/api/locations', ['points' => [
            ['uuid' => 'not-a-uuid', 'latitude' => 91, 'longitude' => 3.3, 'recorded_at' => 'yesterday-ish'],
        ]])->assertStatus(422)->assertJsonValidationErrors(['points.0.uuid', 'points.0.latitude', 'points.0.recorded_at']);

        config(['tracking.max_batch_size' => 3]);
        $this->postJson('/api/locations', ['points' => array_map(fn () => $this->point(6.6, 3.34), range(1, 4))])
            ->assertStatus(422)->assertJsonValidationErrors('points');

        $this->assertSame(0, LocationPoint::count());
    }

    public function test_a_large_offline_batch_is_accepted(): void
    {
        Sanctum::actingAs($agent = $this->agent());

        $points = [];
        for ($i = 0; $i < 500; $i++) {
            $points[] = $this->point(6.6 + $i / 100000, 3.34, now()->subSeconds(1800 - $i * 3), (string) Str::uuid());
        }
        shuffle($points);

        $this->postJson('/api/locations', ['points' => $points])->assertOk()->assertJson(['stored' => 500]);
        $this->assertSame(500, LocationPoint::where('user_id', $agent->id)->count());
    }

    public function test_the_endpoint_is_rate_limited_per_agent(): void
    {
        config(['tracking.rate_limit_per_minute' => 2]);

        Sanctum::actingAs($this->agent());
        $this->postJson('/api/locations', ['points' => [$this->point(6.6, 3.34)]])->assertOk();
        $this->postJson('/api/locations', ['points' => [$this->point(6.6, 3.34)]])->assertOk();
        $this->postJson('/api/locations', ['points' => [$this->point(6.6, 3.34)]])->assertStatus(429);

        // Another agent on the same IP address has their own allowance.
        Sanctum::actingAs($this->agent());
        $this->postJson('/api/locations', ['points' => [$this->point(6.6, 3.34)]])->assertOk();
    }
}
