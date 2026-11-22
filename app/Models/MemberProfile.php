<?php

namespace App\Models;

use App\Enums\AgeRange;
use App\Enums\CanvasserStatus;
use App\Enums\Gender;
use Database\Factories\MemberProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Everything about a member that is not needed to sign in. One per member user.
 * NIN and VIN are encrypted at rest with the application key.
 */
class MemberProfile extends Model
{
    /** @use HasFactory<MemberProfileFactory> */
    use HasFactory;

    /** Mass assignment is not used; every field is set explicitly. */
    protected $guarded = ['*'];

    protected $hidden = ['vin', 'nin', 'verify_token'];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'age_range' => AgeRange::class,
            'has_pvc' => 'boolean',
            'vin' => 'encrypted',
            'nin' => 'encrypted',
            'canvasser_status' => CanvasserStatus::class,
            'canvasser_roles' => 'array',
            'canvasser_applied_at' => 'datetime',
            'canvasser_reviewed_at' => 'datetime',
            'signup_lat' => 'float',
            'signup_lng' => 'float',
            'location_pinned_at' => 'datetime',
            'consent_accepted_at' => 'datetime',
            'empowerment_benefited' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pollingUnit(): BelongsTo
    {
        return $this->belongsTo(PollingUnit::class);
    }

    /** "012 · Central School" from our list, the name the member typed, or null. */
    public function pollingUnitLabel(): ?string
    {
        return $this->pollingUnit ? $this->pollingUnit->code.' · '.$this->pollingUnit->name : $this->polling_unit_name;
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canvasser_reviewed_by');
    }

    public function isApprovedCanvasser(): bool
    {
        return $this->canvasser_status === CanvasserStatus::Approved;
    }

    public function roleLabel(): string
    {
        return $this->isApprovedCanvasser() ? 'Canvasser' : 'Member';
    }

    /** True when the member has put a pin on the members map (at sign-up or later). */
    public function hasPinnedLocation(): bool
    {
        return $this->signup_lat !== null && $this->signup_lng !== null;
    }

    public function hasVin(): bool
    {
        return filled($this->vin);
    }

    /** True when the PVC still needs attention (no card yet, or no VIN recorded). */
    public function needsVin(): bool
    {
        return ! $this->has_pvc || ! $this->hasVin();
    }

    /** RHYE-{STATE}-{6 digits}, unique. */
    public static function newMemberNumber(State $state): string
    {
        $prefix = 'RHYE-'.(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $state->code)) ?: 'NG').'-';

        do {
            $number = $prefix.str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        } while (static::where('member_number', $number)->exists());

        return $number;
    }

    public static function newVerifyToken(): string
    {
        do {
            $token = Str::random(40);
        } while (static::where('verify_token', $token)->exists());

        return $token;
    }
}
