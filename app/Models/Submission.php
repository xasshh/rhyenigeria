<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\HasGeometry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'form_id', 'user_id', 'polling_unit_id', 'ward_id', 'lga_id', 'state_id', 'location', 'accuracy', 'collected_at', 'answers'])]
#[Hidden(['location'])]
class Submission extends Model
{
    use HasGeometry;

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
            'answers' => 'array',
            'accuracy' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function pollingUnit(): BelongsTo
    {
        return $this->belongsTo(PollingUnit::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SubmissionPhoto::class);
    }

    /**
     * Restrict to the submissions a user may see: everything for a super admin,
     * the submissions for polling units inside their area for a coordinator,
     * and only their own for a field agent.
     */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        match ($viewer->role) {
            Role::SuperAdmin => null,
            Role::Coordinator => ($level = $viewer->areaLevel())
                ? $query->where($this->qualifyColumn($level->column()), $viewer->getAttribute($level->column()))
                : $query->whereRaw('1 = 0'),
            Role::FieldAgent => $query->where($this->qualifyColumn('user_id'), $viewer->id),
            Role::Member => $query->whereRaw('1 = 0'),
        };
    }
}
