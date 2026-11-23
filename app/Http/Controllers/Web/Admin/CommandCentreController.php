<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\State;
use App\Models\User;
use App\Models\Ward;
use App\Services\Audit\AuditLogger;
use App\Services\Geo\AreaMap;
use App\Services\Membership\MemberDirectory;
use App\Services\Tracking\LiveOperations;
use App\Services\Voice\AfricasTalkingVoice;
use App\Support\NigerianPhone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The election command centre: one full-screen, live view of the campaign for the
 * leadership. Super admins only (route group); every opening is audit-logged.
 * A single-state site watches its LGAs; the national site watches the states.
 */
class CommandCentreController extends Controller
{
    public function index(Request $request, AreaMap $map, AuditLogger $audit, AfricasTalkingVoice $voice): View
    {
        $state = $this->state();
        $shapes = $state ? $map->lgas($state) : $map->states();
        $audit->record($request, AuditLogger::COMMAND_CENTRE_OPENED);

        return view('admin.command', [
            'state' => $state,
            'map' => $shapes,
            'areaNoun' => $state ? 'LGA' : 'state',
            'electionDate' => Setting::electionDate(),
            'electionLabel' => Setting::electionLabel(),
            'contactKinds' => self::contactKinds(),
            'line' => [
                'enabled' => $voice->isConfigured(),
                'number' => $voice->number() ? NigerianPhone::format(NigerianPhone::normalise($voice->number()) ?? $voice->number()) : null,
                'url' => route('admin.command.calls'),
                'store' => route('admin.command.calls.store'),
                'handledUrl' => route('admin.command.calls.handled', '__ID__'),
                'outcomeUrl' => route('admin.command.calls.outcome', '__ID__'),
                'directUrl' => route('admin.command.calls.direct'),
                'incomingUrl' => route('admin.command.calls.incoming'),
                'csrf' => csrf_token(),
                'operatorPhone' => $request->user()->phone,
            ],
            'config' => [
                'feed' => route('admin.command.feed'),
                'contacts' => route('admin.command.contacts'),
                'refreshSeconds' => 30,
                'operator' => $request->user()->name,
                'scope' => $state ? $state->name.' State' : 'Nigeria',
                'areasTotal' => count($shapes['areas']),
            ],
        ]);
    }

    public function feed(Request $request, MemberDirectory $directory, LiveOperations $live): JsonResponse
    {
        $viewer = $request->user();
        $state = $this->state();
        $members = $directory->query($viewer)->when($state, fn ($q) => $q->where('users.state_id', $state->id));

        $areas = $directory->byArea($members, $state ? 'lga' : 'state')->filter(fn ($a) => $a->id !== null)->values();
        $field = $live->feed($viewer, $state?->id);
        $tz = config('membership.timezone');

        $wardsCovered = (clone $members)->toBase()->whereNotNull('users.ward_id')->distinct()->count('users.ward_id');
        $wardsTotal = $state
            ? Ward::whereHas('lga', fn ($q) => $q->where('state_id', $state->id))->count()
            : Ward::count();

        $recent = (clone $members)->with('lga:id,name', 'ward:id,name')
            ->select('users.id', 'users.name', 'users.lga_id', 'users.ward_id', 'mp.created_at as joined_at', 'mp.registered_by_user_id', 'mp.import_source')
            ->whereNull('mp.import_source')
            ->latest('mp.created_at')->limit(12)->get()
            ->map(fn (User $u) => [
                'name' => $u->publicName(),
                'place' => collect([$u->ward?->name, $u->lga?->name])->filter()->implode(', '),
                'via' => $u->registered_by_user_id ? 'field' : 'web',
                'time' => CarbonImmutable::parse($u->joined_at, 'UTC')->setTimezone($tz)->format('H:i'),
            ]);

        return response()->json([
            'summary' => $directory->summary($members),
            'areas' => $areas->map(fn ($a) => [
                'id' => (int) $a->id, 'name' => $a->name, 'total' => (int) $a->total,
                'today' => (int) $a->today, 'agents' => (int) $a->canvassers,
            ]),
            'coverage' => [
                'wards' => $wardsCovered, 'wards_total' => $wardsTotal,
                'areas' => $areas->where('total', '>', 0)->count(),
            ],
            // Bulk-imported lists would swamp the day they were loaded; the trend shows people signing up.
            'trend' => $directory->perDay((clone $members)->whereNull('mp.import_source'), 30),
            'field' => $field['totals'],
            'activities' => collect($field['activities'])->take(10)->map(fn ($a) => [
                'who' => $a['canvasser'], 'label' => $a['label'], 'ward' => $a['ward'], 'time' => $a['time'],
            ])->values(),
            'recent' => $recent,
            'generated_at' => now()->toIso8601ZuluString(),
        ])->setPrivate();
    }

