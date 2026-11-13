<?php

namespace App\Models;

use App\Enums\DoorOutcome;
use App\Enums\FieldActivityKind;
use App\Models\Concerns\HasGeometry;
use Database\Factories\FieldActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A door-to-door visit, event check-in or report logged by a canvasser on shift. */
#[Hidden(['location', 'photo_path'])]
class FieldActivity extends Model
{
    /** @use HasFactory<FieldActivityFactory> */
    use HasFactory, HasGeometry;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => FieldActivityKind::class,
            'outcome' => DoorOutcome::class,
            'recorded_at' => 'datetime',
            'accuracy' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'households' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
