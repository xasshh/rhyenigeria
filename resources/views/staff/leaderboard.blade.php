@php
    $isAdmin = auth()->user()->isSuperAdmin();
    $medal = [1 => 'bg-accent text-ink', 2 => 'bg-neutral-300 text-ink', 3 => 'bg-[#d79b62] text-white'];
    $count = count($states);
@endphp
<x-layouts.app title="Leaderboard">
    <p class="text-sm text-neutral-500">{{ $isAdmin ? (site_state_code() ? 'Across the state' : 'Every state, nationally') : 'How your state compares' }}</p>
    <h1 class="font-display text-2xl font-bold tracking-tight sm:text-[28px]">Leaderboard</h1>

    {{-- Metric picker --}}
    <nav class="mt-4 flex gap-1.5 overflow-x-auto pb-1 [scrollbar-width:none]" aria-label="Rank by">
        @foreach ($metrics as $key => $label)
            <a href="{{ request()->fullUrlWithQuery(['by' => $key === 'members' ? null : $key]) }}" @class(['shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium', 'bg-brand text-white' => $metric === $key, 'border border-line bg-white text-neutral-700 hover:bg-surface' => $metric !== $key]) @if ($metric === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($mine)
        <section class="mt-4 overflow-hidden rounded-2xl bg-gradient-to-br from-brand-dark to-brand p-5 text-white">
            <p class="text-xs font-semibold tracking-[0.14em] text-white/75 uppercase">{{ $mine['name'] }} · {{ $metrics[$metric] }}</p>
            <div class="mt-2 flex flex-wrap items-end gap-x-6 gap-y-2">
                <p class="font-display text-5xl leading-none font-extrabold">#{{ $mine['rank'] }}<span class="text-xl font-bold text-white/70"> of {{ $count }}</span></p>
                <p class="text-lg font-semibold tabular-nums">{{ number_format($mine[$metric]) }} {{ strtolower($metrics[$metric]) }}</p>
            </div>
            <p class="mt-3 text-sm text-white/90">
                @if ($mine['rank'] === 1)
                    You're top of the table. Keep the lead.
                @elseif ($chasing)
                    <strong>{{ number_format($chasing[$metric] - $mine[$metric]) }}</strong> behind {{ $chasing['name'] }} (#{{ $chasing['rank'] }}).
                    @if ($leader && $leader['id'] !== $chasing['id']) {{ $leader['name'] }} leads with {{ number_format($leader[$metric]) }}. @endif
                @endif
            </p>
        </section>
    @elseif (! $isAdmin)
        <p class="panel mt-4 p-5 text-neutral-700">Your account has no state assigned yet. Ask a super admin to assign your state to see where it ranks.</p>
    @endif

    <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
        {{-- States --}}
        <section class="panel overflow-hidden">
            <h2 class="px-5 pt-5 font-display font-bold">States</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-y border-line bg-surface/60 text-left text-xs tracking-wide text-neutral-500 uppercase">
                        <tr>
                            <th class="w-12 py-2 pl-5">#</th>
                            <th class="py-2 pr-3">State</th>
                            @foreach ($metrics as $key => $label)
                                <th @class(['py-2 pr-4 text-right whitespace-nowrap', 'text-brand-dark' => $metric === $key, 'max-2xl:hidden' => $metric !== $key && $key !== 'members'])>{{ $key === 'activities' ? 'Activities' : $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($states as $row)
                            @php $isMine = $mine && $row['id'] === $mine['id']; @endphp
                            <tr @class(['bg-brand/10 font-semibold' => $isMine]) @if ($isMine) id="my-state" aria-current="true" @endif>
                                <td class="py-2.5 pl-5">
                                    <span @class(['inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold', $medal[$row['rank']] ?? 'text-neutral-500'])>{{ $row['rank'] }}</span>
                                </td>
                                <td class="py-2.5 pr-3">
                                    @if ($isAdmin)
                                        <a href="{{ route('admin.states.show', $row['id']) }}" class="hover:text-brand hover:underline">{{ $row['name'] }}</a>
                                    @else
                                        <span class="whitespace-nowrap">{{ $row['name'] }}@if ($isMine) <span class="badge ml-1 bg-brand text-white">You</span>@endif</span>
                                    @endif
                                </td>
                                @foreach ($metrics as $key => $label)
                                    <td @class(['py-2.5 pr-4 text-right tabular-nums', 'font-bold text-ink' => $metric === $key, 'text-neutral-600' => $metric !== $key, 'max-2xl:hidden' => $metric !== $key && $key !== 'members'])>{{ number_format($row[$key]) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="px-5 py-3 text-xs text-neutral-500">Weeks start on Monday, Nigerian time. Activities are {{ site_term('canvassers') }}' door-to-door visits, event check-ins and reports in the last {{ $activityDays }} days.</p>
        </section>

        <div class="space-y-4">
            {{-- Top recruiters --}}
            <section class="panel p-5">
                <h2 class="font-display font-bold">Top recruiters</h2>
                <p class="text-xs text-neutral-500">{{ $isAdmin ? (site_state_code() ? 'Across the state' : 'Nationally') : 'In your state' }} · sign-ups through their invite link</p>
                <ol class="mt-3 space-y-2">
                    @forelse ($recruiters as $i => $r)
                        <li class="flex items-center gap-3 text-sm">
                            <span @class(['inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold', $medal[$i + 1] ?? 'bg-surface text-neutral-500'])>{{ $i + 1 }}</span>
                            <a href="{{ staff_route('members.show', $r['id']) }}" class="min-w-0 flex-1 truncate hover:underline">{{ $r['name'] }}@if ($isAdmin && $r['state'])<span class="text-neutral-500"> · {{ $r['state'] }}</span>@endif</a>
                            <span class="font-semibold tabular-nums">{{ number_format($r['total']) }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-neutral-600">No referrals yet.</li>
                    @endforelse
                </ol>
            </section>

            {{-- Top canvassers --}}
            <section class="panel p-5">
                <h2 class="font-display font-bold">Top {{ site_term('canvassers') }}</h2>
                <p class="text-xs text-neutral-500">{{ $isAdmin ? (site_state_code() ? 'Across the state' : 'Nationally') : 'In your state' }} · field activities, last {{ $activityDays }} days</p>
                <ol class="mt-3 space-y-2">
                    @forelse ($canvassers as $i => $c)
                        <li class="flex items-center gap-3 text-sm">
                            <span @class(['inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold', $medal[$i + 1] ?? 'bg-surface text-neutral-500'])>{{ $i + 1 }}</span>
                            <a href="{{ staff_route('members.show', $c['id']) }}" class="min-w-0 flex-1 truncate hover:underline">{{ $c['name'] }}@if ($isAdmin && $c['state'])<span class="text-neutral-500"> · {{ $c['state'] }}</span>@endif</a>
                            <span class="text-right tabular-nums"><span class="font-semibold">{{ number_format($c['activities']) }}</span>@if ($c['households'])<span class="block text-[11px] text-neutral-500">{{ number_format($c['households']) }} households</span>@endif</span>
                        </li>
                    @empty
                        <li class="text-sm text-neutral-600">No field activity logged yet.</li>
                    @endforelse
                </ol>
            </section>
        </div>
    </div>
</x-layouts.app>
