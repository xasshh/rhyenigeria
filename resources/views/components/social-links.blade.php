@props(['size' => 'md'])
@php
    $accounts = [
        'facebook' => ['Facebook', 'facebook'],
        'instagram' => ['Instagram', 'instagram'],
        'tiktok' => ['TikTok', 'tiktok'],
        'x' => ['X (Twitter)', 'x-logo'],
    ];
    $links = collect($accounts)->filter(fn ($a, $key) => filled(config("membership.social.{$key}")));
@endphp
@if ($links->isNotEmpty())
    <ul {{ $attributes->merge(['class' => 'flex items-center gap-2']) }} aria-label="Renewed Hope Youth Engagement on social media">
        @foreach ($links as $key => [$label, $icon])
            <li>
                <a href="{{ config("membership.social.{$key}") }}" target="_blank" rel="noopener" aria-label="{{ $label }}" title="{{ $label }}"
                   @class(['flex items-center justify-center rounded-full transition-colors', 'h-10 w-10 bg-white/10 text-white hover:bg-accent hover:text-forest' => $size === 'md', 'h-7 w-7 text-white/80 hover:text-accent' => $size === 'sm'])>
                    <x-icon :name="$icon" @class(['h-[18px] w-[18px]' => $size === 'md', 'h-4 w-4' => $size === 'sm']) />
                </a>
            </li>
        @endforeach
    </ul>
@endif
