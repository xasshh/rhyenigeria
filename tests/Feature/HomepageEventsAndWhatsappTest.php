<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MemberProfile;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every new upcoming public event becomes a homepage slide (its flyer, or a date card). The WhatsApp groups
 * page shows each state's coordinator to anyone, but the invite link only to signed-in members of that state.
 */
class HomepageEventsAndWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private const GROUP = 'https://chat.whatsapp.com/LagosGroupInvite123';

    public function test_a_posted_event_appears_on_the_homepage_and_its_flyer_leads_the_slider(): void
    {
        Storage::fake('public');
        $state = State::factory()->create(['name' => 'Plateau', 'code' => 'PL']);
        $coordinator = User::factory()->coordinator($state)->create();

        $this->actingAs($coordinator)->post('/coordinator/posts/events', [
            'title' => 'Jos Youth Townhall',
            'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'venue' => 'Tamarald Event Centre',
            'show_on_homepage' => '1',
            'flyer' => UploadedFile::fake()->image('flyer.jpg', 1080, 1350),
        ])->assertRedirect();
        $this->actingAs($coordinator)->post('/coordinator/posts/events', [
            'title' => 'Private Planning Meeting',
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'show_on_homepage' => '0',
        ]);

        $event = Event::where('title', 'Jos Youth Townhall')->firstOrFail();
        $this->assertTrue($event->is_public);
        Storage::disk('public')->assertExists($event->flyer_path);

        auth()->logout();
        $this->get('/')->assertOk()
            ->assertViewHas('slides', fn ($slides) => $slides->first()->headline === 'Jos Youth Townhall')
            ->assertDontSee('Upcoming events')
            ->assertSee('Tamarald Event Centre')
            ->assertSee(route('join', ['state' => 'PL']), false)
            ->assertDontSee('Private Planning Meeting');
    }

    public function test_an_event_without_a_flyer_still_gets_a_slide_with_a_date_card(): void
    {
        $state = State::factory()->create(['name' => 'Benue', 'code' => 'BE']);
        $event = new Event(['title' => 'Makurdi Rally', 'starts_at' => now()->addDays(10), 'venue' => 'Aper Aku Stadium']);
        $event->state()->associate($state)->save();

        $this->get('/')->assertOk()
            ->assertViewHas('slides', fn ($slides) => $slides->first()->headline === 'Makurdi Rally' && $slides->first()->eventStartsAt !== null)
            ->assertSee('Register to attend')
            ->assertSee($event->starts_at->setTimezone(config('membership.timezone'))->format('g:ia').' · Aper Aku Stadium')
            ->assertDontSee('Image coming soon');
    }

    public function test_past_events_leave_the_homepage(): void
    {
        $event = new Event(['title' => 'Last Month Rally', 'starts_at' => now()->subDay()]);
        $event->save();

        $this->get('/')->assertOk()->assertDontSee('Last Month Rally');
    }

    public function test_guests_see_the_coordinator_but_never_the_group_link(): void
    {
        State::factory()->create(['name' => 'Lagos', 'code' => 'LA', 'coordinator_name' => 'Ada Coordinator', 'whatsapp_group_url' => self::GROUP]);

        $this->get('/whatsapp?state=LA')->assertOk()
            ->assertSee('Ada Coordinator')
            ->assertSee('join?state=LA', false)
            ->assertDontSee(self::GROUP)
            ->assertDontSee('LagosGroupInvite123');
        $this->get('/')->assertSee(route('whatsapp'), false);
    }

    public function test_a_member_gets_their_own_states_link_on_the_dashboard_and_the_page(): void
    {
        $lagos = State::factory()->create(['name' => 'Lagos', 'code' => 'LA', 'whatsapp_group_url' => self::GROUP]);
        State::factory()->create(['code' => 'KN', 'whatsapp_group_url' => 'https://chat.whatsapp.com/KanoOnly']);
        $member = User::factory()->member($lagos)->create();
        MemberProfile::factory()->for($member)->create();

        $this->actingAs($member)->get('/membership')->assertOk()->assertSee(self::GROUP)->assertDontSee('KanoOnly');
        $this->get('/whatsapp?state=KN')->assertOk()->assertSee(self::GROUP)->assertDontSee('KanoOnly');
    }

    public function test_a_super_admin_sets_a_coordinators_name_and_photo(): void
    {
        Storage::fake('public');
        $state = State::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())->put('/admin/settings', [
            'coordinator_name' => [$state->id => 'Bola Coordinator'],
            'coordinator_photo' => [$state->id => UploadedFile::fake()->image('bola.jpg', 800, 800)],
            'whatsapp' => [$state->id => self::GROUP],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $state->refresh();
        $this->assertSame('Bola Coordinator', $state->coordinator_name);
        $this->assertSame(self::GROUP, $state->whatsapp_group_url);
        Storage::disk('public')->assertExists($state->coordinator_photo_path);
        $this->assertCount(1, Storage::disk('public')->allFiles('coordinators'));

        $this->actingAs(User::factory()->superAdmin()->create())->put('/admin/settings', ['remove_photo' => [$state->id => '1']]);
        $this->assertNull($state->fresh()->coordinator_photo_path);
        $this->assertSame('Bola Coordinator', $state->fresh()->coordinator_name);
    }
}
