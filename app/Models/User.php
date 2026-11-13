<?php

namespace App\Models;

use App\Enums\AreaLevel;
use App\Enums\CanvasserStatus;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The `field_agent` default predates public sign-up and is kept so the
     * existing API keeps working. Public registration never relies on it:
     * MemberRegistrar always sets Role::Member explicitly.
     */
    protected $attributes = [
        'role' => 'field_agent',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'tracking_consent_accepted_at' => 'datetime',
        ];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function activeShift(): HasOne
    {
        return $this->hasOne(Shift::class)->whereNull('ended_at');
    }

    public function position(): HasOne
    {
        return $this->hasOne(AgentPosition::class);
    }

    public function memberProfile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    public function empowermentReports(): HasMany
    {
        return $this->hasMany(EmpowermentReport::class);
    }

    public function isMember(): bool
    {
        return $this->role === Role::Member;
    }

    public function firstName(): string
    {
        return (string) str($this->name)->trim()->before(' ');
    }

    /** "Ada O." — what the public verify page shows. */
    public function publicName(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? end($parts) : '';

        return trim($first.($last !== '' ? ' '.mb_strtoupper(mb_substr($last, 0, 1)).'.' : ''));
    }

    /** Where to send this user after they sign in on the website. */
    public function homeRoute(): string
    {
        return match ($this->role) {
            Role::SuperAdmin => route('admin.dashboard'),
            Role::Coordinator => route('coordinator.dashboard'),
            Role::Member => route('membership.home'),
            Role::FieldAgent => route('home'),
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isCoordinator(): bool
    {
        return $this->role === Role::Coordinator;
    }

    public function isFieldAgent(): bool
    {
        return $this->role === Role::FieldAgent;
    }

    /** A member whose canvasser application was approved: the only members who can share location. */
    public function isApprovedCanvasser(): bool
    {
        return $this->isMember() && (bool) $this->memberProfile?->isApprovedCanvasser();
    }

    public function hasAcceptedTrackingConsent(): bool
    {
        return $this->tracking_consent_accepted_at !== null;
    }

    /** The deepest area level set on this user, or null if none is. */
    public function areaLevel(): ?AreaLevel
    {
        return match (true) {
            $this->ward_id !== null => AreaLevel::Ward,
            $this->lga_id !== null => AreaLevel::Lga,
            $this->state_id !== null => AreaLevel::State,
            default => null,
        };
    }

    /**
     * Set the user's area from its deepest level; the parent levels are filled
     * in from the geography tables so they can never disagree.
     */
    public function assignArea(?AreaLevel $level, ?int $id): static
    {
        $this->state_id = $this->lga_id = $this->ward_id = null;

        if ($level === null || $id === null) {
            return $this;
        }

        if ($level === AreaLevel::Ward) {
            $ward = Ward::with('lga')->findOrFail($id);
            $this->ward_id = $ward->id;
            $this->lga_id = $ward->lga_id;
            $this->state_id = $ward->lga->state_id;
        } elseif ($level === AreaLevel::Lga) {
            $lga = Lga::findOrFail($id);
            $this->lga_id = $lga->id;
            $this->state_id = $lga->state_id;
        } else {
            $this->state_id = State::findOrFail($id)->id;
        }

        return $this;
    }

    /**
     * Whether this (coordinator's) area covers the given area. A state-level
     * coordinator covers every LGA and ward in the state, and so on down.
     */
    public function areaCovers(AreaLevel $level, int $id): bool
    {
        $own = $this->areaLevel();

        if ($own === null) {
            return false;
        }

        $ownId = (int) $this->getAttribute($own->column());

        if ($own === $level) {
            return $ownId === $id;
        }

        return match ([$own, $level]) {
            [AreaLevel::State, AreaLevel::Lga] => Lga::whereKey($id)->where('state_id', $ownId)->exists(),
            [AreaLevel::State, AreaLevel::Ward] => Ward::whereKey($id)->whereHas('lga', fn ($q) => $q->where('state_id', $ownId))->exists(),
            [AreaLevel::Lga, AreaLevel::Ward] => Ward::whereKey($id)->where('lga_id', $ownId)->exists(),
            default => false,
        };
    }

    public function scopeFieldAgents(Builder $query): void
    {
        $query->where($this->qualifyColumn('role'), Role::FieldAgent->value);
    }

    /**
     * Restrict to the users the viewer may see: everyone for a super admin, the
     * field agents assigned inside their area for a coordinator, and only
     * themselves for a field agent. A coordinator with no area sees nobody.
     */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        if ($viewer->isSuperAdmin()) {
            return;
        }

        if ($viewer->isCoordinator() && ($level = $viewer->areaLevel())) {
            $query->where($this->qualifyColumn('role'), Role::FieldAgent->value)
                ->where($this->qualifyColumn($level->column()), $viewer->getAttribute($level->column()));

            return;
        }

        if ($viewer->isFieldAgent()) {
            $query->whereKey($viewer->id);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    public function scopeMembers(Builder $query): void
    {
        $query->where($this->qualifyColumn('role'), Role::Member->value);
    }

    /**
     * Members the viewer may see: all of them for a super admin, those whose
     * own area falls inside a coordinator's area, and nobody for anyone else.
     * Every member list, detail page, photo and export goes through this.
     */
    public function scopeMembersVisibleTo(Builder $query, User $viewer): void
    {
        $query->where($this->qualifyColumn('role'), Role::Member->value);

        if ($viewer->isSuperAdmin()) {
            return;
        }

        if ($viewer->isCoordinator() && ($level = $viewer->areaLevel())) {
            $query->where($this->qualifyColumn($level->column()), $viewer->getAttribute($level->column()));

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /** Approved canvassers the viewer may see on the live map: the same area rule as members. */
    public function scopeCanvassersVisibleTo(Builder $query, User $viewer): void
    {
        $query->membersVisibleTo($viewer)
            ->whereHas('memberProfile', fn (Builder $q) => $q->where('canvasser_status', CanvasserStatus::Approved->value));
    }

    public function fieldActivities(): HasMany
    {
        return $this->hasMany(FieldActivity::class);
    }

    public function canSeeMember(User $member): bool
    {
        return static::query()->membersVisibleTo($this)->whereKey($member->id)->exists();
    }

    public function canSee(User $other): bool
    {
        return static::query()->visibleTo($this)->whereKey($other->id)->exists();
    }
}
