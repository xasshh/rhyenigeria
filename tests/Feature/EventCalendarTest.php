<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MemberProfile;
use App\Models\State;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Everyone gets a month calendar of past and upcoming events they are allowed to see. */
class EventCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $title, CarbonImmutable $at, ?State $state = null): Event
    {
        $event = new Event(['title' => $title, 'starts_at' => $at->utc(), 'venue' => 'Town hall']);
        $event->state_id = $state?->id;
        $event->save();

        return $event;
    }

    public function test_members_see_past_and_upcoming_national_and_own_state_events_for_the_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00', 'Africa/Lagos'));
        $state = State::factory()->create();
        $member = User::factory()->member($state)->create();
        MemberProfile::factory()->for($member)->create();

        $this->event('Past National Rally', CarbonImmutable::parse('2026-11-03 10:00', 'Africa/Lagos'));
        $this->event('Upcoming State Meetup', CarbonImmutable::parse('2026-11-28 15:00', 'Africa/Lagos'), $state);
        $this->event('Other State Meetup', CarbonImmutable::parse('2026-11-20 15:00', 'Africa/Lagos'), State::factory()->create());
        $this->event('December Rally', CarbonImmutable::parse('2026-12-05 10:00', 'Africa/Lagos'));

        $this->actingAs($member)->get('/membership/events')->assertOk()
            ->assertSee('November 2026')
            ->assertSee('Past National Rally')->assertSee('Past')
            ->assertSee('Upcoming State Meetup')
            ->assertDontSee('Other State Meetup');

        $this->get('/membership/events?month=2026-12')->assertOk()
            ->assertSee('December 2026')
            ->assertViewHas('calendar', fn ($c) => $c->monthEvents->pluck('title')->all() === ['December Rally']);

        // Nonsense falls back to this month.
        $this->get('/membership/events?month=nope')->assertOk()->assertSee('November 2026');
    }

    public function test_an_event_late_in_the_evening_lands_on_its_nigerian_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00', 'Africa/Lagos'));
        $member = User::factory()->member(State::factory()->create())->create();
        MemberProfile::factory()->for($member)->create();
        // 23:30 Lagos on the 30th is 22:30 UTC: still the 30th, and still November.
        $this->event('Late Vigil', CarbonImmutable::parse('2026-11-30 23:30', 'Africa/Lagos'));

        $this->actingAs($member)->get('/membership/events')
            ->assertViewHas('calendar', function ($c) {
                $cell = collect($c->weeks)->flatten(1)->firstWhere('date', '2026-11-30');

                return $cell['events']->pluck('title')->all() === ['Late Vigil'] && $c->monthEvents->count() === 1;
            });
    }

    public function test_staff_calendars_follow_the_viewers_scope(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00', 'Africa/Lagos'));
        $state = State::factory()->create();
        $other = State::factory()->create();
        $this->event('National Day', CarbonImmutable::parse('2026-11-18 10:00', 'Africa/Lagos'));
        $this->event('Home Event', CarbonImmutable::parse('2026-11-19 10:00', 'Africa/Lagos'), $state);
        $this->event('Away Event', CarbonImmutable::parse('2026-11-20 10:00', 'Africa/Lagos'), $other);

        $this->actingAs(User::factory()->coordinator($state)->create())->get('/coordinator/calendar')->assertOk()
            ->assertSee('National Day')->assertSee('Home Event')->assertDontSee('Away Event');

        $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/calendar')->assertOk()
            ->assertSee('Home Event')->assertSee('Away Event');
        $this->get('/admin/calendar?state_id='.$other->id)
            ->assertSee('Away Event')->assertSee('National Day')->assertDontSee('Home Event');
    }
}
