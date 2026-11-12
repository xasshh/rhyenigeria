<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['programme', 'received_on', 'what_received'])]
class EmpowermentReport extends Model
{
    protected function casts(): array
    {
        return ['received_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Reports by members the viewer may see. */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        $query->whereIn('user_id', User::query()->membersVisibleTo($viewer)->select('users.id'));
    }
}
