<x-layouts.app title="Calendar">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm text-neutral-500">Past and upcoming events</p>
            <h1 class="font-display text-2xl font-bold tracking-tight sm:text-[28px]">Calendar</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($states->isNotEmpty())
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="month" value="{{ $calendar->month->format('Y-m') }}">
                    <label class="sr-only" for="state_id">State</label>
                    <select id="state_id" name="state_id" class="field py-2 text-sm" onchange="this.form.submit()">
                        <option value="">All events</option>
                        @foreach ($states as $s)
                            <option value="{{ $s->id }}" @selected($stateId === $s->id)>{{ $s->name }} + national</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <a href="{{ staff_route('posts.index') }}#event" class="btn-primary btn-sm"><x-icon name="calendar" class="h-4 w-4" /> Add an event</a>
        </div>
    </div>

    <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
        <x-event-calendar :calendar="$calendar" />

        <aside class="panel h-max p-5">
            <h2 class="font-display font-bold">Next up</h2>
            <ul class="mt-3 space-y-3">
                @forelse ($upcoming as $event)
                    @php $at = $event->starts_at->setTimezone(config('membership.timezone')); @endphp
                    <li>
                        <a href="{{ request()->fullUrlWithQuery(['month' => $at->format('Y-m')]) }}" class="block rounded-xl p-2 -m-2 hover:bg-surface">
                            <p class="font-semibold">{{ $event->title }}</p>
                            <p class="text-sm text-neutral-600">{{ $at->format('D j M, g:ia') }}</p>
                            <p class="text-xs text-neutral-500">{{ $event->state?->name ?? 'National' }}{{ $event->venue ? ' · '.$event->venue : '' }}</p>
                        </a>
                    </li>
                @empty
                    <li class="text-sm text-neutral-600">Nothing scheduled yet.</li>
                @endforelse
            </ul>
        </aside>
    </div>
</x-layouts.app>
