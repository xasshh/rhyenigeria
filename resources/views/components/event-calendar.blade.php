@props(['calendar', 'showState' => true])
@php
    /** @var \App\Support\EventCalendar $calendar */
    $tz = config('membership.timezone');
    $now = now();
    $chip = fn ($event) => $event->starts_at->isPast()
        ? 'bg-neutral-100 text-neutral-500'
        : ($event->state_id ? 'bg-brand/10 text-brand-dark' : 'bg-accent/20 text-[#7a5b00]');
@endphp
<section {{ $attributes->merge(['class' => 'panel overflow-hidden']) }} x-data="{ day: null }">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3 sm:px-5">
        <h2 class="font-display text-lg font-bold">{{ $calendar->month->format('F Y') }}</h2>
        <nav class="flex items-center gap-1" aria-label="Month">
            <a href="{{ request()->fullUrlWithQuery(['month' => $calendar->previous()]) }}" class="rounded-lg p-2 text-neutral-600 hover:bg-surface" aria-label="Previous month"><x-icon name="chevron-left" class="h-4 w-4" /></a>
            @unless ($calendar->isCurrentMonth())
                <a href="{{ request()->fullUrlWithQuery(['month' => null]) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand hover:bg-surface">Today</a>
            @endunless
            <a href="{{ request()->fullUrlWithQuery(['month' => $calendar->next()]) }}" class="rounded-lg p-2 text-neutral-600 hover:bg-surface" aria-label="Next month"><x-icon name="chevron-right" class="h-4 w-4" /></a>
        </nav>
    </header>

    <div class="grid grid-cols-7 border-b border-line bg-surface/60 text-center text-[11px] font-semibold tracking-wide text-neutral-500 uppercase">
        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $name)
            <div class="py-2"><span class="sm:hidden">{{ mb_substr($name, 0, 1) }}</span><span class="max-sm:hidden">{{ $name }}</span></div>
        @endforeach
    </div>

    <div class="grid grid-cols-7">
        @foreach ($calendar->weeks as $week)
            @foreach ($week as $cell)
                @php $count = $cell['events']->count(); @endphp
                <button type="button"
                    @if ($count) x-on:click="day = day === '{{ $cell['date'] }}' ? null : '{{ $cell['date'] }}'" :class="day === '{{ $cell['date'] }}' && 'ring-2 ring-inset ring-brand'" @else disabled @endif
                    @class([
                        'relative flex min-h-14 flex-col items-stretch gap-1 border-r border-b border-line p-1 text-left align-top last:border-r-0 sm:min-h-24 sm:p-1.5 [&:nth-child(7n)]:border-r-0',
                        'bg-white' => $cell['inMonth'],
                        'bg-surface/50 text-neutral-400' => ! $cell['inMonth'],
                        'cursor-pointer hover:bg-brand/5' => $count,
                        'cursor-default' => ! $count,
                    ])
                    aria-label="{{ \Carbon\CarbonImmutable::parse($cell['date'])->format('l j F') }}{{ $count ? ', '.$count.' '.str('event')->plural($count) : '' }}">
                    <span @class([
                        'flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold sm:text-sm',
                        'bg-brand text-white' => $cell['isToday'],
                        'text-neutral-400' => ! $cell['isToday'] && $cell['isPast'] && $cell['inMonth'],
                    ])>{{ $cell['day'] }}</span>
                    {{-- Phones: dots. Larger screens: titles. --}}
                    @if ($count)
                        <span class="flex flex-wrap gap-0.5 px-0.5 sm:hidden">
                            @foreach ($cell['events']->take(3) as $event)
                                <span class="h-1.5 w-1.5 rounded-full {{ $event->starts_at->isPast() ? 'bg-neutral-300' : ($event->state_id ? 'bg-brand' : 'bg-accent') }}"></span>
                            @endforeach
                        </span>
                        <span class="hidden flex-col gap-0.5 sm:flex">
                            @foreach ($cell['events']->take(2) as $event)
                                <span class="truncate rounded px-1.5 py-0.5 text-[11px] leading-tight font-medium {{ $chip($event) }}">{{ $event->starts_at->setTimezone($tz)->format('g:ia') }} {{ $event->title }}</span>
                            @endforeach
                            @if ($count > 2)<span class="px-1.5 text-[11px] text-neutral-500">+{{ $count - 2 }} more</span>@endif
                        </span>
                    @endif
                </button>
            @endforeach
        @endforeach
    </div>

    <div class="px-4 py-4 sm:px-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-display font-bold" x-text="day ? new Date(day + 'T12:00:00').toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' }) : 'Events in {{ $calendar->month->format('F') }}'">Events in {{ $calendar->month->format('F') }}</h3>
            <button type="button" class="text-sm font-medium text-brand" x-show="day" x-cloak x-on:click="day = null">Show the whole month</button>
        </div>
        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-500">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-accent"></span> National</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-brand"></span> State</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-neutral-300"></span> Past</span>
        </div>
        <ul class="mt-3 divide-y divide-line">
            @forelse ($calendar->monthEvents as $event)
                @php $at = $event->starts_at->setTimezone($tz); $past = $event->starts_at->lt($now); @endphp
                <li class="flex gap-4 py-3" x-show="!day || day === '{{ $at->format('Y-m-d') }}'">
                    <div @class(['flex h-14 w-12 shrink-0 flex-col items-center justify-center rounded-xl', 'bg-neutral-100 text-neutral-500' => $past, 'bg-brand/10 text-brand-dark' => ! $past])>
                        <span class="text-[11px] font-semibold uppercase">{{ $at->format('M') }}</span>
                        <span class="text-xl leading-none font-bold">{{ $at->format('j') }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            @if ($showState)<span class="badge {{ $event->state_id ? 'bg-brand/10 text-brand-dark' : 'bg-accent/20 text-[#7a5b00]' }}">{{ $event->state?->name ?? 'National' }}</span>@endif
                            @if ($past)<span class="badge bg-neutral-100 text-neutral-600">Past</span>@else<span class="badge bg-brand text-white">Upcoming</span>@endif
                        </div>
                        <h4 @class(['mt-1 font-display font-bold', 'text-neutral-600' => $past])>{{ $event->title }}</h4>
                        <p class="text-sm text-neutral-600">{{ $at->format('l, g:ia') }}{{ $event->venue ? ' · '.$event->venue : '' }}</p>
                        @if ($event->description)<p class="mt-1 text-sm whitespace-pre-line text-neutral-700">{{ $event->description }}</p>@endif
                    </div>
                </li>
            @empty
                <li class="py-3 text-sm text-neutral-600">No events this month.</li>
            @endforelse
        </ul>
    </div>
</section>
