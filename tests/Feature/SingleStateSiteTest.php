<?php

namespace Tests\Feature;

use App\Models\HeroSlide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The same code running as ubasani2027.com (SITE_BRAND=us27): Kaduna only, polling unit agents. */
class SingleStateSiteTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function kaduna(): array
    {
        $_ENV['SITE_BRAND'] = $_SERVER['SITE_BRAND'] = 'us27';
        config(['site' => require config_path('site.php')]);
        unset($_ENV['SITE_BRAND'], $_SERVER['SITE_BRAND']);

        $geo = $this->geography();
        $geo['state']->update(['code' => 'KD', 'name' => 'Kaduna']);

        return $geo;
    }

    /** @return array<string, mixed> */
    private function answers(array $geo, array $overrides = []): array
    {
        return array_merge([
            'as' => 'canvasser', 'email' => 'ada@example.com', 'phone' => '0803 123 4567', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
            'name' => 'Ada Obi', 'gender' => 'female', 'age_range' => '25-34', 'state_id' => $geo['state']->id, 'lga_id' => $geo['lga']->id,
            'ward_id' => $geo['wardA']->id, 'has_pvc' => 1, 'consent' => 1,
        ], $overrides);
    }

    public function test_the_site_is_branded_and_about_one_state(): void
    {
        $this->kaduna();
        $this->artisan('site:setup', ['--force' => true])->assertSuccessful();

        $this->get('/')->assertOk()
            ->assertSee('Tinubu &amp; Uba Sani 2027', false)->assertSee('Tinubu, Uba Sau Biyu ne!')
            ->assertSee('The movement across Kaduna State')->assertDontSee('Renewed Hope Youth Engagement')
            ->assertDontSee('State coordinators');

        // Members only: asking to join as an agent shows the member form.
        $this->get('/join?as=canvasser')->assertOk()->assertSee('Join as a Member')->assertDontSee('Join as a Polling unit agent');
        $this->getJson('/map/lgas.json')->assertOk()->assertJsonStructure(['counts', 'total']);
    }

    public function test_sign_ups_are_members_from_this_state_only(): void
    {
        $geo = $this->kaduna();

        $this->postJson('/join', $this->answers($geo, ['state_id' => $geo['otherState']->id, 'lga_id' => $geo['farWard']->lga_id, 'ward_id' => $geo['farWard']->id]))
            ->assertJsonValidationErrors('state_id');

        $this->postJson('/join', $this->answers($geo))->assertCreated();

        $profile = User::where('email', 'ada@example.com')->firstOrFail()->memberProfile;
        // Even a request to join as an agent makes a plain member; agents are added by coordinators.
        $this->assertNull($profile->canvasser_status);
        $this->assertStringStartsWith('US27-KD-', $profile->member_number);
        $this->assertSame('Member', $profile->roleLabel());
    }

    public function test_the_admin_overview_is_the_state_page(): void
    {
        $geo = $this->kaduna();

        $this->actingAs(User::factory()->superAdmin()->create())->get('/admin')
            ->assertRedirect(route('admin.states.show', $geo['state']));
    }

    public function test_the_starting_slides_replace_rhyes(): void
    {
        $this->kaduna();

        $this->artisan('site:setup', ['--force' => true])->assertSuccessful();

        $this->assertSame(['Tinubu, Uba Sau Biyu ne!', 'Senator Uba Sani', 'Abdulazeez Kaka'], HeroSlide::orderBy('position')->pluck('headline')->all());
    }
}
