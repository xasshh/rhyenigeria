<article class="panel p-4 sm:p-5" x-data="{ more: false }">
    <div class="flex flex-wrap items-center gap-2 text-xs">
        @if ($post->source_label)<span class="badge bg-ink text-white">{{ $post->source_label }}</span>@endif
        <span class="badge {{ $post->state_id ? 'bg-brand/10 text-brand-dark' : 'bg-accent/20 text-[#7a5b00]' }}">{{ $post->ward?->name ?? $post->state?->name ?? 'National' }}</span>
        <time class="text-neutral-500" datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->setTimezone(config('membership.timezone'))->format('j M Y') }}</time>
    </div>
    @if ($post->relationLoaded('photos') && $post->photos->isNotEmpty())
        <img src="{{ $post->photos->first()->url() }}" alt="{{ $post->photos->first()->alt ?? '' }}" class="mt-3 aspect-[3/2] w-full rounded-xl object-cover" loading="lazy">
    @endif
    <h3 class="mt-2 font-display font-bold">{{ $post->title }}</h3>
    <div class="mt-1 text-sm leading-relaxed whitespace-pre-line text-neutral-700" :class="more ? '' : 'line-clamp-3'">{{ $post->body }}</div>
    @if (mb_strlen($post->body) > 200)
        <button type="button" class="mt-1 text-sm font-semibold text-brand" x-on:click="more = !more" x-text="more ? 'Show less' : 'Read more'">Read more</button>
    @endif
    @if ($post->is_public && $post->slug)
        <a href="{{ route('news.show', $post->slug) }}" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-brand">{{ ($post->relationLoaded('photos') && $post->photos->count() > 1) ? 'See all '.$post->photos->count().' photos' : 'Open the story' }} <x-icon name="arrow-right" class="h-4 w-4" /></a>
    @endif
</article>
