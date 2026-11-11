<?php

namespace App\Models;

use App\Models\Concerns\HasGeometry;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The latest known position of an agent: one row per agent, maintained by
 * LocationIngestor so the live map never has to scan location_points.
 */
#[Hidden(['location'])]
class AgentPosition extends Model
{
    use HasGeometry;

    public const CREATED_AT = null;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'accuracy' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'nearest_polling_unit_distance_m' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function nearestPollingUnit(): BelongsTo
    {
        return $this->belongsTo(PollingUnit::class, 'nearest_polling_unit_id');
    }

    /**
     * The shape used by both the map endpoint and the broadcast event.
     *
     * Expects the row to be loaded withCoordinates() and with user, ward and
     * nearestPollingUnit.
     *
     * @return array<string, mixed>
     */
    public function toMapArray(): array
    {
        return [
            'agent' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'phone' => $this->user->phone,
                'state_id' => $this->user->state_id,
                'lga_id' => $this->user->lga_id,
                'ward_id' => $this->user->ward_id,
            ],
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy,
            'recorded_at' => $this->recorded_at?->toIso8601ZuluString(),
            'ward' => $this->ward ? ['id' => $this->ward->id, 'code' => $this->ward->code, 'name' => $this->ward->name] : null,
            'nearest_polling_unit' => $this->nearestPollingUnit ? [
                'id' => $this->nearestPollingUnit->id,
                'code' => $this->nearestPollingUnit->code,
                'name' => $this->nearestPollingUnit->name,
                'distance_m' => $this->nearest_polling_unit_distance_m === null ? null : round($this->nearest_polling_unit_distance_m, 1),
            ] : null,
        ];
    }
}
