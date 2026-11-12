@props(['title' => null, 'width' => 'max-w-xl', 'noindex' => false])
<x-layouts.base :title="$title" :noindex="$noindex">
    <header class="border-b border-line bg-white">
        <div class="mx-auto flex {{ $width }} items-center justify-between px-4 py-3">
            <x-logo />
            @auth
                <a href="{{ auth()->user()->homeRoute() }}" class="text-sm font-semibold text-brand">My dashboard</a>
            @else
                @unless (request()->routeIs('login'))
                    <a href="{{ route('login') }}" class="text-sm font-semibold text-brand">Sign in</a>
                @endunless
            @endauth
        </div>
    </header>
    <main class="mx-auto {{ $width }} px-4 pt-7 pb-16 sm:pt-10">
        {{ $slot }}
    </main>
</x-layouts.base>
