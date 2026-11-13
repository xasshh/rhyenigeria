<?php

namespace Tests\Feature;

use App\Models\HeroSlide;
use App\Models\MemberProfile;
use App\Models\Setting;
use App\Models\State;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_slider_shows_active_slides_in_order_with_the_new_slogan(): void
    {
        HeroSlide::query()->delete();
        HeroSlide::factory()->create(['position' => 2, 'headline' => 'Second slide']);
        HeroSlide::factory()->create(['position' => 1, 'headline' => 'First slide', 'button_label' => 'See the event', 'button_url' => '/join?state=PL']);
        HeroSlide::factory()->hidden()->create(['position' => 3, 'headline' => 'Hidden slide']);

        $this->get('/')->assertOk()
            ->assertSee('Hope Renewed, Nation Rebuilt.')
            ->assertDontSee('Renewed Hope Nation Rebuilt')
            ->assertSeeInOrder(['First slide', 'See the event', 'Second slide'])
            ->assertDontSee('Hidden slide')
            ->assertSee('aria-roledescription="carousel"', false);
    }

    public function test_the_slides_run_event_president_patron_minister_convener(): void
    {
        $slides = HeroSlide::query()->shown()->get();

        $this->assertCount(5, $slides);
        $this->assertSame(['image', 'classic', 'portrait', 'portrait', 'portrait'], $slides->pluck('layout')->all());
        $this->assertSame(['images/slides/slide-1.webp', 'images/hero-portrait.webp', 'images/slides/seyi-tinubu.webp', 'images/slides/ayodele-olawande.webp', 'images/slides/abdulazeez-kaka.webp'], $slides->pluck('image_path')->all());
        foreach ($slides->pluck('image_path')->filter() as $path) {
            $this->assertFileExists(public_path($path));
        }

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Education Empowerment Initiative', 'Youth for PBAT', '5,000+ youth empowered', 'Bar. Seyi Tinubu', 'Comrade Ayodele Olawande', 'National Convener', 'Abdulazeez Kaka'])
            // Backgrounds alternate white, green, white… by position.
            ->assertSeeInOrder(['bg-white text-forest', 'bg-action text-white', 'bg-white text-forest', 'bg-action text-white', 'bg-white text-forest'], false);
    }

    public function test_the_top_bar_shows_a_live_countdown(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-01 12:00:00', 'Africa/Lagos'));

        $this->get('/')->assertOk()->assertSeeInOrder(['Election Day in', '2', 'w', '0', 'd', '12:00:00', 'Support Us']);
    }

    public function test_the_countdown_is_rendered_on_the_server_and_defaults_to_16_january_2027(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-01 12:00:00', 'Africa/Lagos'));

        // 14 days 12 hours left: 2 weeks, 0 days, 12 hours.
        $this->get('/')->assertOk()
            ->assertSee('Countdown to Election Day')
            ->assertSee('Saturday 16 January 2027')
            ->assertSee('Presidential and National Assembly election')
            ->assertViewHas('countdown', fn (array $c) => $c['weeks'] === 2 && $c['days'] === 0 && $c['hours'] === 12 && ! $c['done']);
    }

    public function test_on_election_day_the_countdown_becomes_a_call_to_vote(): void
    {
        Setting::put(Setting::ELECTION_DATE, CarbonImmutable::parse('2027-01-16 08:00', 'Africa/Lagos')->utc()->toIso8601String());
        $this->travelTo(CarbonImmutable::parse('2027-01-16 09:00', 'Africa/Lagos'));

        $this->get('/')->assertOk()
            ->assertSee('Today is Election Day: go and vote.')
            ->assertViewHas('countdown', fn (array $c) => $c['done'] && $c['seconds'] === 0);
    }

    public function test_the_state_map_endpoint_returns_aggregate_counts_only_and_is_cached(): void
    {
        $geo = $this->geography();
        $member = User::factory()->member($geo['wardA'])->create(['name' => 'Secret Person', 'email' => 'secret@example.com']);
        MemberProfile::factory()->for($member)->create();
        User::factory()->coordinator($geo['state'])->create();

        $response = $this->getJson('/map/states.json')->assertOk();
        $this->assertSame(1, $response->json('counts.'.$geo['state']->code));
        $this->assertSame(0, $response->json('counts.'.$geo['otherState']->code));
        $this->assertSame(1, $response->json('total'));
        $this->assertStringNotContainsString('Secret', $response->getContent());
        $this->assertStringNotContainsString('secret@example.com', $response->getContent());
        $this->assertStringContainsString('max-age=300', $response->headers->get('Cache-Control'));

        // Cached as plain arrays: the production cache store refuses to unserialize objects.
        $this->assertIsArray(Cache::get('map:state-counts')['counts']);

        // Served from the cache for a few minutes.
        $second = User::factory()->member($geo['wardA'])->create();
        MemberProfile::factory()->for($second)->create();
        $this->assertSame(1, $this->getJson('/map/states.json')->json('total'));
        Cache::forget('map:state-counts');
        $this->assertSame(2, $this->getJson('/map/states.json')->json('total'));
    }

    public function test_clicking_a_state_preselects_it_on_join(): void
    {
        $state = State::factory()->create(['code' => 'PL', 'name' => 'Plateau']);

        $this->get('/join?state=pl')->assertOk()->assertSee('?as=member&amp;state=PL', false);
        $this->get('/join?as=member&state=PL')->assertOk()->assertViewHas('preselectedStateId', $state->id);
        $this->get('/join?as=member&state=ZZ')->assertOk()->assertViewHas('preselectedStateId', null);
    }

    public function test_the_empowerment_button_sends_guests_to_sign_in_and_then_to_empowerment(): void
    {
        $this->get('/')->assertSee(route('empowerment'));

        $member = User::factory()->member()->create(['email' => 'ada@example.com', 'password' => 'a-good-password']);
        MemberProfile::factory()->for($member)->create();

        $this->get('/empowerment')->assertRedirect(route('login'));
        $this->get('/login')->assertSee('Sign in to open Empowerment')->assertSee('Not a member yet?')->assertSee(route('join'));
        $this->post('/login', ['email' => 'ada@example.com', 'password' => 'a-good-password'])->assertRedirect(route('empowerment'));
        $this->get('/empowerment')->assertRedirect(route('membership.empowerment'));
        $this->get('/membership/empowerment')->assertOk();
    }

    public function test_signed_in_users_go_straight_to_their_own_empowerment_page(): void
    {
        $member = User::factory()->member()->create();
        MemberProfile::factory()->for($member)->create();
        $state = State::factory()->create();

        $this->actingAs($member)->get('/empowerment')->assertRedirect(route('membership.empowerment'));
        $this->actingAs(User::factory()->coordinator($state)->create())->get('/empowerment')->assertRedirect(route('coordinator.empowerment.index'));
        $this->actingAs(User::factory()->superAdmin()->create())->get('/empowerment')->assertRedirect(route('admin.empowerment.index'));
    }

    public function test_a_coordinator_signing_in_from_a_member_link_lands_on_their_dashboard(): void
    {
        $coordinator = User::factory()->coordinator(State::factory()->create())->create(['password' => 'a-good-password']);

        $this->get('/membership/empowerment')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $coordinator->email, 'password' => 'a-good-password'])->assertRedirect(route('coordinator.dashboard'));
    }

    public function test_super_admins_manage_slides_and_uploads_get_a_webp_copy(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post('/admin/slides', [
            'position' => 9, 'is_active' => '1', 'layout' => 'image', 'headline' => 'New rally', 'body' => 'Come along',
            'button_label' => 'Join', 'button_url' => '/join', 'image' => UploadedFile::fake()->image('flyer.jpg', 2000, 2500),
        ])->assertRedirect(route('admin.slides.index'));

        $slide = HeroSlide::where('headline', 'New rally')->sole();
        Storage::disk('public')->assertExists([$slide->image_path, $slide->image_original_path]);
        $this->assertStringEndsWith('.webp', $slide->image_path);
        $this->assertSame(1600, getimagesizefromstring(Storage::disk('public')->get($slide->image_path))[0]);

        $this->patch('/admin/slides/'.$slide->id.'/toggle')->assertRedirect();
        $this->assertFalse($slide->fresh()->is_active);

        $this->post('/admin/slides', ['position' => 1, 'layout' => 'image', 'headline' => 'Bad link', 'button_label' => 'Go', 'button_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('button_url');

        $this->delete('/admin/slides/'.$slide->id)->assertRedirect();
        Storage::disk('public')->assertMissing($slide->image_path);
    }

    public function test_coordinators_and_members_cannot_manage_slides(): void
    {
        $slide = HeroSlide::query()->first();
        $member = User::factory()->member()->create();

        $this->actingAs(User::factory()->coordinator(State::factory()->create())->create())->get('/admin/slides')->assertForbidden();
        $this->actingAs($member)->put('/admin/slides/'.$slide->id, ['position' => 1, 'headline' => 'Hacked'])->assertForbidden();
        $this->assertNotSame('Hacked', $slide->fresh()->headline);
    }

    public function test_the_super_admin_dashboard_has_a_state_map_linking_to_each_state(): void
    {
        $state = State::factory()->create(['code' => 'PL', 'name' => 'Plateau']);
        DB::update('UPDATE states SET boundary = ST_Multi(ST_MakeEnvelope(8.5, 8.5, 10, 10, 4326)) WHERE id = ?', [$state->id]);

        $this->actingAs(User::factory()->superAdmin()->create())->get('/admin')->assertOk()
            ->assertSee('Members across Nigeria')
            ->assertSee('data-area="PL"', false)
            ->assertSee('href="'.route('admin.states.show', $state->id).'"', false);
    }

    public function test_the_homepage_links_to_the_official_social_accounts(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Follow us')
            ->assertSee('https://x.com/rhyengagement', false)
            ->assertSee('https://www.tiktok.com/@renewedhopeyouthe', false)
            ->assertSee('aria-label="Instagram"', false)
            ->assertSee('aria-label="Facebook"', false);
    }
}
