@props(['label', 'value', 'hint' => null, 'tone' => 'default'])
<div {{ $attributes->merge(['class' => 'panel p-4']) }}>
    <p class="text-xs font-medium tracking-wide text-neutral-500 uppercase">{{ $label }}</p>
    <p @class(['mt-1 text-2xl font-bold tabular-nums sm:text-3xl', 'text-ink' => $tone === 'default', 'text-brand' => $tone === 'brand', 'text-amber-600' => $tone === 'warn'])>{{ is_numeric($value) ? number_format($value) : $value }}</p>
    @if ($hint)<p class="mt-0.5 text-xs text-neutral-500">{{ $hint }}</p>@endif
</div>
