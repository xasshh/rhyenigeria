<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A rally or other event. National when `state_id` is null. */
#[Fillable(['title', 'description', 'starts_at', 'venue'])]
class Event extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'is_public' => 'boolean'];
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

    /** Upcoming events staff chose to show on the public homepage, soonest first. */
    public function scopePublicUpcoming(Builder $query): void
    {
        $query->where('is_public', true)->upcoming();
    }

    public function flyerUrl(): ?string
    {
        return $this->flyer_path ? Storage::disk('public')->url($this->flyer_path) : null;
    }

    public function flyerOriginalUrl(): ?string
    {
        return $this->flyer_original_path ? Storage::disk('public')->url($this->flyer_original_path) : null;
    }

    public function deleteFlyer(): void
    {
        Storage::disk('public')->delete(array_filter([$this->flyer_path, $this->flyer_original_path]));
        $this->flyer_path = null;
        $this->flyer_original_path = null;
    }

    /**
     * A homepage slide for this event (not saved): its flyer beside the title, date and venue.
     * Only events with a flyer become slides.
     */
    public function toSlide(): HeroSlide
    {
        $at = $this->starts_at->setTimezone(config('membership.timezone'));
        $slide = new HeroSlide([
            'layout' => HeroSlide::LAYOUT_IMAGE,
            'eyebrow' => strtoupper(($this->state?->name ? $this->state->name.' State' : 'National').' · '.$at->format('j F Y').' · Upcoming'),
            'headline' => $this->title,
            'body' => trim($at->format('l, g:ia').($this->venue ? ', '.$this->venue : '').'. '.str($this->description ?? '')->limit(160)),
            'button_label' => 'Register to attend',
            'button_url' => route('join', array_filter(['state' => $this->state?->code])),
            'image_alt' => 'Flyer: '.$this->title,
        ]);
        $slide->image_path = $this->flyer_path;
        $slide->image_original_path = $this->flyer_original_path;

        return $slide;
    }
}
