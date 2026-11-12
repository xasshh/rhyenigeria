<?php

namespace Tests\Feature;

use App\Enums\CanvasserStatus;
use App\Models\AuditLog;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoordinatorScopingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function world(): array
    {
        $geo = $this->geography();

        $mine = User::factory()->member($geo['wardA'])->create(['name' => 'Mine Member']);
        MemberProfile::factory()->for($mine)->canvasser()->withPvc()->create(['nin' => '11122233344']);

        $theirs = User::factory()->member($geo['farWard'])->create(['name' => 'Far Away Member']);
        MemberProfile::factory()->for($theirs)->canvasser()->create();

        $coordinator = User::factory()->coordinator($geo['state'])->create();
        $otherCoordinator = User::factory()->coordinator($geo['otherState'])->create();

        return $geo + compact('mine', 'theirs', 'coordinator', 'otherCoordinator');
    }

    public function test_a_canvasser_application_shows_up_only_for_their_own_states_coordinator(): void
    {
        $w = $this->world();

        $this->actingAs($w['coordinator'])->get('/coordinator/canvassers')
            ->assertOk()->assertSee('Mine Member')->assertDontSee('Far Away Member');

        $this->actingAs($w['otherCoordinator'])->get('/coordinator/canvassers')
            ->assertOk()->assertSee('Far Away Member')->assertDontSee('Mine Member');
    }

    public function test_a_coordinator_can_approve_their_states_canvasser_and_it_is_audited(): void
    {
        $w = $this->world();

        $this->actingAs($w['coordinator'])
            ->post('/coordinator/canvassers/'.$w['mine']->id, ['decision' => 'approve', 'note' => 'Welcome aboard'])
            ->assertRedirect();

        $profile = $w['mine']->memberProfile->fresh();
        $this->assertSame(CanvasserStatus::Approved, $profile->canvasser_status);
        $this->assertSame($w['coordinator']->id, $profile->canvasser_reviewed_by);
        $this->assertSame('Canvasser', $profile->roleLabel());

        $log = AuditLog::where('action', 'canvasser.approved')->sole();
        $this->assertSame($w['mine']->id, $log->subject_id);
        $this->assertSame('Welcome aboard', $log->metadata['note']);
    }

    public function test_a_coordinator_cannot_review_another_states_canvasser(): void
    {
        $w = $this->world();

        $this->actingAs($w['coordinator'])
            ->post('/coordinator/canvassers/'.$w['theirs']->id, ['decision' => 'approve'])
            ->assertNotFound();

        $this->assertSame(CanvasserStatus::Pending, $w['theirs']->memberProfile->fresh()->canvasser_status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_coordinator_cannot_see_another_states_members(): void
    {
        $w = $this->world();
        $this->actingAs($w['coordinator']);

        $this->get('/coordinator/members')->assertOk()->assertSee('Mine Member')->assertDontSee('Far Away Member');
        // Filters cannot widen the scope.
        $this->get('/coordinator/members?state_id='.$w['otherState']->id)->assertOk()->assertDontSee('Far Away Member');
        $this->get('/coordinator/members?q=Far')->assertOk()->assertDontSee('Far Away Member');
        $this->get('/coordinator/members/'.$w['theirs']->id)->assertNotFound();
        $this->get('/coordinator/members/'.$w['mine']->id)->assertOk()->assertSee('Mine Member');
    }

    public function test_a_coordinator_cannot_export_another_states_members_and_exports_are_audited(): void
    {
        $w = $this->world();
        $this->actingAs($w['coordinator']);

        $csv = $this->get('/coordinator/members/export?include_identifiers=1')->assertOk()->streamedContent();

        $this->assertStringContainsString('Mine Member', $csv);
        $this->assertStringNotContainsString('Far Away Member', $csv);
        // Coordinators never get NIN/VIN, even if they ask.
        $this->assertStringNotContainsString('11122233344', $csv);
        $this->assertStringNotContainsString(',nin', strtok($csv, "\n"));

        $log = AuditLog::where('action', 'members.exported')->sole();
        $this->assertSame($w['coordinator']->id, $log->user_id);
        $this->assertSame(1, $log->metadata['rows']);

        // Asking for another state narrows to nothing rather than widening.
        $other = $this->get('/coordinator/members/export?state_id='.$w['otherState']->id)->streamedContent();
        $this->assertStringNotContainsString('Far Away Member', $other);
        $this->assertSame(1, substr_count(trim($other), "\n") + 1);
    }

    public function test_a_super_admin_exports_identifiers_only_when_asked(): void
    {
        $w = $this->world();
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        $plain = $this->get('/admin/members/export')->streamedContent();
        $this->assertStringContainsString('Far Away Member', $plain);
        $this->assertStringNotContainsString('11122233344', $plain);

        $full = $this->get('/admin/members/export?include_identifiers=1')->streamedContent();
        $this->assertStringContainsString('11122233344', $full);
        $this->assertTrue(AuditLog::where('action', 'members.exported')->get()->last()->metadata['include_identifiers']);
    }

    public function test_member_lists_mask_phones_and_detail_pages_never_show_nin_or_vin(): void
    {
        $w = $this->world();
        $this->actingAs($w['coordinator']);
        $phone = $w['mine']->phone;

        $this->get('/coordinator/members')->assertDontSee(substr($phone, 4, 6));
        $this->get('/coordinator/members/'.$w['mine']->id)
            ->assertSee(substr($phone, -4))
            ->assertDontSee('11122233344')
            ->assertDontSee('90F5B12345678901234')
            ->assertSee('3344');
    }

    public function test_the_dashboards_render_for_each_role(): void
    {
        $w = $this->world();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($w['coordinator']);
        foreach (['/coordinator', '/coordinator/empowerment', '/coordinator/posts'] as $page) {
            $this->get($page)->assertOk();
        }

        $this->actingAs($admin);
        foreach (['/admin', '/admin/members', '/admin/canvassers', '/admin/coordinators', '/admin/settings', '/admin/posts', '/admin/empowerment', '/admin/states/'.$w['state']->id] as $page) {
            $this->get($page)->assertOk();
        }

        // Members cannot reach staff pages, and staff cannot reach each other's.
        $this->actingAs($w['mine'])->get('/coordinator')->assertForbidden();
        $this->actingAs($w['mine'])->get('/admin')->assertForbidden();
        $this->actingAs($w['coordinator'])->get('/admin')->assertForbidden();
    }

    public function test_coordinator_posts_go_to_their_state_only(): void
    {
        $w = $this->world();

        $this->actingAs($w['coordinator'])->post('/coordinator/posts/news', [
            'title' => 'Ward meeting', 'body' => 'Saturday 10am', 'state_id' => $w['otherState']->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('news_posts', ['title' => 'Ward meeting', 'state_id' => $w['state']->id]);

        $this->actingAs($w['mine'])->get('/membership')->assertSee('Ward meeting');
        $this->actingAs($w['theirs'])->get('/membership')->assertDontSee('Ward meeting');
    }

    public function test_a_super_admin_can_create_a_state_coordinator(): void
    {
        $w = $this->world();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post('/admin/coordinators', [
            'name' => 'Bola Ade', 'email' => 'Bola@Example.com', 'phone' => '08051234567',
            'password' => 'long-password', 'password_confirmation' => 'long-password', 'state_id' => $w['state']->id,
        ])->assertRedirect();

        $coordinator = User::where('email', 'bola@example.com')->sole();
        $this->assertTrue($coordinator->isCoordinator());
        $this->assertSame($w['state']->id, $coordinator->state_id);

        auth()->logout();
        $this->post('/login', ['email' => 'bola@example.com', 'password' => 'long-password'])->assertRedirect(route('coordinator.dashboard'));
    }
}
