<?php

namespace Tests\Feature;

use App\Enums\CanvasserStatus;
use App\Models\AuditLog;
use App\Models\FieldActivity;
use App\Models\MemberProfile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Canvasser location sharing: only approved canvassers, only after consent,
 * only during a shift they start; coordinators see their own state only.
 */
class FieldTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake(); // no live broadcasts in tests
    }

    private function canvasser(object $area, string $name = 'Ada Canvasser', ?CanvasserStatus $status = CanvasserStatus::Approved): User
    {
        $user = User::factory()->member($area)->create(['name' => $name]);
        MemberProfile::factory()->for($user)->state($status ? ['canvasser_status' => $status] : [])->create();

        return $user;
    }

    /** @return array<string, mixed> */
    private function gpsPoint(float $lat = 6.6005, float $lng = 3.3401, ?string $at = null): array
    {
        return ['uuid' => (string) Str::uuid(), 'latitude' => $lat, 'longitude' => $lng, 'accuracy' => 12, 'recorded_at' => $at ?? now()->toIso8601String()];
    }

    private function onShift(User $canvasser): void
    {
        $this->actingAs($canvasser)->post('/membership/field/consent', ['accepted' => 1]);
        $this->actingAs($canvasser)->postJson('/membership/field/shift')->assertCreated();
    }

    public function test_an_approved_canvasser_consents_starts_a_shift_shares_location_and_logs_activities(): void
    {
        $geo = $this->geography();
        $ada = $this->canvasser($geo['wardA']);
        $this->actingAs($ada);

        $this->get('/membership/field')->assertOk()->assertSee('Before you start: location sharing')->assertDontSee('Location sharing is off');
        // No shift without consent.
        $this->postJson('/membership/field/shift')->assertForbidden();

        $this->post('/membership/field/consent', ['accepted' => 1])->assertRedirect();
        $this->get('/membership/field')->assertOk()->assertSee('Location sharing is off');
        $this->postJson('/membership/field/shift')->assertCreated();

        $this->postJson('/membership/field/locations', ['points' => [$this->gpsPoint(), $this->gpsPoint(6.6010, 3.3405)]])
            ->assertOk()->assertJson(['stored' => 2, 'shift_active' => true]);
        $this->assertDatabaseCount('location_points', 2);
        $this->assertDatabaseHas('agent_positions', ['user_id' => $ada->id]);

        $this->post('/membership/field/activities', [
            'uuid' => $uuid = (string) Str::uuid(), 'kind' => 'door_to_door', 'households' => 6, 'outcome' => 'supportive',
            'latitude' => 6.6005, 'longitude' => 3.3401, 'accuracy' => 9,
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('activity.label', 'Door-to-door visit');
        // Re-sending the same activity (a retry after a dropped connection) stores it once.
        $this->post('/membership/field/activities', [
            'uuid' => $uuid, 'kind' => 'door_to_door', 'households' => 6, 'outcome' => 'supportive', 'latitude' => 6.6005, 'longitude' => 3.3401,
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['duplicate' => true]);

        $activity = FieldActivity::sole();
        $this->assertSame($geo['state']->id, $activity->state_id);
        $this->assertSame($geo['wardA']->id, $activity->ward_id); // resolved from the GPS point

        $this->deleteJson('/membership/field/shift')->assertOk();
        // After the shift, new points are refused rather than recorded.
        $this->postJson('/membership/field/locations', ['points' => [$this->gpsPoint(at: now()->addSeconds(5)->toIso8601String())]])->assertOk()->assertJson(['stored' => 0, 'outside_shift' => 1]);
        $this->postJson('/membership/field/activities', ['uuid' => (string) Str::uuid(), 'kind' => 'report', 'note' => 'x', 'latitude' => 6.6, 'longitude' => 3.3])->assertStatus(409);
    }

    public function test_only_approved_canvassers_can_use_field_work(): void
    {
        $geo = $this->geography();

        foreach ([null, CanvasserStatus::Pending, CanvasserStatus::Rejected] as $status) {
            $member = $this->canvasser($geo['wardA'], 'Not Yet '.($status?->value ?? 'none'), $status);
            $this->actingAs($member)->get('/membership/field')->assertForbidden();
            $this->actingAs($member)->post('/membership/field/consent', ['accepted' => 1])->assertForbidden();
            $this->actingAs($member)->postJson('/membership/field/shift')->assertForbidden();
            $this->actingAs($member)->postJson('/membership/field/locations', ['points' => [$this->gpsPoint()]])->assertForbidden();
            $this->actingAs($member)->get('/membership')->assertDontSee('Field work');
        }

        $this->actingAs($this->canvasser($geo['wardA']))->get('/membership')->assertSee('Field work');
        $this->assertDatabaseCount('location_points', 0);
    }

    public function test_withdrawing_consent_ends_the_shift_and_stops_tracking(): void
    {
        $geo = $this->geography();
        $ada = $this->canvasser($geo['wardA']);
        $this->onShift($ada);

        $this->actingAs($ada)->delete('/membership/field/consent')->assertRedirect();

        $this->assertNull($ada->fresh()->tracking_consent_accepted_at);
        $this->assertFalse($ada->activeShift()->exists());
        $this->actingAs($ada)->postJson('/membership/field/locations', ['points' => [$this->gpsPoint()]])->assertForbidden();
    }

    public function test_activities_are_validated_by_kind(): void
    {
        $geo = $this->geography();
        $ada = $this->canvasser($geo['wardA']);
        $this->onShift($ada);

        $this->actingAs($ada)->postJson('/membership/field/activities', ['uuid' => (string) Str::uuid(), 'kind' => 'door_to_door', 'latitude' => 6.6, 'longitude' => 3.3])
            ->assertJsonValidationErrors(['households', 'outcome']);
        $this->actingAs($ada)->postJson('/membership/field/activities', ['uuid' => (string) Str::uuid(), 'kind' => 'report'])
            ->assertJsonValidationErrors(['note', 'latitude']);
    }

    public function test_the_live_feed_shows_a_coordinator_only_their_own_states_canvassers(): void
    {
        $geo = $this->geography();
        $ada = $this->canvasser($geo['wardA'], 'Ada Home');
        $far = $this->canvasser($geo['farWard'], 'Far Away Canvasser');
        $plain = User::factory()->member($geo['wardA'])->create(['name' => 'Plain Member']);
        MemberProfile::factory()->for($plain)->create();

        foreach ([$ada, $far] as $c) {
            $this->onShift($c);
            $this->actingAs($c)->postJson('/membership/field/locations', ['points' => [$this->gpsPoint()]]);
            $this->actingAs($c)->postJson('/membership/field/activities', ['uuid' => (string) Str::uuid(), 'kind' => 'report', 'note' => 'Report by '.$c->name, 'latitude' => 6.6, 'longitude' => 3.34]);
        }

        $coordinator = User::factory()->coordinator($geo['state'])->create();
        $feed = $this->actingAs($coordinator)->getJson('/coordinator/live/feed')->assertOk();

        $this->assertSame(['Ada Home'], array_column($feed->json('canvassers'), 'name'));
        $this->assertSame(['Report by Ada Home'], array_column($feed->json('activities'), 'note'));
        $this->assertTrue($feed->json('canvassers.0.on_shift'));
        $this->assertSame('active', $feed->json('canvassers.0.status'));
        $this->assertNotNull($feed->json('canvassers.0.position'));
        $this->assertStringNotContainsString('Plain Member', $feed->getContent());
        // Asking for another state changes nothing for a coordinator.
        $this->assertSame($feed->json('canvassers'), $this->actingAs($coordinator)->getJson('/coordinator/live/feed?state_id='.$geo['otherState']->id)->json('canvassers'));

        $admin = User::factory()->superAdmin()->create();
        $this->assertCount(2, $this->actingAs($admin)->getJson('/admin/live/feed')->json('canvassers'));
        $this->assertSame(['Far Away Canvasser'], array_column($this->actingAs($admin)->getJson('/admin/live/feed?state_id='.$geo['otherState']->id)->json('canvassers'), 'name'));
    }

    public function test_routes_are_scoped_and_every_view_is_audit_logged(): void
    {
        $geo = $this->geography();
        $ada = $this->canvasser($geo['wardA']);
        $far = $this->canvasser($geo['farWard'], 'Far Away Canvasser');
        $this->onShift($ada);
        $ada->activeShift()->update(['started_at' => now()->subMinutes(30)]);
        $this->actingAs($ada)->postJson('/membership/field/locations', ['points' => [
            $this->gpsPoint(6.6001, 3.3401, now()->subMinutes(10)->toIso8601String()),
            $this->gpsPoint(6.6020, 3.3420, now()->subMinutes(5)->toIso8601String()),
        ]]);

        $coordinator = User::factory()->coordinator($geo['state'])->create();
        $this->actingAs($coordinator)->getJson("/coordinator/live/{$ada->id}/trail")
            ->assertOk()->assertJsonPath('features.0.geometry.type', 'LineString')->assertJsonPath('features.0.properties.points', 2);
        $this->actingAs($coordinator)->getJson("/coordinator/live/{$far->id}/trail")->assertNotFound();

        $log = AuditLog::where('action', AuditLogger::TRAIL_VIEWED)->sole();
        $this->assertSame($ada->id, $log->subject_id);
        $this->assertSame($coordinator->id, $log->user_id);
    }

    public function test_activity_photos_are_private_to_the_canvasser_and_their_states_staff(): void
    {
        Storage::fake('local');
        $geo = $this->geography();
        $ada = $this->canvasser($geo['wardA']);
        $this->onShift($ada);
        $this->actingAs($ada)->post('/membership/field/activities', [
            'uuid' => (string) Str::uuid(), 'kind' => 'report', 'note' => 'Rally', 'latitude' => 6.6, 'longitude' => 3.34,
            'photo' => UploadedFile::fake()->image('rally.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $activity = FieldActivity::sole();
        $this->assertNotNull($activity->photo_path);

        $this->actingAs(User::factory()->coordinator($geo['otherState'])->create())->get("/field/activities/{$activity->id}/photo")->assertNotFound();
        $this->actingAs($this->canvasser($geo['wardA'], 'Another Canvasser'))->get("/field/activities/{$activity->id}/photo")->assertNotFound();
    }

    public function test_the_live_map_page_is_for_staff_only(): void
    {
        $geo = $this->geography();

        $this->actingAs(User::factory()->coordinator($geo['state'])->create())->get('/coordinator/live')->assertOk()->assertSee('Live map');
        $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/live')->assertOk()->assertSee('All states');
        $this->actingAs($this->canvasser($geo['wardA']))->get('/coordinator/live')->assertForbidden();
    }
}
