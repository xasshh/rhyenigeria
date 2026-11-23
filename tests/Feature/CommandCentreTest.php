<?php

namespace Tests\Feature;

use App\Enums\CanvasserStatus;
use App\Enums\Role;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommandCentreTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_opens_the_command_centre_and_it_is_audit_logged(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/command')->assertOk()->assertSee('Election Command Centre');

        $this->assertDatabaseHas('audit_logs', ['action' => 'command_centre.opened', 'user_id' => $admin->id]);
    }

    public function test_coordinators_members_and_guests_are_kept_out(): void
    {
        $this->get('/admin/command')->assertRedirect(route('login'));
        $this->get('/admin/command/feed')->assertRedirect(route('login'));

        foreach ([User::factory()->coordinator()->create(), User::factory()->role(Role::Member)->create()] as $user) {
            $this->actingAs($user)->get('/admin/command')->assertForbidden();
            $this->actingAs($user)->get('/admin/command/feed')->assertForbidden();
        }
    }

    public function test_the_feed_counts_members_by_area_and_coverage_for_a_single_state_site(): void
    {
        $_ENV['SITE_BRAND'] = $_SERVER['SITE_BRAND'] = 'us27';
        config(['site' => require config_path('site.php')]);
        unset($_ENV['SITE_BRAND'], $_SERVER['SITE_BRAND']);
        $geo = $this->geography();
        $geo['state']->update(['code' => 'KD']);

        foreach ([$geo['wardA'], $geo['wardA'], $geo['wardB'], $geo['farWard']] as $ward) {
            MemberProfile::factory()->for(User::factory()->member($ward)->create())->create();
        }

        $feed = $this->actingAs(User::factory()->superAdmin()->create())->getJson('/admin/command/feed')->assertOk();

        // The member in another state is not part of this site's picture.
        $feed->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.today', 3)
            ->assertJsonPath('areas.0.id', $geo['lga']->id)
            ->assertJsonPath('areas.0.total', 3)
            ->assertJsonPath('coverage.wards', 2)
            ->assertJsonPath('coverage.wards_total', 2)
            ->assertJsonCount(3, 'recent');
    }

    public function test_the_call_desk_lists_coordinators_agents_and_members_with_their_numbers(): void
    {
        $geo = $this->geography();
        $admin = User::factory()->superAdmin()->create();
        $lgaCoordinator = User::factory()->coordinator($geo['lga'])->create(['name' => 'Lami LGA', 'phone' => '+2348030000001']);
        $wardCoordinator = User::factory()->coordinator($geo['wardA'])->create(['name' => 'Wale Ward', 'phone' => '+2348030000002']);
        $agent = User::factory()->member($geo['wardA'])->create(['name' => 'Agnes Agent', 'phone' => '+2348030000003']);
        MemberProfile::factory()->for($agent)->canvasser(CanvasserStatus::Approved)->create();
        $member = User::factory()->member($geo['wardB'])->create(['name' => 'Musa Member', 'phone' => '+2348030000004']);
        MemberProfile::factory()->for($member)->create();
        MemberProfile::factory()->for(User::factory()->member($geo['wardB'])->inactive()->create(['name' => 'Gone Away']))->create();

        $all = $this->actingAs($admin)->getJson('/admin/command/contacts')->assertOk()
            ->assertJsonPath('counts.lga_coordinator', 1)
            ->assertJsonPath('counts.ward_coordinator', 1)
            ->assertJsonPath('counts.agent', 1)
            ->assertJsonPath('counts.member', 1)
            ->assertJsonPath('total', 4);
        // Leaders first, and every row carries a number to call.
        $this->assertSame(['Lami LGA', 'Wale Ward', 'Agnes Agent', 'Musa Member'], array_column($all->json('contacts'), 'name'));
        $this->assertSame('+2348030000003', $all->json('contacts.2.phone'));

        $this->getJson('/admin/command/contacts?kind=agent')->assertJsonCount(1, 'contacts')->assertJsonPath('contacts.0.name', 'Agnes Agent');
        $this->getJson('/admin/command/contacts?q=0803 000 0004')->assertJsonCount(1, 'contacts')->assertJsonPath('contacts.0.name', 'Musa Member');
        $this->getJson('/admin/command/contacts?lga_id='.$geo['otherLga']->id)->assertJsonCount(0, 'contacts');

        $this->assertDatabaseHas('audit_logs', ['action' => 'command_centre.contacts_searched', 'user_id' => $admin->id]);
    }

    public function test_only_super_admins_can_use_the_call_desk(): void
    {
        $this->actingAs(User::factory()->coordinator()->create())->getJson('/admin/command/contacts')->assertForbidden();
    }

    public function test_the_live_map_endpoints_answer_super_admins_only(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->getJson('/admin/command/geo')->assertOk()->assertJsonStructure(['areas' => ['type', 'features'], 'labels' => ['type', 'features']]);
        $this->actingAs($admin)->getJson('/admin/command/live')->assertOk()->assertJsonStructure(['agents', 'activities', 'totals']);

        $coordinator = User::factory()->coordinator()->create();
        foreach (['/admin/command/geo', '/admin/command/live', '/admin/command/news', '/admin/command/area/1'] as $url) {
            $this->actingAs($coordinator)->getJson($url)->assertForbidden();
        }
    }

    public function test_the_area_close_up_lists_what_is_inside_it(): void
    {
        $geo = $this->geography();
        $admin = User::factory()->superAdmin()->create();
        MemberProfile::factory()->for(User::factory()->member($geo['wardA'])->create())->create();

        $this->actingAs($admin)->getJson('/admin/command/area/'.$geo['state']->id)
            ->assertOk()->assertJsonPath('noun', 'LGA')->assertJsonPath('parts.0.name', $geo['lga']->name)->assertJsonPath('parts.0.total', 1);
        $this->actingAs($admin)->getJson('/admin/command/area/999999')->assertNotFound();
    }

    public function test_the_news_wire_merges_searches_newest_first_and_survives_an_outage(): void
    {
        Cache::flush();
        $rss = fn (array $items) => '<?xml version="1.0"?><rss><channel>'.implode('', array_map(fn ($i) => "<item><title>{$i[0]} - {$i[1]}</title><link>https://news.google.com/rss/articles/{$i[2]}</link><pubDate>{$i[3]}</pubDate><source url=\"https://x.example\">{$i[1]}</source></item>", $items)).'</channel></rss>';
        Http::fake(['news.google.com/*' => Http::sequence()
            ->push($rss([['INEC extends voter registration', 'Daily Trust', 'a', now()->subHour()->toRfc2822String()], ['Older story', 'Punch', 'b', now()->subDays(2)->toRfc2822String()]]))
            ->push($rss([['INEC extends voter registration', 'Daily Trust', 'a2', now()->subHour()->toRfc2822String()], ['Kaduna news', 'Channels', 'c', now()->subMinutes(10)->toRfc2822String()]]))
            ->whenEmpty(Http::response('', 500))]);
        config(['site.news_topics' => ['One' => 'q1', 'Two' => 'q2']]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->getJson('/admin/command/news')->assertOk()
            ->assertJsonCount(3, 'items')
            ->assertJsonPath('items.0.title', 'Kaduna news')
            ->assertJsonPath('items.0.source', 'Channels')
            ->assertJsonPath('items.1.title', 'INEC extends voter registration');

        // Later, Google is down: the last list is still served.
        $this->travel(15)->minutes();
        $this->actingAs($admin)->getJson('/admin/command/news')->assertOk()->assertJsonCount(3, 'items');
    }
}
