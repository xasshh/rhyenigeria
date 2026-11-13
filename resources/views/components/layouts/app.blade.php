@props(['title' => null, 'charts' => false, 'heading' => null, 'map' => false])
@php
    $user = auth()->user();
    $nav = match (true) {
        $user->isSuperAdmin() => [
            ['admin.dashboard', 'Overview', 'chart', 'admin.dashboard'],
            ['admin.live', 'Live map', 'map', 'admin.live'],
            ['admin.members.index', 'Members', 'users', 'admin.members.*'],
            ['admin.canvassers.index', 'Canvassers', 'badge', 'admin.canvassers.*'],
            ['admin.coordinators.index', 'Coordinators', 'user-plus', 'admin.coordinators.*'],
            ['admin.empowerment.index', 'Empowerment', 'gift', 'admin.empowerment.*'],
            ['admin.posts.index', 'News & events', 'megaphone', 'admin.posts.*'],
            ['admin.settings.edit', 'Settings', 'cog', 'admin.settings.*'],
        ],
        $user->isCoordinator() => [
            ['coordinator.dashboard', 'Dashboard', 'chart', 'coordinator.dashboard'],
            ['coordinator.live', 'Live map', 'map', 'coordinator.live'],
            ['coordinator.members.index', 'Members', 'users', 'coordinator.members.*'],
            ['coordinator.canvassers.index', 'Canvassers', 'badge', 'coordinator.canvassers.*'],
            ['coordinator.empowerment.index', 'Empowerment', 'gift', 'coordinator.empowerment.*'],
            ['coordinator.posts.index', 'News & events', 'megaphone', 'coordinator.posts.*'],
        ],
        default => array_values(array_filter([
            ['membership.home', 'Home', 'home', 'membership.home'],
            ['membership.card', 'My card', 'card', 'membership.card'],
            $user->isApprovedCanvasser() ? ['membership.field.show', 'Field work', 'pin', 'membership.field.*'] : null,
            ['membership.events', 'Events & news', 'calendar', 'membership.events'],
            ['membership.empowerment', 'Empowerment', 'gift', 'membership.empowerment'],
            ['membership.dp', 'Campaign DP', 'camera', 'membership.dp'],
        ])),
    };
    // A canvasser with a shift running, on any other page: tracking only runs on the Field work page.
    $shiftRunning = $user->isMember() && ! request()->routeIs('membership.field.*') && $user->activeShift()->exists();
    $isMember = $user->isMember();
    $subtitle = $isMember
        ? ($user->memberProfile?->roleLabel() ?? 'Member').($user->state ? ', '.$user->state->name : '')
        : ($user->isSuperAdmin() ? 'Super admin' : 'Coordinator'.($user->state ? ', '.$user->state->name : ''));
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp
<x-layouts.base :title="$title" :charts="$charts" :map="$map" noindex>
    <div class="lg:flex">
        {{-- Sidebar (desktop) --}}
        <aside class="sticky top-0 hidden h-dvh w-64 shrink-0 flex-col border-r border-line bg-white lg:flex">
            <div class="px-5 py-5"><x-logo /></div>
            <nav class="flex-1 space-y-1 px-3" aria-label="Dashboard">
                @foreach ($nav as [$route, $label, $icon, $pattern])
                    <a href="{{ route($route) }}" @class([
                        'flex items-center gap-3 rounded-xl px-3 py-2.5 text-[15px] font-medium transition-colors',
                        'bg-brand/10 text-brand-dark ring-1 ring-brand/30' => request()->routeIs($pattern),
                        'text-neutral-700 hover:bg-surface' => ! request()->routeIs($pattern),
                    ]) @if (request()->routeIs($pattern)) aria-current="page" @endif>
                        <x-icon :name="$icon" /> {{ $label }}
                    </a>
                @endforeach
            </nav>
            <div class="m-3 rounded-2xl border border-line p-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand/10 text-sm font-bold text-brand-dark">{{ $initials }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $user->name }}</p>
                        <p class="truncate text-xs text-neutral-500">{{ $subtitle }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button class="flex w-full items-center justify-center gap-2 rounded-lg py-1.5 text-sm text-neutral-600 hover:bg-surface"><x-icon name="logout" class="h-4 w-4" /> Sign out</button>
                </form>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            {{-- Top bar (mobile) --}}
            <header class="sticky top-0 z-30 border-b border-line bg-white/95 backdrop-blur lg:hidden">
                <div class="flex items-center justify-between px-4 py-2.5">
                    <x-logo />
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm text-neutral-600" aria-label="Sign out"><x-icon name="logout" class="h-4 w-4" /> <span class="max-[360px]:hidden">Sign out</span></button>
                    </form>
                </div>
                @unless ($isMember)
                    <nav class="flex gap-1 overflow-x-auto px-3 pb-2 [scrollbar-width:none]" aria-label="Dashboard">
                        @foreach ($nav as [$route, $label, $icon, $pattern])
                            <a href="{{ route($route) }}" @class(['shrink-0 rounded-full px-3 py-1.5 text-sm font-medium', 'bg-brand text-white' => request()->routeIs($pattern), 'bg-surface text-neutral-700' => ! request()->routeIs($pattern)])>{{ $label }}</a>
                        @endforeach
                    </nav>
                @endunless
            </header>

            <main @class(['mx-auto max-w-5xl px-4 pt-5 sm:px-6 lg:px-10 lg:pt-9', 'pb-28 lg:pb-12' => $isMember, 'pb-12' => ! $isMember])>
                @if ($heading)
                    <h1 class="font-display text-2xl font-bold tracking-tight sm:text-[28px]">{{ $heading }}</h1>
                @endif
                @if ($shiftRunning)
                    <a href="{{ route('membership.field.show') }}" class="mt-4 flex items-center gap-3 rounded-2xl border border-accent/50 bg-amber-soft p-4 text-sm text-amber-ink">
                        <span class="h-2.5 w-2.5 animate-pulse rounded-full bg-accent"></span>
                        <span class="flex-1"><strong>Your shift is running.</strong> Location is only shared while the Field work page is open. Tap to go back, or end your shift there.</span>
                        <x-icon name="arrow-right" class="h-4 w-4" />
                    </a>
                @endif
                <x-flash class="mt-4" />
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Bottom navigation (members, mobile) --}}
    @if ($isMember)
        <nav @class(['fixed inset-x-0 bottom-0 z-30 grid border-t border-line bg-white pb-[env(safe-area-inset-bottom)] lg:hidden', 'grid-cols-6' => count($nav) === 6, 'grid-cols-5' => count($nav) !== 6]) aria-label="Membership">
            @foreach ($nav as [$route, $label, $icon, $pattern])
                <a href="{{ route($route) }}" @class(['flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium', 'text-brand' => request()->routeIs($pattern), 'text-neutral-500' => ! request()->routeIs($pattern)]) @if (request()->routeIs($pattern)) aria-current="page" @endif>
                    <x-icon :name="$icon" class="h-[22px] w-[22px]" />
                    <span class="leading-tight">{{ ['Events & news' => 'Events', 'Campaign DP' => 'My DP', 'Empowerment' => 'Empower', 'Field work' => 'Field'][$label] ?? $label }}</span>
                </a>
            @endforeach
        </nav>
    @endif
</x-layouts.base>
