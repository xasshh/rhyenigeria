<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_trail_is_returned_as_geojson_in_recorded_order(): void
    {
        $this->freezeSecond();
        $geo = $this->geography();
        Sanctum::actingAs($agent = $this->agent($geo['wardA']));

        // Sent out of order.
        $this->postJson('/api/locations', ['points' => [
            $this->point(6.6030, 3.3430, now()->subMinutes(10)),
            $this->point(6.6010, 3.3410, now()->subMinutes(30)),
            $this->point(6.6020, 3.3420, now()->subMinutes(20)),
        ]])->assertOk();

        Sanctum::actingAs(User::factory()->coordinator($geo['lga'])->create());

        $response = $this->getJson("/api/agents/{$agent->id}/trail")->assertOk();

        $response->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonPath('meta.point_count', 3)
            ->assertJsonPath('meta.agent.id', $agent->id)
            ->assertJsonCount(4, 'features');

        // First the path: GeoJSON coordinates are [longitude, latitude].
        $line = $response->json('features.0');
        $this->assertSame('LineString', $line['geometry']['type']);
        $this->assertEqualsWithDelta([[3.3410, 6.6010], [3.3420, 6.6020], [3.3430, 6.6030]], $line['geometry']['coordinates'], 0.0000001);
        $this->assertSame('trail', $line['properties']['kind']);

        // Then each point, oldest first, with its resolved ward.
        $points = array_slice($response->json('features'), 1);
        $this->assertSame(['Point', 'Point', 'Point'], array_column(array_column($points, 'geometry'), 'type'));
        $this->assertSame(
            [now()->subMinutes(30)->toIso8601ZuluString(), now()->subMinutes(20)->toIso8601ZuluString(), now()->subMinutes(10)->toIso8601ZuluString()],
            array_column(array_column($points, 'properties'), 'recorded_at'),
        );
        $this->assertSame($geo['wardA']->id, $points[0]['properties']['ward_id']);
        $this->assertSame(8.5, $points[0]['properties']['accuracy']);
    }

    public function test_the_trail_is_limited_to_the_requested_time_window(): void
    {
        $this->freezeSecond();
        Sanctum::actingAs($agent = $this->agent());
        Shift::query()->update(['started_at' => now()->subDays(3)]);

        $this->postJson('/api/locations', ['points' => [
            $this->point(6.60, 3.34, now()->subHours(50)),
            $this->point(6.61, 3.34, now()->subHours(30)),
            $this->point(6.62, 3.34, now()->subHours(5)),
            $this->point(6.63, 3.34, now()->subHours(1)),
        ]])->assertOk()->assertJson(['stored' => 4]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        // Default window: the last 24 hours.
        $this->getJson("/api/agents/{$agent->id}/trail")->assertOk()->assertJsonPath('meta.point_count', 2);

        // Explicit window, with a time zone offset on the query.
        $from = now()->subHours(31)->setTimezone('Africa/Lagos')->toIso8601String();
        $to = now()->subHours(4)->setTimezone('Africa/Lagos')->toIso8601String();
        $response = $this->getJson("/api/agents/{$agent->id}/trail?".http_build_query(['from' => $from, 'to' => $to]))
            ->assertOk()
            ->assertJsonPath('meta.point_count', 2);
        $this->assertEqualsWithDelta([[3.34, 6.61], [3.34, 6.62]], $response->json('features.0.geometry.coordinates'), 0.0000001);

        // A window with one point has no line; with none, no features at all.
        $this->getJson("/api/agents/{$agent->id}/trail?".http_build_query(['from' => now()->subHours(2)->toIso8601String()]))
            ->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.geometry.type', 'Point');
        $this->getJson("/api/agents/{$agent->id}/trail?".http_build_query(['from' => now()->subDays(20)->toIso8601String(), 'to' => now()->subDays(10)->toIso8601String()]))
            ->assertOk()->assertJsonCount(0, 'features');

        // points=0 returns only the line.
        $this->getJson("/api/agents/{$agent->id}/trail?points=0")->assertOk()->assertJsonCount(1, 'features');
    }

    public function test_bad_windows_are_rejected(): void
    {
        $agent = $this->agent();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson("/api/agents/{$agent->id}/trail?from=2026-02-01T00:00:00Z&to=2026-01-01T00:00:00Z")->assertStatus(422);
        $this->getJson("/api/agents/{$agent->id}/trail?from=2026-01-01T00:00:00Z&to=2026-06-01T00:00:00Z")->assertStatus(422);
        $this->getJson("/api/agents/{$agent->id}/trail?from=not-a-date")->assertStatus(422);

        // Only field agents have trails.
        $this->getJson('/api/agents/'.User::factory()->coordinator()->create()->id.'/trail')->assertNotFound();
    }

    public function test_viewing_a_trail_writes_an_audit_log_entry(): void
    {
        $this->freezeSecond();
        $geo = $this->geography();
        $agent = $this->agent($geo['wardA']);
        Sanctum::actingAs($agent);
        $this->postJson('/api/locations', ['points' => [$this->point(6.60, 3.34)]])->assertOk();

        $coordinator = User::factory()->coordinator($geo['wardA'])->create(['name' => 'Ada Coordinator']);
        Sanctum::actingAs($coordinator);

        $this->getJson("/api/agents/{$agent->id}/trail", ['User-Agent' => 'MapUI/1.0'])->assertOk();

        $log = AuditLog::sole();
        $this->assertSame($coordinator->id, $log->user_id);                 // who
        $this->assertSame('Ada Coordinator', $log->user_name);
        $this->assertSame('trail.viewed', $log->action);                    // what
        $this->assertSame('App\\Models\\User', $log->subject_type);
        $this->assertSame($agent->id, $log->subject_id);
        $this->assertSame(1, $log->metadata['points']);
        $this->assertSame(now()->subDay()->toIso8601ZuluString(), $log->metadata['from']);
        $this->assertTrue($log->created_at->equalTo(now()));                // when
        $this->assertSame('MapUI/1.0', $log->user_agent);
    }

    public function test_a_refused_trail_request_is_not_logged_as_a_view(): void
    {
        $geo = $this->geography();
        $agent = $this->agent($geo['wardB']);
        Sanctum::actingAs(User::factory()->coordinator($geo['wardA'])->create());

        $this->getJson("/api/agents/{$agent->id}/trail")->assertForbidden();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_the_audit_log_can_be_read_by_a_super_admin(): void
    {
        $agent = $this->agent();
        Sanctum::actingAs($admin = User::factory()->superAdmin()->create());

        $this->getJson("/api/agents/{$agent->id}/trail")->assertOk();

        $this->getJson('/api/audit-logs?action=trail.viewed')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.user_id', $admin->id)
            ->assertJsonPath('data.0.subject_id', $agent->id);
    }
}
