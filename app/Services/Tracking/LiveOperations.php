<?php

namespace App\Services\Tracking;

use App\Enums\FieldActivityKind;
use App\Models\FieldActivity;
use App\Models\State;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Who is out canvassing right now, where they are, and how the day is going.
 * Adapted from GeoVerify's live operations console.
 *
 * Everything starts from User::canvassersVisibleTo(), so a coordinator only
 * ever sees their own state. "Out now" is judged by the last GPS fix, not by
 * whether a shift was politely ended: a phone that died leaves a shift open.
 * Distances and flags are worked out by PostGIS, never in PHP.
 */
class LiveOperations
{
    /** Past this without a fix, a canvasser on shift is shown as quiet rather than active. */
    public const ACTIVE_MINUTES = 30;

    /** A fix less accurate than this (metres) is flagged as weak GPS. */
    public const WEAK_ACCURACY_M = 100;

    /** Faster than this (metres per second, about 160 km/h) between fixes is an impossible jump. */
    public const JUMP_SPEED_MS = 45;

    /** Vertices kept per trail, enough to read a day's route on a map. */
    private const TRAIL_POINTS = 500;

    /**
     * @return array{canvassers: list<array<string, mixed>>, activities: list<array<string, mixed>>, totals: array<string, int>, generated_at: string}
     */
    public function feed(User $viewer, ?int $stateId = null): array
    {
        $dayStart = $this->dayStart();
        $ids = $this->canvasserIds($viewer, $stateId);

        $canvassers = $ids === [] ? [] : $this->canvassers($ids, $dayStart);
        $activities = $ids === [] ? [] : $this->activities($ids, $dayStart);

        return [
            'canvassers' => $canvassers,
            'activities' => $activities,
            'totals' => [
                'canvassers' => count($ids),
                'on_shift' => count(array_filter($canvassers, fn ($c) => $c['on_shift'])),
                'active' => count(array_filter($canvassers, fn ($c) => $c['status'] === 'active')),
                'activities' => count($activities),
                'households' => (int) array_sum(array_column($activities, 'households')),
            ],
            'generated_at' => now()->toIso8601ZuluString(),
        ];
    }

    /**
     * One canvasser's route for one day, as a simplified line plus their activities.
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function trail(User $canvasser, ?CarbonImmutable $day = null): array
    {
        $from = ($day ?? CarbonImmutable::now(config('membership.timezone')))->startOfDay()->utc();
        $to = $from->addDay();

        $row = DB::selectOne(<<<'SQL'
            WITH pts AS (
                SELECT location, recorded_at, row_number() OVER (ORDER BY recorded_at, id) AS n, count(*) OVER () AS total
                FROM location_points
                WHERE user_id = ? AND recorded_at >= ? AND recorded_at < ?
            )
            SELECT ST_AsGeoJSON(ST_MakeLine(location ORDER BY recorded_at), 6) AS line,
                   min(recorded_at) AS started_at, max(recorded_at) AS ended_at, max(total) AS total
            FROM pts
            WHERE total <= ? OR n % greatest(1, (total / ?)::int) = 0 OR n = total
            SQL, [$canvasser->id, $from, $to, self::TRAIL_POINTS, self::TRAIL_POINTS]);

        $features = [];
        if ($row && $row->line && (int) $row->total >= 2) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($row->line, true),
                'properties' => ['kind' => 'trail', 'points' => (int) $row->total],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /** The state outline, simplified, for the map's frame. */
    public function outline(?int $stateId): ?array
    {
        if ($stateId === null) {
            return null;
        }

        $geo = DB::selectOne('SELECT ST_AsGeoJSON(ST_SimplifyPreserveTopology(boundary, 0.003), 5) AS g FROM states WHERE id = ? AND boundary IS NOT NULL', [$stateId])?->g;

        return $geo ? json_decode($geo, true) : null;
    }

    /** Midnight today in Nigeria, in UTC. */
    public function dayStart(): CarbonImmutable
    {
        return CarbonImmutable::now(config('membership.timezone'))->startOfDay()->utc();
    }

