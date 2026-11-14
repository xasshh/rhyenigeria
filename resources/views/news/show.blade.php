@php
    // Paragraphs of the story, and the hashtags at its end shown as tags.
    $lines = preg_split('/\R/', trim($post->body));
    $tags = [];
    while ($lines && preg_match('/^\s*#\w+\s*$/u', end($lines))) {
        array_unshift($tags, trim(array_pop($lines)));
    }
    $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', implode("\n", $lines)))));
    $photos = $post->photos->map(fn ($p) => ['src' => $p->url(), 'full' => $p->originalUrl(), 'alt' => $p->alt ?? ''])->values();
    $cover = $post->photos->first();
@endphp
<x-layouts.public :title="$post->title" :description="str($paragraphs[0] ?? $post->title)->limit(180)" :image="$cover?->url()" current="news">
    <article x-data="{ open: false, i: 0, photos: @js($photos),
            show(n) { this.i = n; this.open = true; this.$nextTick(() => this.$refs.close.focus()) },
            next() { this.i = (this.i + 1) % this.photos.length }, prev() { this.i = (this.i - 1 + this.photos.length) % this.photos.length },
            touchX: null }"
        x-on:keydown.escape.window="open = false" x-on:keydown.right.window="open && next()" x-on:keydown.left.window="open && prev()">
        <header class="relative isolate overflow-hidden bg-forest text-white">
            <x-fingerprint class="absolute -top-24 -right-24 -z-10 w-[420px] text-accent/[0.2]" />
            <div class="mx-auto max-w-[980px] px-5 pt-10 pb-12 sm:px-8 lg:pt-16">
                <a href="{{ route('news.index') }}" class="inline-flex items-center gap-1.5 font-body text-sm font-semibold text-mint hover:underline"><x-icon name="arrow-left" class="h-4 w-4" /> All news</a>
                <p class="mt-6 font-body text-sm text-white/70">{{ $post->published_at->setTimezone(config('membership.timezone'))->format('l j F Y') }}{{ $post->source_label ? ' · '.$post->source_label : '' }}</p>
                <h1 class="mt-2 font-headline text-[36px] leading-[1.05] font-extrabold tracking-[-0.03em] text-balance sm:text-[54px]">{{ $post->title }}</h1>
            </div>
        </header>

        <div class="mx-auto max-w-[980px] px-5 sm:px-8">
            @if ($cover)
                <button type="button" class="-mt-6 block w-full overflow-hidden rounded-[24px] shadow-[0_30px_60px_-30px_rgba(15,42,28,0.6)]" x-on:click="show(0)" aria-label="Open photo 1 of {{ $photos->count() }}">
                    <img src="{{ $cover->url() }}" alt="{{ $cover->alt ?? '' }}" class="aspect-[3/2] w-full object-cover" fetchpriority="high">
                </button>
            @endif

            <div class="mx-auto max-w-[720px] py-10 font-body text-[18px] leading-[1.75] text-neutral-800 sm:text-[19px]">
                @foreach ($paragraphs as $paragraph)
                    <p @class(['mt-6' => ! $loop->first, 'text-[20px] font-medium text-forest sm:text-[22px]' => $loop->first])>{!! nl2br(e($paragraph)) !!}</p>
                @endforeach
                @if ($tags)
                    <p class="mt-8 flex flex-wrap gap-2">
                        @foreach ($tags as $tag)<span class="rounded-full bg-[#eaf3ec] px-3 py-1 text-[15px] font-semibold text-action-dark">{{ $tag }}</span>@endforeach
                    </p>
                @endif
            </div>

            @if ($photos->count() > 1)
                <section class="pb-14" aria-labelledby="gallery-title">
                    <h2 id="gallery-title" class="font-headline text-[26px] font-extrabold tracking-[-0.02em] text-forest">Gallery <span class="font-body text-lg font-normal text-neutral-500">· {{ $photos->count() }} photos</span></h2>
                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
                        @foreach ($post->photos as $n => $photo)
                            <button type="button" class="group overflow-hidden rounded-2xl bg-surface focus-visible:ring-4 focus-visible:ring-action/30 focus-visible:outline-none" x-on:click="show({{ $n }})" aria-label="Open photo {{ $n + 1 }} of {{ $photos->count() }}">
                                <img src="{{ $photo->url() }}" alt="{{ $photo->alt ?? '' }}" class="aspect-[3/2] w-full object-cover transition duration-500 group-hover:scale-[1.04]" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="mb-14 flex flex-col items-start gap-4 rounded-[24px] bg-action p-6 text-white sm:flex-row sm:items-center sm:justify-between sm:p-8">
                <p class="font-headline text-[24px] leading-tight font-extrabold">Be part of the next one.</p>
                <a href="{{ route('join') }}" class="inline-flex min-h-[52px] items-center gap-2 rounded-xl bg-accent px-6 font-body text-[17px] font-bold text-forest hover:bg-[#d9a404]">Join the Movement <x-icon name="arrow-right" class="h-4 w-4" /></a>
            </section>
        </div>

        @if ($more->isNotEmpty())
            <section class="bg-surface py-14">
                <div class="mx-auto max-w-[1440px] px-5 sm:px-8 lg:px-[66px]">
                    <h2 class="font-headline text-[28px] font-extrabold tracking-[-0.02em] text-forest">More from the field</h2>
                    <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($more as $story) @include('partials.news-card', ['post' => $story]) @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Full-screen photo viewer --}}
        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4" role="dialog" aria-modal="true" aria-label="Photo viewer"
             x-on:click.self="open = false" x-on:touchstart.passive="touchX = $event.touches[0].clientX"
             x-on:touchend.passive="const dx = $event.changedTouches[0].clientX - touchX; if (Math.abs(dx) > 45) dx < 0 ? next() : prev()">
            <img :src="photos[i]?.src" :alt="photos[i]?.alt" class="max-h-[85vh] max-w-full rounded-xl object-contain">
            <button type="button" x-ref="close" class="absolute top-4 right-4 flex h-11 w-11 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25" x-on:click="open = false" aria-label="Close"><x-icon name="x" /></button>
            <template x-if="photos.length > 1">
                <div>
                    <button type="button" class="absolute top-1/2 left-3 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25" x-on:click="prev()" aria-label="Previous photo"><x-icon name="chevron-left" /></button>
                    <button type="button" class="absolute top-1/2 right-3 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25" x-on:click="next()" aria-label="Next photo"><x-icon name="chevron-right" /></button>
                </div>
            </template>
            <p class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-white/15 px-3 py-1 font-body text-sm text-white" x-text="(i + 1) + ' / ' + photos.length"></p>
        </div>
    </article>
</x-layouts.public>
