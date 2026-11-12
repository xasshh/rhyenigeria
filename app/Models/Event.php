<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A rally or other event. National when `state_id` is null. */
#[Fillable(['title', 'description', 'starts_at', 'venue'])]
class Event extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime'];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** National events plus those in the given state. */
    public function scopeForState(Builder $query, ?int $stateId): void
    {
        $query->where(fn ($q) => $q->whereNull('state_id')->when($stateId, fn ($q) => $q->orWhere('state_id', $stateId)));
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>=', now())->orderBy('starts_at');
    }
}
