<article class="panel p-4 sm:p-5" x-data="{ more: false }">
    <div class="flex flex-wrap items-center gap-2 text-xs">
        @if ($post->source_label)<span class="badge bg-ink text-white">{{ $post->source_label }}</span>@endif
        <span class="badge {{ $post->state_id ? 'bg-brand/10 text-brand-dark' : 'bg-accent/20 text-[#7a5b00]' }}">{{ $post->ward?->name ?? $post->state?->name ?? 'National' }}</span>
        <time class="text-neutral-500" datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->setTimezone(config('membership.timezone'))->format('j M Y') }}</time>
    </div>
    <h3 class="mt-2 font-display font-bold">{{ $post->title }}</h3>
    <div class="mt-1 text-sm leading-relaxed whitespace-pre-line text-neutral-700" :class="more ? '' : 'line-clamp-3'">{{ $post->body }}</div>
    @if (mb_strlen($post->body) > 200)
        <button type="button" class="mt-1 text-sm font-semibold text-brand" x-on:click="more = !more" x-text="more ? 'Show less' : 'Read more'">Read more</button>
    @endif
</article>
