<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(int $pollingUnitId, array $overrides = []): array
    {
        return $overrides + [
            'uuid' => (string) Str::uuid(),
            'polling_unit_id' => $pollingUnitId,
            'latitude' => 6.6004,
            'longitude' => 3.3402,
            'accuracy' => 12,
            'collected_at' => now()->subMinutes(5)->toIso8601String(),
            'answers' => ['turnout' => 120, 'incident' => false, 'notes' => 'Calm', 'tags' => ['a', 'b']],
        ];
    }

    public function test_an_agent_submits_a_record_with_flexible_answers(): void
    {
        $geo = $this->geography();
        Sanctum::actingAs($agent = $this->agent($geo['wardA'], onShift: false));

        $response = $this->postJson('/api/submissions', $this->payload($geo['unitA']->id))->assertCreated();

        $response->assertJsonPath('duplicate', false)
            ->assertJsonPath('submission.agent.id', $agent->id)
            ->assertJsonPath('submission.polling_unit.id', $geo['unitA']->id)
            ->assertJsonPath('submission.ward_id', $geo['wardA']->id)
            ->assertJsonPath('submission.lga_id', $geo['lga']->id)
            ->assertJsonPath('submission.state_id', $geo['state']->id)
            ->assertJsonPath('submission.answers.turnout', 120)
            ->assertJsonPath('submission.answers.incident', false)
            ->assertJsonPath('submission.answers.tags', ['a', 'b'])
            ->assertJsonPath('submission.form', null);

        $this->assertEqualsWithDelta(6.6004, $response->json('submission.latitude'), 0.0000001);
        $this->assertSame(4326, (int) \DB::scalar('SELECT ST_SRID(location) FROM submissions'));
    }

    public function test_resending_the_same_uuid_returns_the_stored_record(): void
    {
        $geo = $this->geography();
        Sanctum::actingAs($this->agent($geo['wardA']));

        $payload = $this->payload($geo['unitA']->id);
        $id = $this->postJson('/api/submissions', $payload)->assertCreated()->json('submission.id');

        $this->postJson('/api/submissions', $payload)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('submission.id', $id);
        $this->assertSame(1, Submission::count());

        // Someone else cannot reuse it.
        Sanctum::actingAs($this->agent($geo['wardA']));
        $this->postJson('/api/submissions', $payload)->assertStatus(409);
    }

    public function test_photos_are_stored_privately_and_served_only_to_those_who_may_see_the_submission(): void
    {
        Storage::fake('local');
        $geo = $this->geography();
        Sanctum::actingAs($agent = $this->agent($geo['wardA']));

        // Multipart: answers travel as a JSON string.
        $payload = $this->payload($geo['unitA']->id);
        $payload['answers'] = json_encode($payload['answers']);
        $payload['photos'] = [UploadedFile::fake()->image('front.jpg', 800, 600), UploadedFile::fake()->image('queue.png')];

        $response = $this->post('/api/submissions', $payload, ['Accept' => 'application/json'])->assertCreated();
        $response->assertJsonCount(2, 'submission.photos')->assertJsonPath('submission.answers.turnout', 120);

        $submission = Submission::sole();
        $photo = $submission->photos()->first();
        Storage::disk('local')->assertExists($photo->path);
        $this->assertStringStartsWith("submissions/{$submission->id}/", $photo->path);
        $this->assertArrayNotHasKey('path', $response->json('submission.photos.0'));

        // One more, uploaded on its own.
        $this->post("/api/submissions/{$submission->id}/photos", ['photo' => UploadedFile::fake()->image('late.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(3, $submission->photos()->count());

        $url = "/api/submissions/{$submission->id}/photos/{$photo->id}";
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        Sanctum::actingAs(User::factory()->coordinator($geo['wardA'])->create());
        $this->get($url)->assertOk();

        Sanctum::actingAs(User::factory()->coordinator($geo['wardB'])->create());
        $this->getJson($url)->assertForbidden();

        Sanctum::actingAs($this->agent($geo['wardA']));
        $this->getJson($url)->assertForbidden();
        $this->post("/api/submissions/{$submission->id}/photos", ['photo' => UploadedFile::fake()->image('x.jpg')], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_photo_limits_are_enforced(): void
    {
        Storage::fake('local');
        config(['fielddata.max_photos' => 2, 'fielddata.photo_max_kb' => 100]);
        $geo = $this->geography();
        Sanctum::actingAs($this->agent($geo['wardA']));

        $base = $this->payload($geo['unitA']->id);
        $base['answers'] = json_encode($base['answers']);

        $this->post('/api/submissions', $base + ['photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')]], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('photos');
        $this->post('/api/submissions', $base + ['photos' => [UploadedFile::fake()->create('big.jpg', 500, 'image/jpeg')]], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('photos.0');
        $this->post('/api/submissions', $base + ['photos' => [UploadedFile::fake()->create('script.php', 10, 'text/x-php')]], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('photos.0');

        $this->assertSame(0, Submission::count());
    }

    public function test_a_forms_required_fields_are_enforced_but_extra_answers_are_kept(): void
    {
        $geo = $this->geography();
        $form = Form::factory()->create(['key' => 'pu-report', 'fields' => [
            ['name' => 'turnout', 'type' => 'number', 'required' => true],
            ['name' => 'notes', 'type' => 'text'],
        ]]);
        Sanctum::actingAs($this->agent($geo['wardA']));

        $this->postJson('/api/submissions', $this->payload($geo['unitA']->id, ['form_key' => 'pu-report', 'answers' => ['notes' => 'x']]))
            ->assertStatus(422)->assertJsonValidationErrors('answers');

        $this->postJson('/api/submissions', $this->payload($geo['unitA']->id, ['form_id' => $form->id, 'answers' => ['turnout' => 0, 'something_new' => ['nested' => true]]]))
            ->assertCreated()
            ->assertJsonPath('submission.form.key', 'pu-report')
            ->assertJsonPath('submission.answers.turnout', 0)
            ->assertJsonPath('submission.answers.something_new.nested', true);

        $form->update(['is_active' => false]);
        $this->postJson('/api/submissions', $this->payload($geo['unitA']->id, ['form_id' => $form->id, 'answers' => ['turnout' => 1]]))->assertStatus(422);
    }

    public function test_required_parts_of_a_submission_are_validated(): void
    {
        $geo = $this->geography();
        Sanctum::actingAs($this->agent($geo['wardA']));

        $this->postJson('/api/submissions', [])->assertStatus(422)
            ->assertJsonValidationErrors(['uuid', 'polling_unit_id', 'latitude', 'longitude', 'collected_at', 'answers']);

        $this->postJson('/api/submissions', $this->payload(999999))->assertStatus(422)->assertJsonValidationErrors('polling_unit_id');

        // No answers yet is fine: the structure is flexible.
        $this->postJson('/api/submissions', $this->payload($geo['unitA']->id, ['answers' => []]))->assertCreated();
    }

    public function test_submissions_are_scoped_by_area(): void
    {
        $geo = $this->geography();

        Sanctum::actingAs($agentA = $this->agent($geo['wardA']));
        $inA = $this->postJson('/api/submissions', $this->payload($geo['unitA']->id))->json('submission.id');

        Sanctum::actingAs($agentB = $this->agent($geo['wardB']));
        $inB = $this->postJson('/api/submissions', $this->payload($geo['unitB']->id))->json('submission.id');

        $visible = fn (User $user) => tap(Sanctum::actingAs($user), fn () => null) ? collect($this->getJson('/api/submissions')->assertOk()->json('data'))->pluck('id')->sort()->values()->all() : [];

        $this->assertSame([$inA, $inB], $visible(User::factory()->superAdmin()->create()));
        $this->assertSame([$inA, $inB], $visible(User::factory()->coordinator($geo['lga'])->create()));
        $this->assertSame([$inA], $visible(User::factory()->coordinator($geo['wardA'])->create()));
        $this->assertSame([], $visible(User::factory()->coordinator($geo['otherState'])->create()));
        $this->assertSame([], $visible(User::factory()->coordinator()->create()));
        $this->assertSame([$inB], $visible($agentB));

        Sanctum::actingAs(User::factory()->coordinator($geo['wardA'])->create());
        $this->getJson("/api/submissions/{$inA}")->assertOk();
        $this->getJson("/api/submissions/{$inB}")->assertForbidden();

        Sanctum::actingAs($agentA);
        $this->getJson("/api/submissions/{$inB}")->assertForbidden();
    }

    public function test_only_field_agents_submit_records(): void
    {
        $geo = $this->geography();

        Sanctum::actingAs(User::factory()->coordinator($geo['state'])->create());
        $this->postJson('/api/submissions', $this->payload($geo['unitA']->id))->assertForbidden();
    }

    public function test_exporting_is_scoped_and_writes_an_audit_log_entry(): void
    {
        $this->freezeSecond();
        $geo = $this->geography();

        Sanctum::actingAs($this->agent($geo['wardA']));
        $this->postJson('/api/submissions', $this->payload($geo['unitA']->id, ['answers' => ['notes' => '=HYPERLINK("http://evil")', 'turnout' => 7]]))->assertCreated();
        Sanctum::actingAs($this->agent($geo['wardB']));
        $this->postJson('/api/submissions', $this->payload($geo['unitB']->id))->assertCreated();

        $coordinator = User::factory()->coordinator($geo['wardA'])->create();
        Sanctum::actingAs($coordinator);

        $response = $this->get('/api/submissions/export?state_id='.$geo['state']->id)->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=submissions-', $response->headers->get('Content-Disposition'));

        $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), array_filter(explode("\n", $response->streamedContent())));
        $this->assertCount(2, $rows, 'Header plus the one submission inside the coordinator\'s ward.');
        $record = array_combine($rows[0], $rows[1]);
        $this->assertSame($geo['unitA']->code, $record['polling_unit_code']);
        $this->assertSame($geo['wardA']->name, $record['ward']);
        $this->assertSame($geo['state']->name, $record['state']);
        $this->assertSame(7, json_decode($record['answers'], true)['turnout']);

        $log = AuditLog::sole();
        $this->assertSame($coordinator->id, $log->user_id);
        $this->assertSame('submissions.exported', $log->action);
        $this->assertSame(1, $log->metadata['rows']);
        $this->assertSame((string) $geo['state']->id, (string) $log->metadata['filters']['state_id']);
        $this->assertTrue($log->created_at->equalTo(now()));
    }

    public function test_field_agents_cannot_export(): void
    {
        Sanctum::actingAs($this->agent());

        $this->getJson('/api/submissions/export')->assertForbidden();
        $this->assertSame(0, AuditLog::count());
    }
}
