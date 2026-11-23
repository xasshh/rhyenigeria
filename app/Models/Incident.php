<?php

namespace App\Models;

use App\Enums\IncidentCategory;
use App\Enums\IncidentUrgency;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Something happening in the field (at a polling unit, a rally...), reported to the command centre. */
#[Fillable(['category', 'urgency', 'description', 'state_id', 'lga_id', 'ward_id', 'polling_unit_id', 'polling_unit_name', 'latitude', 'longitude', 'accuracy'])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    public const MAX_PHOTOS = 4;

    public const STATUSES = ['new' => 'Sent', 'seen' => 'Seen by the command centre', 'resolved' => 'Resolved', 'dismissed' => 'Closed'];

    protected function casts(): array
    {
        return [
            'category' => IncidentCategory::class,
            'urgency' => IncidentUrgency::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'seen_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['new', 'seen'], true);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
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

    public function pollingUnit(): BelongsTo
    {
        return $this->belongsTo(PollingUnit::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(IncidentPhoto::class)->orderBy('id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(IncidentUpdate::class)->orderBy('id');
    }

    /** "PU 004 Gidan Waya, Ward 3, Kachia". */
    public function place(): string
    {
        $unit = $this->pollingUnit ? trim($this->pollingUnit->code.' '.$this->pollingUnit->name) : $this->polling_unit_name;

        return collect([$unit, $this->ward?->name, $this->lga?->name])->filter()->implode(', ');
    }

    protected static function booted(): void
    {
        // Photos live on the private disk; remove the files with the report.
        static::deleting(function (Incident $incident) {
            Storage::disk('local')->delete($incident->photos()->pluck('path')->all());
        });
    }
}
