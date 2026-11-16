<?php

namespace Tests\Feature;

use App\Enums\CanvasserStatus;
use App\Models\FieldActivity;
use App\Models\MemberProfile;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coordinators see every state's totals with their own state highlighted, but the people
 * tables (recruiters, canvassers) stay inside their own state. Super admins see everything.
 */
class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: State} */
    private function membersIn(State $state, int $count, array $profile = []): array
    {
        $first = null;
        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->member($state)->create();
            MemberProfile::factory()->for($user)->create($profile);
            $first ??= $user;
        }

        return [$first, $state];
    }

    public function test_a_coordinator_sees_every_state_ranked_with_their_own_highlighted(): void
    {
        $lagos = State::factory()->create(['name' => 'Lagos']);
        $kano = State::factory()->create(['name' => 'Kano']);
        $oyo = State::factory()->create(['name' => 'Oyo']);
        $this->membersIn($lagos, 3);
        $this->membersIn($kano, 2);
        $this->membersIn($oyo, 1);

        $this->actingAs(User::factory()->coordinator($kano)->create())
            ->get('/coordinator/leaderboard')
            ->assertOk()
            ->assertViewHas('mine', fn (array $row) => $row['id'] === $kano->id && $row['rank'] === 2)
            ->assertViewHas('chasing', fn (array $row) => $row['id'] === $lagos->id)
            ->assertSeeInOrder(['Lagos', 'Kano', 'Oyo'])
            ->assertSee('1</strong> behind Lagos', false);

        // And the dashboard shows the rank.
        $this->get('/coordinator')->assertOk()->assertSee('#2 of 3');
    }

    public function test_states_can_be_ranked_by_another_metric_and_ties_share_a_rank(): void
    {
        $a = State::factory()->create(['name' => 'Abia']);
        $b = State::factory()->create(['name' => 'Benue']);
        $c = State::factory()->create(['name' => 'Cross River']);
        $this->membersIn($a, 3);
        $this->membersIn($b, 1, ['canvasser_status' => CanvasserStatus::Approved]);
        $this->membersIn($c, 1, ['canvasser_status' => CanvasserStatus::Approved]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/leaderboard?by=canvassers')
            ->assertOk()
            ->assertViewHas('states', fn (array $rows) => array_column($rows, 'rank', 'name') === ['Benue' => 1, 'Cross River' => 1, 'Abia' => 3]);
    }

    public function test_recruiter_and_canvasser_tables_stay_inside_the_coordinators_state(): void
    {
        [$mine, $state] = $this->membersIn(State::factory()->create(), 1, ['canvasser_status' => CanvasserStatus::Approved]);
        [$theirs] = $this->membersIn(State::factory()->create(), 1, ['canvasser_status' => CanvasserStatus::Approved]);
        $mine->update(['name' => 'Home Recruiter']);
        $theirs->update(['name' => 'Far Recruiter']);
        foreach ([$mine, $theirs] as $recruiter) {
            MemberProfile::factory()->for(User::factory()->member($state))->create(['referred_by_user_id' => $recruiter->id]);
            FieldActivity::factory()->for($recruiter)->create(['state_id' => $recruiter->state_id]);
        }

        $this->actingAs(User::factory()->coordinator($state)->create())
            ->get('/coordinator/leaderboard')
            ->assertOk()
            ->assertViewHas('recruiters', fn (array $rows) => array_column($rows, 'name') === ['Home Recruiter'])
            ->assertViewHas('canvassers', fn (array $rows) => array_column($rows, 'name') === ['Home Recruiter'])
            ->assertDontSee('Far Recruiter');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/leaderboard')
            ->assertViewHas('recruiters', fn (array $rows) => count($rows) === 2);
    }

    public function test_members_cannot_open_the_leaderboard(): void
    {
        [$member] = $this->membersIn(State::factory()->create(), 1);

        $this->actingAs($member)->get('/coordinator/leaderboard')->assertForbidden();
        $this->get('/admin/leaderboard')->assertForbidden();
    }
}
