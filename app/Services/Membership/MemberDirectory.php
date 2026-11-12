<?php

namespace App\Services\Membership;

use App\Enums\AgeRange;
use App\Enums\CanvasserStatus;
use App\Enums\Gender;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Member queries for coordinators and super admins. Everything starts from
 * User::membersVisibleTo(), so filters can narrow a viewer's scope but never
 * widen it.
 */
class MemberDirectory
{
    /** Now, in Nigerian time: "today" and "this week" follow the local calendar. */
    public static function local(): CarbonImmutable
    {
        return now()->toImmutable()->setTimezone(config('membership.timezone'));
    }

    /** Members the viewer may see, joined to their profile as `mp`. */
    public function query(User $viewer): Builder
    {
        return User::query()
            ->membersVisibleTo($viewer)
            ->join('member_profiles as mp', 'mp.user_id', '=', 'users.id');
    }

    /** @return array<string, mixed> */
    public function validateFilters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'state_id' => ['nullable', 'integer'],
            'lga_id' => ['nullable', 'integer'],
            'ward_id' => ['nullable', 'integer'],
            'gender' => ['nullable', new Enum(Gender::class)],
            'age_range' => ['nullable', new Enum(AgeRange::class)],
            'pvc' => ['nullable', Rule::in(['yes', 'no', 'vin', 'no_vin'])],
            'canvasser' => ['nullable', Rule::in(['none', ...array_column(CanvasserStatus::cases(), 'value')])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }

    /** @param  array<string, mixed>  $filters */
    public function filter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $digits = preg_replace('/\D+/', '', $term);
                $q->where(fn (Builder $q) => $q
                    ->where('users.name', 'ilike', $like)
                    ->orWhere('users.email', 'ilike', $like)
                    ->orWhere('mp.member_number', 'ilike', $like)
                    ->when(strlen($digits) >= 6, fn ($q) => $q->orWhere('users.phone', 'like', '%'.ltrim($digits, '0').'%')));
            })
            ->when($filters['state_id'] ?? null, fn ($q, $id) => $q->where('users.state_id', $id))
            ->when($filters['lga_id'] ?? null, fn ($q, $id) => $q->where('users.lga_id', $id))
            ->when($filters['ward_id'] ?? null, fn ($q, $id) => $q->where('users.ward_id', $id))
            ->when($filters['gender'] ?? null, fn ($q, $v) => $q->where('mp.gender', $v))
            ->when($filters['age_range'] ?? null, fn ($q, $v) => $q->where('mp.age_range', $v))
            ->when($filters['pvc'] ?? null, fn ($q, $v) => match ($v) {
                'yes' => $q->where('mp.has_pvc', true),
                'no' => $q->where('mp.has_pvc', false),
                'vin' => $q->whereNotNull('mp.vin'),
                'no_vin' => $q->whereNull('mp.vin'),
            })
            ->when($filters['canvasser'] ?? null, fn ($q, $v) => $v === 'none'
                ? $q->whereNull('mp.canvasser_status')
                : $q->where('mp.canvasser_status', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('mp.created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('mp.created_at', '<', now()->parse($v)->addDay()));
    }

    /** @return array<string, int> */
    public function summary(Builder $members): array
    {
        $row = (clone $members)->toBase()->selectRaw(<<<'SQL'
            count(*) AS total,
            count(*) FILTER (WHERE mp.canvasser_status = 'pending') AS canvassers_pending,
            count(*) FILTER (WHERE mp.canvasser_status = 'approved') AS canvassers_approved,
            count(*) FILTER (WHERE mp.canvasser_status = 'rejected') AS canvassers_rejected,
            count(*) FILTER (WHERE mp.has_pvc) AS pvc,
            count(*) FILTER (WHERE mp.vin IS NOT NULL) AS vin,
            count(*) FILTER (WHERE mp.created_at >= ?) AS today,
            count(*) FILTER (WHERE mp.created_at >= ?) AS week,
            count(*) FILTER (WHERE mp.created_at >= ?) AS month
            SQL, [self::local()->startOfDay()->utc(), self::local()->startOfWeek()->utc(), self::local()->startOfMonth()->utc()])->first();

        return array_map('intval', (array) $row);
    }

    /**
     * Counts grouped by one profile column (gender, age_range, has_pvc).
     *
     * @return Collection<string, int>
     */
    public function breakdown(Builder $members, string $column): Collection
    {
        return (clone $members)->toBase()
            ->selectRaw("mp.{$column} AS k, count(*) AS n")
            ->groupBy("mp.{$column}")->orderBy('k')
            ->pluck('n', 'k')->map(fn ($n) => (int) $n);
    }

    /**
     * Counts per state, LGA or ward, with the area's name.
     *
     * @param  'state'|'lga'|'ward'  $level
     * @return Collection<int, object{id: int|null, name: string|null, total: int, pvc: int, canvassers: int, pending: int, today: int}>
     */
    public function byArea(Builder $members, string $level): Collection
    {
        $table = ['state' => 'states', 'lga' => 'lgas', 'ward' => 'wards'][$level];

        return (clone $members)->toBase()
            ->leftJoin("{$table} as area", 'area.id', '=', "users.{$level}_id")
            ->selectRaw(<<<'SQL'
                area.id AS id, area.name AS name, count(*) AS total,
                count(*) FILTER (WHERE mp.has_pvc) AS pvc,
                count(*) FILTER (WHERE mp.canvasser_status = 'approved') AS canvassers,
                count(*) FILTER (WHERE mp.canvasser_status = 'pending') AS pending,
                count(*) FILTER (WHERE mp.created_at >= ?) AS today
                SQL, [self::local()->startOfDay()->utc()])
            ->groupBy('area.id', 'area.name')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Sign-ups per day for the last N days, gaps filled with zero.
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    public function perDay(Builder $members, int $days = 60): array
    {
        $from = self::local()->subDays($days - 1)->startOfDay();
        $tz = config('membership.timezone');

        $counts = (clone $members)->toBase()
            ->where('mp.created_at', '>=', $from->copy()->utc())
            ->selectRaw("to_char(mp.created_at AT TIME ZONE 'UTC' AT TIME ZONE ?, 'YYYY-MM-DD') AS d, count(*) AS n", [$tz])
            ->groupBy(DB::raw('1'))
            ->pluck('n', 'd');

        $labels = $values = [];
        for ($day = $from->copy(); $day->lte(self::local()); $day = $day->addDay()) {
            $labels[] = $day->format('j M');
            $values[] = (int) ($counts[$day->format('Y-m-d')] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