    /** Who each kind of contact is; the SQL decides the kind once so filters, counts and labels agree. */
    private const KIND_SQL = <<<'SQL'
        CASE
            WHEN users.role = 'coordinator' AND users.ward_id IS NOT NULL THEN 'ward_coordinator'
            WHEN users.role = 'coordinator' AND users.lga_id IS NOT NULL THEN 'lga_coordinator'
            WHEN users.role = 'coordinator' THEN 'state_coordinator'
            WHEN mp.canvasser_status = 'approved' THEN 'agent'
            ELSE 'member'
        END
        SQL;

    /** @return array<string, string> */
    public static function contactKinds(): array
    {
        return [
            'state_coordinator' => 'State coordinator',
            'lga_coordinator' => 'LGA coordinator',
            'ward_coordinator' => 'Ward coordinator',
            'agent' => site_term('Canvasser'),
            'member' => 'Member',
        ];
    }

    /**
     * The call desk: coordinators, agents and members with their phone numbers, to call or
     * WhatsApp from the command centre. Every search is audit-logged.
     */
    public function contacts(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['nullable', Rule::in(array_keys(self::contactKinds()))],
            'lga_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $state = $this->state();
        $perPage = 30;
        $page = (int) ($data['page'] ?? 1);

        $base = User::query()
            ->leftJoin('member_profiles as mp', 'mp.user_id', '=', 'users.id')
            ->where('users.is_active', true)
            ->whereNotNull('users.phone')
            ->whereIn('users.role', [Role::Coordinator->value, Role::Member->value])
            ->when($state, fn (Builder $q) => $q->where('users.state_id', $state->id))
            ->when($data['lga_id'] ?? null, fn (Builder $q, $id) => $q->where('users.lga_id', $id))
            ->when(filled($data['q'] ?? null), function (Builder $q) use ($data) {
                $term = trim($data['q']);
                $digits = ltrim(preg_replace('/\D+/', '', $term), '0');
                $q->where(function (Builder $w) use ($term, $digits) {
                    $w->where('users.name', 'ilike', '%'.addcslashes($term, '%_\\').'%')
                        ->orWhere('mp.member_number', 'ilike', '%'.addcslashes($term, '%_\\').'%');
                    if (strlen($digits) >= 4) {
                        $w->orWhere('users.phone', 'like', '%'.$digits.'%');
                    }
                });
            });

        $counts = (clone $base)->toBase()->selectRaw(self::KIND_SQL.' AS kind, count(*) AS n')->groupBy(DB::raw('1'))->pluck('n', 'kind')->map(fn ($n) => (int) $n);

        $kinds = array_keys(self::contactKinds());
        $rows = (clone $base)
            ->when($data['kind'] ?? null, fn (Builder $q, $kind) => $q->whereRaw(self::KIND_SQL.' = ?', [$kind]))
            ->leftJoin('lgas', 'lgas.id', '=', 'users.lga_id')
            ->leftJoin('wards', 'wards.id', '=', 'users.ward_id')
            ->select('users.id', 'users.name', 'users.phone', 'users.role', 'mp.whatsapp_phone', 'mp.member_number', 'lgas.name as lga', 'wards.name as ward')
            ->selectRaw(self::KIND_SQL.' AS kind')
            ->selectRaw('EXISTS (SELECT 1 FROM shifts s WHERE s.user_id = users.id AND s.ended_at IS NULL) AS on_shift')
            // Leaders first, agents on shift before the rest, then by name.
            ->orderByRaw('array_position(?::text[], '.self::KIND_SQL.')', ['{'.implode(',', $kinds).'}'])
            ->orderByRaw('on_shift DESC')
            ->orderBy('users.name')
            ->offset(($page - 1) * $perPage)->limit($perPage + 1)
            ->get();

        if ($page === 1) {
            $audit->record($request, AuditLogger::COMMAND_CONTACTS_SEARCHED, null, array_filter([
                'kind' => $data['kind'] ?? null, 'lga_id' => $data['lga_id'] ?? null, 'q' => $data['q'] ?? null,
            ]));
        }

        $labels = self::contactKinds();

        return response()->json([
            'counts' => $counts,
            'total' => $counts->sum(),
            'has_more' => $rows->count() > $perPage,
            'contacts' => $rows->take($perPage)->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'kind' => $r->kind,
                'label' => $labels[$r->kind] ?? 'Member',
                'area' => collect([$r->ward, $r->lga])->filter()->implode(', ') ?: null,
                'phone' => $r->phone,
                'phone_display' => NigerianPhone::format($r->phone),
                'whatsapp' => ltrim($r->whatsapp_phone ?: $r->phone, '+'),
                'on_shift' => (bool) $r->on_shift,
                'profile' => $r->role === Role::Member ? route('admin.members.show', $r->id) : null,
            ])->values(),
        ])->setPrivate();
    }

    private function state(): ?State
    {
        return site_state_code() ? State::where('code', site_state_code())->first() : null;
    }
}
