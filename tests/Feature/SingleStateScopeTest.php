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
}
