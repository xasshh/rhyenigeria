<?php

namespace Tests\Feature;

use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleStateScopeTest extends TestCase
{
    use RefreshDatabase;

    private function kadunaOnly(): array
    {
        $_ENV['SITE_BRAND'] = $_SERVER['SITE_BRAND'] = 'us27';
        config(['site' => require config_path('site.php')]);
        unset($_ENV['SITE_BRAND'], $_SERVER['SITE_BRAND']);
        $geo = $this->geography();
        $geo['state']->update(['code' => 'KD']);

        return $geo;
    }

    public function test_super_admin_pages_have_no_state_picker_and_show_only_the_site_state(): void
    {
        $geo = $this->kadunaOnly();
        $admin = User::factory()->superAdmin()->create();
        $here = User::factory()->member($geo['wardA'])->create(['name' => 'Kaduna Person']);
        $away = User::factory()->member($geo['farWard'])->create(['name' => 'Far Away Person']);
        MemberProfile::factory()->for($here)->create();
        MemberProfile::factory()->for($away)->create();

        foreach (['/admin/team', '/admin/members', '/admin/members/map', '/admin/live', '/admin/canvassers'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertDontSee('All states');
        }

        // Even asking for another state shows this one.
        $this->actingAs($admin)->get('/admin/members?state_id='.$away->state_id)
            ->assertSee('Kaduna Person')->assertDontSee('Far Away Person');
    }

    public function test_the_field_team_form_adds_people_to_the_site_state(): void
    {
        $geo = $this->kadunaOnly();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/team')->assertOk()->assertDontSee('Choose a state above');
        $this->actingAs($admin)->post('/admin/team', [
            'type' => 'lga_coordinator', 'name' => 'Lami Lga', 'phone' => '08031234567', 'lga_id' => $geo['lga']->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => 'Lami Lga', 'state_id' => $geo['state']->id, 'lga_id' => $geo['lga']->id]);
    }

    public function test_empowerment_is_switched_off_and_the_flyer_caption_is_the_campaign_line(): void
    {
        $geo = $this->kadunaOnly();
        $member = User::factory()->member($geo['wardA'])->create();
        MemberProfile::factory()->for($member)->create();

        $this->get('/')->assertOk()->assertDontSee(route('empowerment'), false);
        $this->actingAs($member)->get('/empowerment')->assertNotFound();
        $this->actingAs($member)->get('/membership/empowerment')->assertNotFound();
        $this->actingAs($member)->get('/membership')->assertOk()->assertDontSee('Empowerment');
        $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/empowerment')->assertNotFound();

        $this->actingAs($member)->get('/membership/campaign-dp')->assertOk()
            ->assertSee('I will vote')->assertSee('Tinubu and Uba Sani 2027')->assertDontSee('Choose your message');
    }

    public function test_sign_up_is_for_members_only(): void
    {
        $this->kadunaOnly();

        $this->get('/join')->assertOk()->assertDontSee('How would you like to take part?')->assertDontSee('name="joining_as"', false);
        $this->get('/join?as=canvasser')->assertOk()->assertSee('Join as a Member', false)->assertDontSee('name="joining_as"', false);
    }

    public function test_members_cannot_apply_to_be_agents_from_the_dashboard(): void
    {
        $geo = $this->kadunaOnly();
        $member = User::factory()->member($geo['wardA'])->create();
        MemberProfile::factory()->for($member)->create();

        $this->actingAs($member)->get('/membership')->assertOk()->assertDontSee('Want to do more?');
        $this->actingAs($member)->post('/membership/canvasser', ['whatsapp_phone' => '08031234567'])->assertNotFound();
        $this->assertNull($member->memberProfile->fresh()->canvasser_status);
    }

    public function test_coordinators_see_no_empowerment_anywhere(): void
    {
        $geo = $this->kadunaOnly();
        $coordinator = User::factory()->coordinator($geo['state'])->create(['must_change_password' => false]);

        $this->actingAs($coordinator)->followingRedirects()->get('/coordinator')->assertOk()->assertDontSee('Empowerment')->assertDontSee('empowered');
        $this->actingAs($coordinator)->get('/coordinator/empowerment')->assertNotFound();
        auth()->logout();
        $this->get('/login')->assertOk()->assertDontSee('youth empowered')->assertSee('local governments');
    }
}
