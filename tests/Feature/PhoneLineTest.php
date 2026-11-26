<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Models\VoiceCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhoneLineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.africastalking' => [
            'username' => 'rhye', 'api_key' => 'test-key', 'voice_number' => '+2342017001234',
            'forward_to' => '', 'webhook_token' => 'secret-token', 'greeting' => null,
        ]]);
    }

    public function test_placing_a_call_rings_the_operator_then_connects_the_contact(): void
    {
        Http::fake(['voice.africastalking.com/call' => Http::response(['entries' => [['phoneNumber' => '+2348030000001', 'status' => 'Queued', 'sessionId' => 'ATVId_abc']], 'errorMessage' => 'None'])]);
        $admin = User::factory()->superAdmin()->create();
        $contact = User::factory()->coordinator()->create(['phone' => '+2348031112222']);

        $this->actingAs($admin)->postJson('/admin/command/calls', ['user_id' => $contact->id, 'ring_me' => '0803 000 0001'])
            ->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'is ringing'));

        Http::assertSent(fn (ClientRequest $r) => $r['to'] === '+2348030000001' && $r['from'] === '+2342017001234' && $r->hasHeader('apiKey', 'test-key'));
        $this->assertDatabaseHas('voice_calls', ['session_id' => 'ATVId_abc', 'direction' => 'outbound', 'status' => 'ringing', 'contact_phone' => '+2348031112222', 'operator_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'command_centre.call_placed', 'user_id' => $admin->id]);

        // The operator answers: AT asks what to do and we dial the contact.
        $this->post('/api/webhooks/voice/secret-token', ['sessionId' => 'ATVId_abc', 'isActive' => '1', 'direction' => 'Outbound'])
            ->assertOk()->assertSee('<Dial phoneNumbers="+2348031112222"', false);

        $this->post('/api/webhooks/voice/secret-token', ['sessionId' => 'ATVId_abc', 'isActive' => '0', 'dialDurationInSeconds' => '75', 'amount' => '12.5', 'currencyCode' => 'NGN', 'recordingUrl' => 'https://rec.example/a.mp3'])->assertOk();
        $this->assertDatabaseHas('voice_calls', ['session_id' => 'ATVId_abc', 'status' => 'completed', 'duration_seconds' => 75, 'recording_url' => 'https://rec.example/a.mp3']);
    }

    public function test_a_call_into_the_line_shows_live_then_as_a_missed_call_to_return(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $caller = User::factory()->role(Role::Member)->create(['phone' => '+2348035556666', 'name' => 'Musa Bello']);

        $this->post('/api/webhooks/voice/secret-token', ['sessionId' => 'ATVId_in', 'isActive' => '1', 'direction' => 'Inbound', 'callerNumber' => '+2348035556666'])
            ->assertOk()->assertSee('<Record', false);

        $this->actingAs($admin)->getJson('/admin/command/calls')
            ->assertJsonPath('calls.0.name', 'Musa Bello')->assertJsonPath('calls.0.live', true);

        $this->post('/api/webhooks/voice/secret-token', ['sessionId' => 'ATVId_in', 'isActive' => '0', 'durationInSeconds' => '4']);
        $this->actingAs($admin)->getJson('/admin/command/calls')
            ->assertJsonPath('calls.0.status', 'missed')->assertJsonPath('calls.0.needs_call_back', true)->assertJsonPath('waiting', 1);

        $call = VoiceCall::firstWhere('session_id', 'ATVId_in');
        $this->assertSame($caller->id, $call->contact_user_id);
        $this->actingAs($admin)->patchJson("/admin/command/calls/{$call->id}/handled")->assertOk();
        $this->actingAs($admin)->getJson('/admin/command/calls')->assertJsonPath('waiting', 0);
    }

    public function test_calls_into_the_line_ring_the_forwarding_phones_when_set(): void
    {
        config(['services.africastalking.forward_to' => '08030000009, +2348030000008']);

        $this->post('/api/webhooks/voice/secret-token', ['sessionId' => 'ATVId_fw', 'isActive' => '1', 'direction' => 'Inbound', 'callerNumber' => '+2348099990000'])
            ->assertOk()->assertSee('phoneNumbers="+2348030000009,+2348030000008"', false);
    }

    public function test_the_webhook_needs_the_secret_and_only_super_admins_use_the_line(): void
    {
        $this->post('/api/webhooks/voice/wrong', ['sessionId' => 'x', 'isActive' => '1'])->assertNotFound();
        $this->assertDatabaseCount('voice_calls', 0);

        $coordinator = User::factory()->coordinator()->create();
        $this->actingAs($coordinator)->getJson('/admin/command/calls')->assertForbidden();
        $this->actingAs($coordinator)->postJson('/admin/command/calls', ['user_id' => $coordinator->id, 'ring_me' => '08030000001'])->assertForbidden();
    }

    public function test_without_the_line_set_up_the_call_desk_says_so(): void
    {
        config(['services.africastalking.api_key' => null]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/command')->assertSee('not connected yet');
        $this->actingAs($admin)->postJson('/admin/command/calls', ['user_id' => 1, 'ring_me' => '08030000001'])->assertStatus(503);
    }

    public function test_a_call_from_the_operators_own_phone_is_logged_and_its_outcome_kept(): void
    {
        config(['services.africastalking.api_key' => null]);
        $admin = User::factory()->superAdmin()->create();
        $contact = User::factory()->coordinator()->create(['phone' => '+2348031112222', 'name' => 'Hauwa Musa']);

        $id = $this->actingAs($admin)->postJson('/admin/command/calls/direct', ['user_id' => $contact->id])->assertOk()->json('id');
        $this->actingAs($admin)->getJson('/admin/command/calls')
            ->assertJsonPath('calls.0.status', 'dialled')->assertJsonPath('calls.0.channel', 'phone')->assertJsonPath('calls.0.name', 'Hauwa Musa');

        $this->actingAs($admin)->patchJson("/admin/command/calls/{$id}/outcome", ['outcome' => 'call_later', 'note' => 'At a rally, try after 6'])->assertOk();
        $this->actingAs($admin)->getJson('/admin/command/calls')
            ->assertJsonPath('calls.0.status', 'call_later')->assertJsonPath('calls.0.needs_call_back', true)->assertJsonPath('waiting', 1);

        // Calling again from the follow-up deals with it.
        $this->actingAs($admin)->postJson('/admin/command/calls/direct', ['call_id' => $id])->assertOk();
        $this->actingAs($admin)->getJson('/admin/command/calls')->assertJsonPath('waiting', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'command_centre.call_placed', 'user_id' => $admin->id]);

        $this->actingAs($admin)->patchJson("/admin/command/calls/{$id}/outcome", ['outcome' => 'nonsense'])->assertUnprocessable();
    }

    public function test_a_call_to_the_hotline_is_typed_in_and_matched_to_the_member(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $member = User::factory()->role(Role::Member)->create(['phone' => '+2348035556666', 'name' => 'Musa Bello']);

        $this->actingAs($admin)->postJson('/admin/command/calls/incoming', ['phone' => '0803 555 6666', 'answered' => false, 'note' => 'Wants to volunteer'])
            ->assertOk()->assertJsonPath('name', 'Musa Bello');
        $this->assertDatabaseHas('voice_calls', ['direction' => 'inbound', 'channel' => 'phone', 'status' => 'missed', 'contact_user_id' => $member->id, 'note' => 'Wants to volunteer']);

        $this->actingAs($admin)->postJson('/admin/command/calls/incoming', ['phone' => '12345', 'answered' => true])->assertUnprocessable();
        $this->actingAs(User::factory()->coordinator()->create())->postJson('/admin/command/calls/incoming', ['phone' => '08035556666', 'answered' => true])->assertForbidden();
    }

    public function test_a_line_test_call_plays_a_message(): void
    {
        Http::fake(['voice.africastalking.com/call' => Http::response(['entries' => [['status' => 'Queued', 'sessionId' => 'ATVId_test']]])]);

        $this->artisan('voice:test', ['phone' => '0803 000 0001'])->assertSuccessful();
        $this->post('/api/webhooks/voice/secret-token', ['sessionId' => 'ATVId_test', 'isActive' => '1', 'direction' => 'Outbound'])
            ->assertOk()->assertSee('<Say>', false)->assertDontSee('<Dial', false);
        $this->post('/api/webhooks/voice/secret-token', ['sessionId' => 'ATVId_test', 'isActive' => '0', 'durationInSeconds' => '9']);

        $this->assertDatabaseHas('voice_calls', ['session_id' => 'ATVId_test', 'status' => 'completed', 'contact_phone' => '+2348030000001']);
    }
}
