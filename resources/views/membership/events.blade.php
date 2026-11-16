<x-layouts.app title="Events & news" heading="Events & news">
    <div class="mt-4 inline-flex rounded-xl border border-line bg-white p-1 text-sm font-medium">
        <a href="{{ request()->fullUrlWithQuery(['scope' => null, 'page' => null]) }}" @class(['rounded-lg px-4 py-1.5', 'bg-brand text-white' => ! $mine, 'text-neutral-600' => $mine])>Everything</a>
        <a href="{{ request()->fullUrlWithQuery(['scope' => 'state', 'page' => null]) }}" @class(['rounded-lg px-4 py-1.5', 'bg-brand text-white' => $mine, 'text-neutral-600' => ! $mine])>My state</a>
    </div>

    @if ($upcoming->isNotEmpty())
        <section class="mt-6">
            <h2 class="font-display text-lg font-bold">Coming up</h2>
            <div class="mt-3 flex gap-3 overflow-x-auto pb-1 [scrollbar-width:none]">
                @foreach ($upcoming as $event)
                    @php $at = $event->starts_at->setTimezone(config('membership.timezone')); @endphp
                    <a href="{{ route('membership.events', array_filter(['scope' => $mine ? 'state' : null, 'month' => $at->format('Y-m')])) }}" class="panel flex w-64 shrink-0 gap-3 p-3 hover:border-brand/40">
                        <div class="flex h-14 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-brand/10 text-brand-dark">
                            <span class="text-[11px] font-semibold uppercase">{{ $at->format('M') }}</span>
                            <span class="text-xl leading-none font-bold">{{ $at->format('j') }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $event->title }}</p>
                            <p class="truncate text-sm text-neutral-600">{{ $at->format('D, g:ia') }}{{ $event->venue ? ' · '.$event->venue : '' }}</p>
                            <p class="text-xs text-neutral-500">{{ $event->state?->name ?? 'National' }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <x-event-calendar :calendar="$calendar" class="mt-6" />

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
