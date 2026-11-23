<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    private function reporter(): User
    {
        $geo = $this->geography();
        $user = User::factory()->member($geo['wardA'])->create(['name' => 'Bala Reporter']);
        MemberProfile::factory()->for($user)->create();

        return $user;
    }

    public function test_a_member_reports_an_incident_with_photos_and_location(): void
    {
        Storage::fake('local');
        $user = $this->reporter();

        $this->actingAs($user)->get('/membership/incidents')->assertOk()->assertSee('Send to the command centre');

        $this->actingAs($user)->post('/membership/incidents', [
            'category' => 'ballot_snatching',
            'urgency' => 'critical',
            'description' => 'Thugs took the ballot box at the school and ran towards the market.',
            'polling_unit_name' => 'PU 004 Gidan Waya Primary School',
            'latitude' => '10.512345', 'longitude' => '7.433210', 'accuracy' => '12',
            'photos' => [UploadedFile::fake()->image('a.jpg', 2400, 1800), UploadedFile::fake()->image('b.png', 800, 600)],
        ])->assertRedirect(route('membership.incidents'));

        $incident = Incident::sole();
        $this->assertSame($user->id, $incident->user_id);
        $this->assertSame($user->ward_id, $incident->ward_id);
        $this->assertSame('new', $incident->status);
        $this->assertSame(10.512345, $incident->latitude);
        $this->assertCount(2, $incident->photos);
        foreach ($incident->photos as $photo) {
            Storage::disk('local')->assertExists($photo->path);
            $this->assertStringEndsWith('.webp', $photo->path);
        }

        // The reporter adds an update while it is still going on.
        $this->actingAs($user)->post("/membership/incidents/{$incident->id}/updates", ['body' => 'Police have arrived.', 'photos' => [UploadedFile::fake()->image('c.jpg')]])
            ->assertSessionHas('status');
        $this->assertSame(1, $incident->updates()->count());
        $this->assertSame(3, $incident->photos()->count());
    }

    public function test_the_command_centre_sees_it_opens_it_and_resolves_it(): void
    {
        Storage::fake('local');
        $user = $this->reporter();
        $this->actingAs($user)->post('/membership/incidents', [
            'category' => 'vote_buying', 'urgency' => 'serious', 'description' => 'Money being shared at the polling unit gate.',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ]);
        $incident = Incident::sole();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->getJson('/admin/command/incidents')->assertOk()
            ->assertJsonPath('counts.new', 1)
            ->assertJsonPath('incidents.0.category_label', 'Vote buying')
            ->assertJsonPath('incidents.0.photo_count', 1);

        $detail = $this->actingAs($admin)->getJson("/admin/command/incidents/{$incident->id}")->assertOk()
            ->assertJsonPath('reporter.name', 'Bala Reporter')
            ->assertJsonPath('reporter.phone', $user->phone)
            ->assertJsonPath('status', 'seen')
            ->assertJsonCount(1, 'photos');
        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.viewed', 'user_id' => $admin->id]);

        // The admin can see the photo; another member cannot.
        $this->actingAs($admin)->get($detail->json('photos.0'))->assertOk();
        $other = User::factory()->member()->create();
        MemberProfile::factory()->for($other)->create();
        $this->actingAs($other)->get($detail->json('photos.0'))->assertNotFound();

        $this->actingAs($admin)->patchJson("/admin/command/incidents/{$incident->id}", ['status' => 'resolved', 'note' => 'Police unit sent.'])->assertOk();
        $this->assertSame('resolved', $incident->fresh()->status);
        $this->assertDatabaseHas('incident_updates', ['incident_id' => $incident->id, 'from_command' => true, 'body' => 'Marked resolved. Police unit sent.']);

        // The reporter sees the note, and can no longer add updates to a closed report.
        $this->actingAs($user)->get('/membership/incidents')->assertSee('Police unit sent.')->assertSee('Resolved');
        $this->actingAs($user)->post("/membership/incidents/{$incident->id}/updates", ['body' => 'More news'])->assertStatus(409);
        $this->actingAs($admin)->getJson('/admin/command/incidents')->assertJsonPath('counts.open', 0)->assertJsonCount(0, 'incidents');
    }

    public function test_reports_need_a_category_and_description_and_members_only_update_their_own(): void
    {
        $user = $this->reporter();
        $this->actingAs($user)->post('/membership/incidents', ['urgency' => 'minor', 'description' => 'short'])->assertSessionHasErrors(['category', 'description']);

        $someone = Incident::factory()->create();
        $this->actingAs($user)->post("/membership/incidents/{$someone->id}/updates", ['body' => 'Not mine'])->assertNotFound();
    }

    public function test_only_super_admins_use_the_incident_desk(): void
    {
        $incident = Incident::factory()->create();
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->getJson('/admin/command/incidents')->assertForbidden();
        $this->actingAs($coordinator)->getJson("/admin/command/incidents/{$incident->id}")->assertForbidden();
        $this->actingAs($coordinator)->patchJson("/admin/command/incidents/{$incident->id}", ['status' => 'resolved'])->assertForbidden();
    }
}