    /** @return list<int> */
    private function canvasserIds(User $viewer, ?int $stateId): array
    {
        return User::query()->canvassersVisibleTo($viewer)
            ->when($viewer->isSuperAdmin() && $stateId, fn ($q) => $q->where('users.state_id', $stateId))
            ->pluck('users.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function canvassers(array $ids, CarbonImmutable $dayStart): array
    {
        $in = implode(',', array_fill(0, count($ids), '?'));

        $rows = DB::select(<<<SQL
            WITH today AS (
                SELECT lp.user_id, lp.recorded_at, lp.accuracy,
                       ST_Distance(lp.location::geography, (lag(lp.location) OVER w)::geography) AS step_m,
                       extract(epoch FROM lp.recorded_at - lag(lp.recorded_at) OVER w) AS step_s
                FROM location_points lp
                WHERE lp.user_id IN ({$in}) AND lp.recorded_at >= ?
                WINDOW w AS (PARTITION BY lp.user_id ORDER BY lp.recorded_at, lp.id)
            ),
            stats AS (
                SELECT user_id,
                       count(*) AS fixes,
                       coalesce(sum(step_m) FILTER (WHERE step_s > 0 AND step_m / step_s <= ?), 0) AS distance_m,
                       count(*) FILTER (WHERE accuracy > ?) AS weak_fixes,
                       count(*) FILTER (WHERE step_s > 0 AND step_m > 300 AND step_m / step_s > ?) AS jumps
                FROM today GROUP BY user_id
            ),
            acts AS (
                SELECT user_id, count(*) AS activities, coalesce(sum(households), 0) AS households
                FROM field_activities WHERE user_id IN ({$in}) AND recorded_at >= ? GROUP BY user_id
            )
            SELECT u.id, u.name, u.phone, l.name AS lga, w.name AS ward,
                   s.started_at AS shift_started_at,
                   ST_Y(ap.location) AS latitude, ST_X(ap.location) AS longitude, ap.accuracy, ap.recorded_at AS seen_at,
                   pw.name AS seen_ward,
                   coalesce(st.fixes, 0) AS fixes, coalesce(st.distance_m, 0) AS distance_m,
                   coalesce(st.weak_fixes, 0) AS weak_fixes, coalesce(st.jumps, 0) AS jumps,
                   coalesce(a.activities, 0) AS activities, coalesce(a.households, 0) AS households
            FROM users u
            LEFT JOIN lgas l ON l.id = u.lga_id
            LEFT JOIN wards w ON w.id = u.ward_id
            LEFT JOIN shifts s ON s.user_id = u.id AND s.ended_at IS NULL
            LEFT JOIN agent_positions ap ON ap.user_id = u.id
            LEFT JOIN wards pw ON pw.id = ap.ward_id
            LEFT JOIN stats st ON st.user_id = u.id
            LEFT JOIN acts a ON a.user_id = u.id
            WHERE u.id IN ({$in})
            ORDER BY (s.started_at IS NULL), ap.recorded_at DESC NULLS LAST, u.name
            SQL, [...$ids, $dayStart, self::JUMP_SPEED_MS, self::WEAK_ACCURACY_M, self::JUMP_SPEED_MS, ...$ids, $dayStart, ...$ids]);

        $now = CarbonImmutable::now();

        return array_map(function ($r) use ($now, $dayStart) {
            $seen = $r->seen_at ? CarbonImmutable::parse($r->seen_at, 'UTC') : null;
            $onShift = $r->shift_started_at !== null;
            $minutes = $seen ? (int) floor($seen->diffInMinutes($now, true)) : null;
            $status = match (true) {
                $onShift && $minutes !== null && $minutes <= self::ACTIVE_MINUTES => 'active',
                $onShift => 'quiet',
                default => 'off',
            };

            return [
                'id' => (int) $r->id,
                'name' => $r->name,
                'area' => collect([$r->ward, $r->lga])->filter()->implode(', ') ?: null,
                'on_shift' => $onShift,
                'status' => $status,
                'shift_started_at' => $r->shift_started_at ? CarbonImmutable::parse($r->shift_started_at, 'UTC')->toIso8601ZuluString() : null,
                // Today's last position only; yesterday's is not "where they are".
                'position' => $seen && $seen->greaterThanOrEqualTo($dayStart) && $r->latitude !== null
                    ? ['latitude' => (float) $r->latitude, 'longitude' => (float) $r->longitude, 'accuracy' => $r->accuracy === null ? null : round((float) $r->accuracy)]
                    : null,
                'seen_at' => $seen?->toIso8601ZuluString(),
                'seen_minutes_ago' => $minutes,
                'seen_ward' => $r->seen_ward,
                'distance_km' => round(((float) $r->distance_m) / 1000, 1),
                'activities' => (int) $r->activities,
                'households' => (int) $r->households,
                'flags' => array_values(array_filter([
                    (int) $r->jumps > 0 ? $r->jumps.' impossible '.str('jump')->plural((int) $r->jumps) : null,
                    (int) $r->fixes > 0 && (int) $r->weak_fixes / (int) $r->fixes > 0.5 ? 'Weak GPS most of the day' : null,
                ])),
            ];
        }, $rows);
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function activities(array $ids, CarbonImmutable $dayStart): array
    {
        $tz = config('membership.timezone');

        return FieldActivity::query()->withCoordinates()
            ->whereIn('user_id', $ids)->where('recorded_at', '>=', $dayStart)
            ->with('user:id,name', 'event:id,title', 'ward:id,name')
            ->latest('recorded_at')->limit(500)->get()
            ->map(fn (FieldActivity $a) => [
                'id' => $a->id,
                'user_id' => $a->user_id,
                'canvasser' => $a->user->name,
                'kind' => $a->kind->value,
                'label' => $a->kind->label(),
                'households' => $a->households,
                'outcome' => $a->outcome?->label(),
                'event' => $a->event?->title,
                'note' => $a->note,
                'ward' => $a->ward?->name,
                'photo' => $a->photo_path ? route('field.activities.photo', $a) : null,
                'time' => $a->recorded_at->setTimezone($tz)->format('g:ia'),
                'latitude' => (float) $a->latitude,
                'longitude' => (float) $a->longitude,
            ])->all();
    }

    /** @return array<string, string> */
    public static function kindLabels(): array
    {
        return collect(FieldActivityKind::cases())->mapWithKeys(fn ($k) => [$k->value => $k->label()])->all();
    }

    public static function stateName(?int $id): ?string
    {
        return $id ? State::whereKey($id)->value('name') : null;
    }
}
