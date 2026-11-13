{{-- The RHYE logo as a tilted sticker, like a graffiti tag slapped on a poster. Position it with classes. --}}
<span {{ $attributes->merge(['class' => 'pointer-events-none absolute z-10 inline-flex -rotate-[9deg] items-center rounded-xl bg-white px-2.5 py-1.5 shadow-[4px_5px_0_rgba(15,42,28,0.9)] ring-[3px] ring-accent sm:rounded-2xl sm:px-3 sm:py-2']) }} aria-hidden="true">
    <img src="{{ asset('images/logo-rhye.png') }}" alt="" class="h-7 w-auto sm:h-10" width="600" height="272" loading="lazy">
</span>
