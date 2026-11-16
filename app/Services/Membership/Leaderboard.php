<?php

namespace App\Services\Membership;

use App\Enums\CanvasserStatus;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rankings. The state table holds aggregate counts only, so every coordinator may see every
 * state's row (the homepage map already publishes members per state). The people tables
 * (recruiters, canvassers) start from the viewer's own scope, like every other member list.
 */
class Leaderboard
{
    /** @var array<string, string> Ranking metrics and their labels. */
    public const METRICS = [
        'members' => 'Members',
        'week' => 'New this week',
        'month' => 'New this month',
        'canvassers' => 'Canvassers',
        'pvc' => 'PVC holders',
        'activities' => 'Field activities (30 days)',
    ];

    public const ACTIVITY_DAYS = 30;

    /**
     * Every state, ranked by one metric. Ties share a rank (1, 2, 2, 4) and are listed by name.
     *
     * @return list<array{id: int, name: string, code: string, rank: int, members: int, week: int, month: int, canvassers: int, pvc: int, activities: int}>
     */
    public function states(string $metric = 'members'): array
    {
        $metric = array_key_exists($metric, self::METRICS) ? $metric : 'members';
        $now = MemberDirectory::local();

        $rows = DB::table('states')
            ->leftJoinSub(
                DB::table('users')
                    ->join('member_profiles as mp', 'mp.user_id', '=', 'users.id')
                    ->where('users.role', Role::Member->value)
                    ->groupBy('users.state_id')
                    ->selectRaw(<<<'SQL'
                        users.state_id,
                        count(*) AS members,
                        count(*) FILTER (WHERE mp.created_at >= ?) AS week,
                        count(*) FILTER (WHERE mp.created_at >= ?) AS month,
                        count(*) FILTER (WHERE mp.canvasser_status = ?) AS canvassers,
                        count(*) FILTER (WHERE mp.has_pvc) AS pvc
                        SQL, [$now->startOfWeek()->utc(), $now->startOfMonth()->utc(), CanvasserStatus::Approved->value]),
                'm', 'm.state_id', '=', 'states.id')
            ->leftJoinSub(
                DB::table('field_activities')
                    ->where('recorded_at', '>=', $now->subDays(self::ACTIVITY_DAYS)->utc())
                    ->groupBy('state_id')
                    ->selectRaw('state_id, count(*) AS activities'),
                'a', 'a.state_id', '=', 'states.id')
            ->selectRaw(<<<'SQL'
                states.id, states.name, states.code,
                coalesce(m.members, 0) AS members, coalesce(m.week, 0) AS week, coalesce(m.month, 0) AS month,
                coalesce(m.canvassers, 0) AS canvassers, coalesce(m.pvc, 0) AS pvc, coalesce(a.activities, 0) AS activities
                SQL)
            ->get()
            ->map(fn (object $row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'code' => (string) $row->code,
                'rank' => 0,
                'members' => (int) $row->members,
                'week' => (int) $row->week,
                'month' => (int) $row->month,
                'canvassers' => (int) $row->canvassers,
                'pvc' => (int) $row->pvc,
                'activities' => (int) $row->activities,
            ])
            ->sort(fn (array $a, array $b) => [$b[$metric], $a['name']] <=> [$a[$metric], $b['name']])
            ->values()
            ->all();

        foreach ($rows as $i => $row) {
            $rows[$i]['rank'] = $i > 0 && $row[$metric] === $rows[$i - 1][$metric] ? $rows[$i - 1]['rank'] : $i + 1;
        }

        return $rows;
    }

    /**
     * Members who brought in the most sign-ups with their referral link, within the viewer's scope.
     *
     * @return list<array{id: int, name: string, state: string|null, member_number: string, total: int}>
     */
    public function recruiters(User $viewer, int $limit = 10): array
    {
        return User::query()
            ->membersVisibleTo($viewer)
            ->join('member_profiles as mp', 'mp.user_id', '=', 'users.id')
            ->joinSub(
                DB::table('member_profiles')->whereNotNull('referred_by_user_id')
                    ->groupBy('referred_by_user_id')->selectRaw('referred_by_user_id, count(*) AS total'),
                'r', 'r.referred_by_user_id', '=', 'users.id')
            ->leftJoin('states', 'states.id', '=', 'users.state_id')
            ->orderByDesc('r.total')->orderBy('users.name')
            ->limit($limit)
            ->get(['users.id', 'users.name', 'states.name as state', 'mp.member_number', 'r.total'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'state' => $row->state,
                'member_number' => (string) $row->member_number,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Approved canvassers with the most field activities in the last 30 days, within the viewer's scope.
     *
     * @return list<array{id: int, name: string, state: string|null, activities: int, households: int}>
     */
    public function canvassers(User $viewer, int $limit = 10): array
    {
        return User::query()
            ->canvassersVisibleTo($viewer)
            ->joinSub(
                DB::table('field_activities')
                    ->where('recorded_at', '>=', MemberDirectory::local()->subDays(self::ACTIVITY_DAYS)->utc())
                    ->groupBy('user_id')
                    ->selectRaw('user_id, count(*) AS activities, coalesce(sum(households), 0) AS households'),
                'fa', 'fa.user_id', '=', 'users.id')
            ->leftJoin('states', 'states.id', '=', 'users.state_id')
            ->orderByDesc('fa.activities')->orderByDesc('fa.households')->orderBy('users.name')
            ->limit($limit)
            ->get(['users.id', 'users.name', 'states.name as state', 'fa.activities', 'fa.households'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'state' => $row->state,
                'activities' => (int) $row->activities,
                'households' => (int) $row->households,
            ])
            ->all();
    }
}
