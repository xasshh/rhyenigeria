<?php

namespace Tests\Feature;

use App\Enums\CanvasserStatus;
use App\Enums\Role;
use App\Models\Lga;
use App\Models\MemberProfile;
use App\Models\State;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MemberRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function answers(array $geo, array $overrides = []): array
    {
        return array_merge([
            'as' => 'member',
            'email' => 'Ada.Obi@Example.com',
            'phone' => '0803 123 4567',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'name' => 'Ada Obi',
            'gender' => 'female',
            'age_range' => '25-34',
            'occupation' => 'Trader',
            'state_id' => $geo['state']->id,
            'lga_id' => $geo['lga']->id,
            'ward_id' => $geo['wardA']->id,
            'polling_unit_id' => $geo['unitA']->id,
            'has_pvc' => 1,
            'vin' => '90F5 B123 4567 8901 234',
            'nin' => '12345678901',
            'consent' => 1,
        ], $overrides);
    }

    public function test_a_member_can_register_and_is_signed_in_as_a_member(): void
    {
        $geo = $this->geography();

        $this->postJson('/join', $this->answers($geo))
            ->assertCreated()
            ->assertJson(['redirect' => route('membership.welcome')]);

        $user = User::where('email', 'ada.obi@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(Role::Member, $user->role);
        $this->assertSame('+2348031234567', $user->phone);
        $this->assertSame($geo['wardA']->id, $user->ward_id);
        $this->assertSame($geo['state']->id, $user->state_id);

        $profile = $user->memberProfile;
        $this->assertMatchesRegularExpression('/^RHYE-'.preg_quote(strtoupper($geo['state']->code), '/').'-\d{6}$/', $profile->member_number);
        $this->assertSame(40, strlen($profile->verify_token));
        $this->assertNull($profile->canvasser_status);
        $this->assertNotNull($profile->consent_accepted_at);

        $this->get('/membership/welcome')->assertOk()->assertSee('Welcome to the movement, Ada!')->assertSee($profile->member_number);
    }

    public function test_a_member_can_sign_in_and_is_sent_to_their_dashboard(): void
    {
        $user = User::factory()->member()->create(['email' => 'ada@example.com', 'password' => 'secret-pass']);
        MemberProfile::factory()->for($user)->create();

        $this->post('/login', ['email' => 'ADA@example.com', 'password' => 'secret-pass'])->assertRedirect(route('membership.home'));
        $this->get('/membership')->assertOk();

        foreach (['/membership/card', '/membership/events', '/membership/empowerment', '/membership/campaign-dp'] as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_public_signups_never_get_field_agent_or_coordinator_rights_even_if_they_ask(): void
    {
        $geo = $this->geography();

        $this->postJson('/join', $this->answers($geo, ['role' => 'coordinator', 'is_active' => 0, 'state_id' => $geo['state']->id]))->assertCreated();

        $user = User::where('email', 'ada.obi@example.com')->first();
        $this->assertSame(Role::Member, $user->role);
        $this->assertTrue($user->is_active);
        $this->get('/coordinator')->assertForbidden();
    }

    public function test_a_canvasser_applicant_is_created_as_a_pending_member(): void
    {
        $geo = $this->geography();

        $this->postJson('/join', $this->answers($geo, ['as' => 'canvasser', 'canvasser_roles' => ['door_to_door', 'social_media']]))->assertCreated();

        $profile = User::where('email', 'ada.obi@example.com')->first()->memberProfile;
        $this->assertSame(CanvasserStatus::Pending, $profile->canvasser_status);
        $this->assertSame(['door_to_door', 'social_media'], $profile->canvasser_roles);
        $this->assertSame('+2348031234567', $profile->whatsapp_phone);
    }

    public function test_canvassers_must_say_how_they_will_help(): void
    {
        $geo = $this->geography();

        $this->postJson('/join', $this->answers($geo, ['as' => 'canvasser']))->assertJsonValidationErrors('canvasser_roles');
    }

    public function test_each_step_is_validated_on_the_server(): void
    {
        $geo = $this->geography();
        User::factory()->member()->create(['phone' => '+2348031234567']);

        $this->postJson('/join/check/account', ['email' => 'not-an-email', 'phone' => '0803 123 4567', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertJsonValidationErrors(['email', 'phone', 'password']);
        $this->postJson('/join/check/account', ['email' => 'new@example.com', 'phone' => '+234 802 000 0000', 'password' => 'long-enough', 'password_confirmation' => 'long-enough'])
            ->assertOk();
        $this->postJson('/join/check/details', ['name' => 'Ada', 'gender' => 'other', 'age_range' => '17'])
            ->assertJsonValidationErrors(['name', 'gender', 'age_range']);
        // An LGA from another state is refused.
        $this->postJson('/join/check/location', ['state_id' => $geo['otherState']->id, 'lga_id' => $geo['lga']->id, 'has_pvc' => 0])
            ->assertJsonValidationErrors('lga_id');
        $this->postJson('/join/check/finish', ['referrer' => 'RHYE-XX-000000'])
            ->assertJsonValidationErrors(['referrer', 'consent']);
    }

    public function test_the_ward_is_optional_when_an_lga_has_no_wards_imported(): void
    {
        $state = State::factory()->create();
        $lga = Lga::factory()->for($state)->create();

        $this->postJson('/join/check/location', ['state_id' => $state->id, 'lga_id' => $lga->id, 'has_pvc' => 0])->assertOk();

        Ward::factory()->for($lga)->create();
        $this->postJson('/join/check/location', ['state_id' => $state->id, 'lga_id' => $lga->id, 'has_pvc' => 0])->assertJsonValidationErrors('ward_id');
    }

    public function test_the_honeypot_rejects_bots(): void
    {
        $geo = $this->geography();

        $this->postJson('/join', $this->answers($geo, ['website' => 'http://spam.example']))->assertJsonValidationErrors('website');
        $this->assertDatabaseCount('member_profiles', 0);
    }

    public function test_sign_up_is_rate_limited_per_ip(): void
    {
        config(['membership.join_rate_limit_per_minute' => 2]);

        $this->postJson('/join', ['as' => 'member'])->assertUnprocessable();
        $this->postJson('/join', ['as' => 'member'])->assertUnprocessable();
        $this->postJson('/join', ['as' => 'member'])->assertTooManyRequests();
    }

    public function test_the_referrer_is_stored(): void
    {
        $geo = $this->geography();
        $inviter = MemberProfile::factory()->create(['referral_code' => 'RHYE-LA-123456', 'member_number' => 'RHYE-LA-123456']);

        $this->get('/join?ref=RHYE-LA-123456&as=member')->assertOk()->assertSee('RHYE-LA-123456');
        $this->postJson('/join', $this->answers($geo, ['referrer' => 'rhye-la-123456']))->assertCreated();

        $this->assertSame($inviter->user_id, User::where('email', 'ada.obi@example.com')->first()->memberProfile->referred_by_user_id);
    }

    public function test_nin_and_vin_are_encrypted_at_rest(): void
    {
        $geo = $this->geography();
        $this->postJson('/join', $this->answers($geo))->assertCreated();

        $raw = DB::table('member_profiles')->first();
        $this->assertStringNotContainsString('12345678901', $raw->nin);
        $this->assertStringNotContainsString('90F5B', $raw->vin);

        $profile = MemberProfile::first();
        $this->assertSame('12345678901', $profile->nin);
        $this->assertSame('90F5B12345678901234', $profile->vin);
    }

    public function test_the_verify_page_shows_no_private_fields(): void
    {
        $geo = $this->geography();
        $this->postJson('/join', $this->answers($geo, ['name' => 'Ada Chioma Obi']))->assertCreated();
        $profile = MemberProfile::first();
        auth()->logout();

        $this->get('/verify/'.$profile->verify_token)
            ->assertOk()
            ->assertSee('Verified member')
            ->assertSee('Ada O.')
            ->assertSee($geo['state']->name)
            ->assertDontSee('Chioma')
            ->assertDontSee('ada.obi@example.com')
            ->assertDontSee('8031234567')
            ->assertDontSee('12345678901')
            ->assertDontSee('90F5B')
            ->assertDontSee($profile->member_number);

        // The member number is not a valid token.
        $this->get('/verify/'.$profile->member_number)->assertOk()->assertSee('Not a valid membership card');
    }

    public function test_a_member_can_reset_a_forgotten_password_by_email(): void
    {
        Notification::fake();
        $user = User::factory()->member()->create(['email' => 'ada@example.com']);
        MemberProfile::factory()->for($user)->create();

        $this->post('/forgot-password', ['email' => 'ada@example.com'])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->get('/reset-password/'.$notification->token.'?email='.$user->email)->assertOk();
            $this->post('/reset-password', ['token' => $notification->token, 'email' => $user->email, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
                ->assertRedirect(route('login'));

            return true;
        });

        $this->post('/login', ['email' => 'ada@example.com', 'password' => 'brand-new-pass'])->assertRedirect(route('membership.home'));
    }

    public function test_field_agents_cannot_sign_in_on_the_website(): void
    {
        $agent = User::factory()->agent()->create(['password' => 'agent-password']);

        $this->post('/login', ['email' => $agent->email, 'password' => 'agent-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_landing_buttons_lead_to_the_join_chooser(): void
    {
        $this->get('/')->assertOk()->assertSee(route('join'))->assertSee('Sign in');
        $this->get('/join')->assertOk()->assertSee('Join as a Member')->assertSee('Join as a Canvasser');
        $this->get('/join?as=canvasser')->assertOk()->assertSee('How would you like to help?');
    }
}
