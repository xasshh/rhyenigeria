<?php

namespace Tests\Feature;

use App\Enums\CanvasserStatus;
use App\Enums\Role;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
