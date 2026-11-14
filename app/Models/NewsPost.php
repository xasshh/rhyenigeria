<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An update. National when `state_id` is null; limited to one ward when `ward_id` is set. */
#[Fillable(['title', 'body', 'source_label', 'published_at'])]
class NewsPost extends Model
{
    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'is_public' => 'boolean'];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(NewsPhoto::class)->orderBy('position')->orderBy('id');
    }

    /** Stories on the public website: ticked public by a super admin, and already published. */
    public function scopePublicStories(Builder $query): void
    {
        $query->where('is_public', true)->whereNotNull('slug')->where('published_at', '<=', now());
    }

    /** A unique, readable address for the public page. */
    public static function uniqueSlug(string $title): string
    {
        $base = str($title)->slug()->limit(120, '')->trim('-')->toString() ?: 'news';
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** What a member in this state and ward should see. */
    public function scopeForMember(Builder $query, ?int $stateId, ?int $wardId): void
    {
        $query->where(fn ($q) => $q
            ->whereNull('state_id')
            ->when($stateId, fn ($q) => $q->orWhere(fn ($q) => $q
                ->where('state_id', $stateId)
                ->where(fn ($q) => $q->whereNull('ward_id')->when($wardId, fn ($q) => $q->orWhere('ward_id', $wardId)))))
        )->where('published_at', '<=', now());
    }
}
