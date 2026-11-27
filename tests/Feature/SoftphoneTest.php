<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\State;
use App\Models\User;
use App\Services\Voice\AfricasTalkingVoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SoftphoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.africastalking' => [
            'username' => 'UbasaniCC', 'api_key' => 'test-key', 'voice_number' => '+2342017001234',
            'forward_to' => '08030000001', 'webhook_token' => 'secret-token', 'greeting' => null,
        ]]);
    }

    public function test_staff_get_a_capability_token_for_their_browser(): void
    {
        Http::fake(['webrtc.africastalking.com/*' => Http::response(['clientName' => 'x', 'token' => 'ATCAPtkn_abc'], 201)]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->postJson('/admin/command/calls/token')->assertOk()->assertJsonPath('token', 'ATCAPtkn_abc');

        Http::assertSent(fn (ClientRequest $r) => $r['clientName'] === 'staff'.$admin->id && $r['phoneNumber'] === '+2342017001234'
            && $r['username'] === 'UbasaniCC' && $r->hasHeader('apiKey', 'test-key'));

        $member = User::factory()->create();
        $this->actingAs($member)->postJson('/admin/command/calls/token')->assertForbidden();
    }

    public function test_a_browser_call_is_connected_only_when_the_call_desk_asked_for_it(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $contact = User::factory()->coordinator()->create(['phone' => '+2348031112222']);

        $this->actingAs($admin)->postJson('/admin/command/calls', ['user_id' => $contact->id, 'browser' => true])
            ->assertOk()->assertJsonPath('dial', '+2348031112222');
        Http::assertNothingSent();

        // Another number from the same browser: refused.
        $this->post('/api/webhooks/voice/secret-token', ['sessionId' => 'ATVId_x', 'isActive' => '1', 'callerNumber' => 'UbasaniCC.staff'.$admin->id, 'clientDialedNumber' => '+2348099999999'])
            ->assertOk()->assertSee('<Reject/>', false);
        $this->post('/api/webhooks/voice/secret-token', ['sessionId' => 'ATVId_x', 'isActive' => '0', 'callerNumber' => '+2342017001234'])->assertOk();
        $this->assertDatabaseMissing('voice_calls', ['session_id' => 'ATVId_x']);

        // The number they pressed Call for: put through, recorded and logged.
        $this->post('/api/webhooks/voice/secret-token', ['sessionId' => 'ATVId_b', 'isActive' => '1', 'callerNumber' => 'UbasaniCC.staff'.$admin->id, 'clientDialedNumber' => '+2348031112222'])
            ->assertOk()->assertSee('<Dial phoneNumbers="+2348031112222"', false);
        $this->post('/api/webhooks/voice/secret-token', ['sessionId' => 'ATVId_b', 'isActive' => '0', 'dialDurationInSeconds' => '40']);

        $this->assertDatabaseHas('voice_calls', ['session_id' => 'ATVId_b', 'operator_id' => $admin->id, 'contact_user_id' => $contact->id, 'status' => 'completed', 'duration_seconds' => 40]);
    }

    public function test_a_browser_call_that_never_connects_stops_showing_as_ringing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $contact = User::factory()->coordinator()->create(['phone' => '+2348031112222']);
        $this->actingAs($admin)->postJson('/admin/command/calls', ['user_id' => $contact->id, 'browser' => true])->assertOk();

        $this->travel(3)->minutes();

        $this->actingAs($admin)->getJson('/admin/command/calls')->assertJsonPath('calls.0.status', 'failed')->assertJsonPath('calls.0.live', false);
    }

    public function test_calls_into_the_line_ring_open_browsers_and_the_desk_phone_together(): void
    {
        $kaduna = State::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $coordinator = User::factory()->coordinator($kaduna)->create();
        $otherCoordinator = User::factory()->coordinator(State::factory()->create())->create();
        User::factory()->role(Role::Member)->create(['phone' => '+2348035556666', 'state_id' => $kaduna->id]);

        $voice = app(AfricasTalkingVoice::class);
        foreach ([$admin, $coordinator, $otherCoordinator] as $u) {
            $this->actingAs($u)->postJson(($u->isSuperAdmin() ? '/admin' : '/coordinator').'/command/calls/presence', ['online' => true])->assertOk();
        }
        $this->actingAs($otherCoordinator)->postJson('/coordinator/command/calls/presence', ['online' => false]);
        $this->assertEqualsCanonicalizing([$admin->id, $coordinator->id], $voice->onlineStaffIds());

        $this->post('/api/webhooks/voice/secret-token', ['sessionId' => 'ATVId_in', 'isActive' => '1', 'direction' => 'Inbound', 'callerNumber' => '+2348035556666'])
            ->assertOk()
            ->assertSee('UbasaniCC.staff'.$admin->id, false)
            ->assertSee('UbasaniCC.staff'.$coordinator->id, false)
            ->assertSee('+2348030000001', false)
            ->assertSee('sequential="false"', false);

        // Answered on the coordinator's computer: credited to them.
        $this->post('/api/webhooks/voice/secret-token', ['sessionId' => 'ATVId_in', 'isActive' => '0', 'dialDurationInSeconds' => '30', 'dialDestinationNumber' => 'UbasaniCC.staff'.$coordinator->id]);
        $this->assertDatabaseHas('voice_calls', ['session_id' => 'ATVId_in', 'status' => 'completed', 'operator_id' => $coordinator->id]);

        // Browsers that stop checking in stop ringing.
        $this->travel(2)->minutes();
        $this->assertSame([], $voice->onlineStaffIds());
    }
}
