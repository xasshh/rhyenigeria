<x-layouts.public title="News" description="News and photos from Renewed Hope Youth Engagement events across Nigeria." current="news">
    <section class="relative isolate overflow-hidden bg-forest text-white">
        <x-fingerprint class="absolute -top-24 -right-24 -z-10 w-[420px] text-accent/[0.2]" />
        <div class="mx-auto max-w-[1440px] px-5 py-14 sm:px-8 lg:px-[66px] lg:py-20">
            <p class="eyebrow text-mint">News &amp; gallery</p>
            <h1 class="mt-3 font-headline text-[40px] leading-[1.02] font-extrabold tracking-[-0.03em] sm:text-[60px]">From the field</h1>
            <p class="mt-4 max-w-[640px] font-body text-lg leading-relaxed text-white/80 sm:text-xl">Events, empowerment programmes and the young Nigerians behind them, state by state.</p>
        </div>
    </section>
    <section class="mx-auto max-w-[1440px] px-5 py-12 sm:px-8 lg:px-[66px] lg:py-16">
        @if ($posts->isEmpty())
            <p class="font-body text-lg text-neutral-600">No stories yet. Check back soon.</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    @include('partials.news-card', ['post' => $post])
                @endforeach
            </div>
            <div class="mt-10 font-body">{{ $posts->links() }}</div>
        @endif
    </section>
</x-layouts.public>
