<a href="{{ route('home') }}" {{ $attributes->merge(['class' => 'inline-flex items-center']) }} aria-label="{{ config('site.name') }} home">
    <img src="{{ asset(config('site.logo.0')) }}" alt="{{ config('site.short') }} — {{ config('site.name') }}" class="h-11 w-auto" width="{{ config('site.logo.1') }}" height="{{ config('site.logo.2') }}">
</a>
