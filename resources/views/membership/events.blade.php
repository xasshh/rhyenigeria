<x-layouts.app title="Events & news" heading="Events & news">
    <div class="mt-4 inline-flex rounded-xl border border-line bg-white p-1 text-sm font-medium">
        <a href="{{ route('membership.events') }}" @class(['rounded-lg px-4 py-1.5', 'bg-brand text-white' => ! $mine, 'text-neutral-600' => $mine])>Everything</a>
        <a href="{{ route('membership.events', ['scope' => 'state']) }}" @class(['rounded-lg px-4 py-1.5', 'bg-brand text-white' => $mine, 'text-neutral-600' => ! $mine])>My state</a>
    </div>

    <section class="mt-6">
        <h2 class="font-display text-lg font-bold">Upcoming events</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            @forelse ($events as $event)
                @php $at = $event->starts_at->setTimezone(config('membership.timezone')); @endphp
                <article class="panel flex gap-4 p-4">
                    <div class="flex h-16 w-14 shrink-0 flex-col items-center justify-center rounded-xl bg-brand/10 text-brand-dark">
                        <span class="text-[11px] font-semibold uppercase">{{ $at->format('M') }}</span>
                        <span class="text-2xl leading-none font-bold">{{ $at->format('j') }}</span>
                    </div>
                    <div class="min-w-0">
                        <span class="badge {{ $event->state_id ? 'bg-brand/10 text-brand-dark' : 'bg-accent/20 text-[#7a5b00]' }}">{{ $event->state?->name ?? 'National' }}</span>
                        <h3 class="mt-1 font-display font-bold">{{ $event->title }}</h3>
                        <p class="text-sm text-neutral-600">{{ $at->format('l, g:ia') }}{{ $event->venue ? ' · '.$event->venue : '' }}</p>
                        @if ($event->description)<p class="mt-1 text-sm whitespace-pre-line text-neutral-700">{{ $event->description }}</p>@endif
                    </div>
                </article>
            @empty
                <p class="panel p-5 text-sm text-neutral-600 sm:col-span-2">No upcoming events{{ $mine ? ' in your state' : '' }} yet.</p>
            @endforelse
        </div>
    </section>

    <section class="mt-8">
        <h2 class="font-display text-lg font-bold">News</h2>
        <div class="mt-3 space-y-3">
            @forelse ($news as $post)
                @include('partials.news-item', ['post' => $post])
            @empty
                <p class="panel p-5 text-sm text-neutral-600">No news yet.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $news->links() }}</div>
    </section>
</x-layouts.app>
