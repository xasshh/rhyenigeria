<?php

namespace Tests\Feature;

use App\Models\MemberProfile;
use App\Models\User;
use App\Services\Security\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SignupBotDefencesTest extends TestCase
{
    use RefreshDatabase;

    private function answers(array $geo, array $overrides = []): array
    {
        return array_merge([
            'as' => 'member', 'email' => 'ada.obi@example.com', 'phone' => '0803 123 4567',
            'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
            'name' => 'Ada Obi', 'gender' => 'female', 'age_range' => '25-34',
            'state_id' => $geo['state']->id, 'lga_id' => $geo['lga']->id, 'ward_id' => $geo['wardA']->id,
            'has_pvc' => 1, 'consent' => 1,
        ], $overrides);
    }

    private function enableTurnstile(): void
    {
        config(['services.turnstile.site_key' => '0xTESTKEY', 'services.turnstile.secret' => '0xTESTSECRET']);
    }

    public function test_without_keys_the_check_is_skipped_so_signups_still_work(): void
    {
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret' => null]);
        $this->assertFalse(app(Turnstile::class)->enabled());

        $geo = $this->geography();
        Http::fake(); // no siteverify call should be made
        $this->postJson('/join', $this->answers($geo))->assertCreated();
        Http::assertNothingSent();
    }

    public function test_with_keys_a_signup_without_a_valid_token_is_rejected(): void
    {
        $this->enableTurnstile();
        $geo = $this->geography();

        // No token at all.
        $this->postJson('/join', $this->answers($geo))->assertJsonValidationErrors('cf-turnstile-response');
        $this->assertDatabaseCount('users', 0);

        // A token Cloudflare rejects.
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
        $this->postJson('/join', $this->answers($geo, ['cf-turnstile-response' => 'bad-token']))->assertJsonValidationErrors('cf-turnstile-response');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_with_keys_a_human_solved_token_lets_the_signup_through(): void
    {
        $this->enableTurnstile();
        $geo = $this->geography();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->postJson('/join', $this->answers($geo, ['cf-turnstile-response' => 'good-token']))->assertCreated();
        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), 'siteverify') && $r['response'] === 'good-token');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_one_referral_code_cannot_bring_in_more_than_the_hourly_cap(): void
    {
        config(['membership.signups_per_referrer_per_hour' => 2]);
        $geo = $this->geography();
        $referrer = User::factory()->member($geo['wardA'])->create();
        $code = MemberProfile::factory()->for($referrer)->create()->referral_code;

        foreach (['a@example.com', 'b@example.com'] as $i => $email) {
            $this->postJson('/join', $this->answers($geo, ['email' => $email, 'phone' => '080312345'.(10 + $i), 'referrer' => $code]))->assertCreated();
            auth()->logout(); // each real sign-up is a fresh session
        }

        // The third via the same code is blocked.
        $this->postJson('/join', $this->answers($geo, ['email' => 'c@example.com', 'phone' => '08031234512', 'referrer' => $code]))
            ->assertJsonValidationErrors('referrer');
        $this->assertDatabaseCount('users', 3); // referrer + 2 signups, not the 3rd
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('referral-signup:');
        parent::tearDown();
    }
}
