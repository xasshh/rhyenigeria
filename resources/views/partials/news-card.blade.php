{{-- A public story as a card: cover photo, date, title, first lines. --}}
@php($cover = $post->photos->first())
<a href="{{ route('news.show', $post->slug) }}" class="group block overflow-hidden rounded-[22px] bg-white ring-1 ring-line transition hover:-translate-y-0.5 hover:shadow-[0_24px_50px_-28px_rgba(15,42,28,0.5)] focus-visible:ring-4 focus-visible:ring-action/30 focus-visible:outline-none">
    <div class="relative aspect-[3/2] overflow-hidden bg-surface">
        @if ($cover)
            <img src="{{ $cover->url() }}" alt="{{ $cover->alt ?? '' }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy">
        @else
            <x-fingerprint class="absolute inset-0 m-auto w-1/2 text-brand/20" />
        @endif
        @if ($post->photos->count() > 1)
            <span class="absolute right-3 bottom-3 inline-flex items-center gap-1.5 rounded-full bg-forest/85 px-3 py-1 text-xs font-semibold text-white"><x-icon name="image" class="h-3.5 w-3.5" /> {{ $post->photos->count() }} photos</span>
        @endif
    </div>
    <div class="p-5 sm:p-6">
        <time class="font-body text-sm text-neutral-500" datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->setTimezone(config('membership.timezone'))->format('j F Y') }}</time>
        <h3 class="mt-2 font-headline text-[22px] leading-tight font-extrabold tracking-[-0.01em] text-forest group-hover:text-action sm:text-[24px]">{{ $post->title }}</h3>
        <p class="mt-2 line-clamp-3 font-body text-[16px] leading-relaxed text-neutral-700">{{ str($post->body)->before("\n#")->squish()->limit(200) }}</p>
        <span class="mt-4 inline-flex items-center gap-1.5 font-body text-[15px] font-bold text-action">Read the story <x-icon name="arrow-right" class="h-4 w-4 transition group-hover:translate-x-0.5" /></span>
    </div>
</a>
