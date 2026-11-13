<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Lga;
use App\Models\MemberProfile;
use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A state coordinator sees their own state and nothing else: not in lists,
 * detail pages, counts, maps, exports, posts, or by putting another state's
 * id in the URL. Members see their own state's news plus national news.
 */
class CoordinatorStateIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function world(): array
    {
        $geo = $this->geography();
        $farLga = Lga::findOrFail($geo['farWard']->lga_id);

        // Boundaries so both states' LGAs can be drawn on a map.
        foreach ([[$geo['lga'], 3.3, 6.5], [$geo['otherLga'], 3.5, 6.5], [$farLga, 7.4, 9.0]] as [$lga, $x, $y]) {
            DB::update('UPDATE lgas SET boundary = ST_Multi(ST_MakeEnvelope(?, ?, ?, ?, 4326)) WHERE id = ?', [$x, $y, $x + 0.2, $y + 0.2, $lga->id]);
        }
        $farLga->update(['name' => 'Faraway LGA']);

        $mine = User::factory()->member($geo['wardA'])->create(['name' => 'Mine Member']);
        MemberProfile::factory()->for($mine)->create();
        $theirs = User::factory()->member($geo['farWard'])->create(['name' => 'Far Away Member']);
        MemberProfile::factory()->for($theirs)->create();
        $theirs2 = User::factory()->member($geo['farWard'])->create(['name' => 'Second Far Member']);
        MemberProfile::factory()->for($theirs2)->create();

        $coordinator = User::factory()->coordinator($geo['state'])->create();

        return $geo + compact('farLga', 'mine', 'theirs', 'coordinator');
    }

    private function makePost(string $class, ?int $stateId, string $title, int $authorId): Event|NewsPost
    {
        $post = $class === Event::class
            ? new Event(['title' => $title, 'starts_at' => now()->addWeek(), 'venue' => 'Town hall'])
            : new NewsPost(['title' => $title, 'body' => 'Body', 'published_at' => now()->subMinute()]);
        $post->state_id = $stateId;
        $post->created_by = $authorId;
        $post->save();

        return $post;
    }

    public function test_the_lga_map_only_counts_the_coordinators_own_state(): void
    {
        $w = $this->world();
        $this->actingAs($w['coordinator']);

        $counts = $this->getJson('/coordinator/map/lgas')->assertOk()->json();
        $this->assertSame([(string) $w['lga']->id => 1], $counts['counts']);
        $this->assertSame(1, $counts['total']);

        // Asking for another state changes nothing.
        $this->assertSame($counts, $this->getJson('/coordinator/map/lgas?state_id='.$w['otherState']->id)->json());

        // The dashboard draws only their own LGAs.
        $this->get('/coordinator')->assertOk()
            ->assertSee('data-name="'.$w['lga']->name.'"', false)
            ->assertDontSee('Faraway LGA')
            ->assertSee(route('coordinator.members.index', ['lga_id' => $w['lga']->id]), false);
    }

    public function test_a_coordinator_cannot_reach_another_state_through_the_url(): void
    {
        $w = $this->world();
        $this->actingAs($w['coordinator']);

        $this->get('/admin/states/'.$w['otherState']->id)->assertForbidden();
        $this->get('/admin/map/lgas?state_id='.$w['otherState']->id)->assertForbidden();
        $this->get('/coordinator/members?lga_id='.$w['farLga']->id)->assertOk()->assertDontSee('Far Away Member');
        $this->get('/coordinator/members?ward_id='.$w['farWard']->id)->assertOk()->assertDontSee('Far Away Member');
        $this->get('/coordinator/members/'.$w['theirs']->id)->assertNotFound();

        // Asking for another state narrows the export to nothing rather than widening it.
        $csv = $this->get('/coordinator/members/export?state_id='.$w['otherState']->id)->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Far Away Member', $csv);
        $this->assertStringNotContainsString('Mine Member', $csv);

        $csv = $this->get('/coordinator/members/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('Mine Member', $csv);
        $this->assertStringNotContainsString('Far Away Member', $csv);
    }

    public function test_dashboard_counts_never_include_another_state(): void
    {
        $w = $this->world();

        $this->actingAs($w['coordinator'])->get('/coordinator')->assertOk()
            ->assertViewHas('summary', fn (array $summary) => $summary['total'] === 1);
    }

    public function test_a_coordinator_can_edit_their_states_posts_but_not_another_states(): void
    {
        $w = $this->world();
        $admin = User::factory()->superAdmin()->create();
        $own = $this->makePost(Event::class, $w['state']->id, 'Our rally', $w['coordinator']->id);
        $foreign = $this->makePost(Event::class, $w['otherState']->id, 'Their rally', $admin->id);
        $national = $this->makePost(NewsPost::class, null, 'National update', $admin->id);
        $foreignNews = $this->makePost(NewsPost::class, $w['otherState']->id, 'Their news', $admin->id);
        $this->actingAs($w['coordinator']);

        $this->put('/coordinator/posts/events/'.$own->id, [
            'title' => 'Our bigger rally', 'starts_at' => now()->addWeeks(2)->format('Y-m-d H:i'), 'state_id' => $w['otherState']->id,
        ])->assertRedirect();
        // Edited, but still in the coordinator's own state.
        $this->assertDatabaseHas('events', ['id' => $own->id, 'title' => 'Our bigger rally', 'state_id' => $w['state']->id]);

        $this->put('/coordinator/posts/events/'.$foreign->id, ['title' => 'Hijacked', 'starts_at' => now()->addWeek()->format('Y-m-d H:i')])->assertForbidden();
        $this->put('/coordinator/posts/news/'.$national->id, ['title' => 'Hijacked', 'body' => 'x'])->assertForbidden();
        $this->put('/coordinator/posts/news/'.$foreignNews->id, ['title' => 'Hijacked', 'body' => 'x'])->assertForbidden();
        $this->delete('/coordinator/posts/events/'.$foreign->id)->assertForbidden();
        $this->assertDatabaseMissing('events', ['title' => 'Hijacked']);
        $this->assertDatabaseMissing('news_posts', ['title' => 'Hijacked']);

        $this->get('/coordinator/posts')->assertOk()->assertSee('Our bigger rally')->assertDontSee('Their rally')->assertDontSee('Their news');
    }

    public function test_members_see_their_own_state_and_national_news_only(): void
    {
        $w = $this->world();
        $admin = User::factory()->superAdmin()->create();
        $this->makePost(NewsPost::class, null, 'National update', $admin->id);
        $this->makePost(NewsPost::class, $w['state']->id, 'Home state news', $admin->id);
        $this->makePost(NewsPost::class, $w['otherState']->id, 'Other state news', $admin->id);
        $this->makePost(Event::class, $w['otherState']->id, 'Other state rally', $admin->id);
        $this->makePost(Event::class, $w['state']->id, 'Home state rally', $admin->id);

        $this->actingAs($w['mine'])->get('/membership/events')->assertOk()
            ->assertSee('National update')->assertSee('Home state news')->assertSee('Home state rally')
            ->assertDontSee('Other state news')->assertDontSee('Other state rally');
    }
}
