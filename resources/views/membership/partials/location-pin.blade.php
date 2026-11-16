{{-- Pin (or re-pin, or remove) the member's location on the members map. $profile, $prompt (bool: the bold home-page version). --}}
@php $pinned = $profile->hasPinnedLocation(); @endphp
<section id="location" x-data="locationPin" @class(['scroll-mt-24 rounded-2xl p-5', 'border border-accent/50 bg-amber-soft text-amber-ink' => $prompt && ! $pinned, 'panel' => ! ($prompt && ! $pinned)])>
    <div class="flex items-start gap-4">
        <span @class(['flex h-11 w-11 shrink-0 items-center justify-center rounded-xl', 'bg-white text-action' => ! $pinned, 'bg-brand/10 text-brand' => $pinned])><x-icon name="pin" /></span>
        <div class="min-w-0 flex-1">
            @if ($pinned)
                <h2 class="font-display font-bold">Your location is pinned</h2>
                <p class="mt-1 text-sm text-neutral-600">Pinned {{ $profile->location_pinned_at?->setTimezone(config('membership.timezone'))->format('j M Y') ?? 'when you joined' }}. Your coordinator sees this one point on the members map. Moved? Pin it again from where you live or vote.</p>
            @else
                <h2 class="font-display font-bold">Pin your location on the map</h2>
                <p class="mt-1 text-sm {{ $prompt ? '' : 'text-neutral-600' }}">Tap once, from home or near where you vote, so your coordinator can see where members are and plan activities nearby. It is one point, not tracking: it never moves unless you pin again, and you can remove it any time.</p>
            @endif
            <p class="mt-2 text-sm font-medium" x-show="message" x-text="message" x-cloak :class="status === 'error' ? 'text-red-700' : ''"></p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('membership.location.update') }}" x-ref="form">
                    @csrf @method('PUT')
                    <input type="hidden" name="latitude" x-ref="lat">
                    <input type="hidden" name="longitude" x-ref="lng">
                    <button type="button" class="{{ $pinned ? 'btn-ghost' : 'btn-primary' }} btn-sm" x-on:click="pin" :disabled="status === 'busy'"><x-icon name="pin" class="h-4 w-4" /> {{ $pinned ? 'Update my pin' : 'Pin my location' }}</button>
                </form>
                @if ($pinned)
                    <form method="POST" action="{{ route('membership.location.destroy') }}" x-data x-on:submit="if (!confirm('Remove your pin from the members map?')) $event.preventDefault()">
                        @csrf @method('DELETE')
                        <button class="text-sm text-red-700 hover:underline">Remove pin</button>
                    </form>
                @endif
            </div>
            @error('latitude')<p class="error mt-2">{{ $message }}</p>@enderror
        </div>
    </div>
</section>
