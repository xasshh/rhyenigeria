@props([
    'map',                 // AreaMap::states() or ::lgas(): viewBox + areas
    'url',                 // JSON counts endpoint
    'key' => 'code',       // which area field the counts are keyed by
    'linkKey' => null,     // which area field goes into the link (defaults to `key`)
    'href',                // link for one area, with __KEY__ standing in for the key
    'action' => null,      // what clicking does, read to screen readers and shown in the label
    'noun' => 'members',
    'label' => 'Map',
])
<div {{ $attributes->merge(['class' => 'relative']) }} x-data="areaMap(@js(['url' => $url, 'noun' => $noun]))">
    @if ($map['areas'] === [])
        <p class="rounded-2xl border border-dashed border-line p-8 text-center text-sm text-neutral-500">Boundaries for this map have not been loaded yet.</p>
    @else
        <div class="relative" x-ref="frame" x-on:pointerleave="leave()">
            <svg viewBox="{{ $map['viewBox'] }}" class="h-auto w-full" role="group" aria-label="{{ $label }}"
                 x-on:pointerover="over($event)" x-on:pointermove="move($event)" x-on:focusin="focus($event)" x-on:focusout="tip = null" x-on:click="tap($event)">
                @foreach ($map['areas'] as $area)
                    <a href="{{ str_replace('__KEY__', rawurlencode((string) $area[$linkKey ?? $key]), $href) }}" data-area="{{ $area[$key] }}" data-name="{{ $area['name'] }}" data-action="{{ $action ? str_replace(':name', $area['name'], $action) : '' }}"
                       class="group outline-none" aria-label="{{ $area['name'] }}">
                        <path d="{{ $area['d'] }}" fill="#e9eee9" stroke="#fff" stroke-width="1.2" stroke-linejoin="round" vector-effect="non-scaling-stroke"
                              class="transition-[fill,opacity] duration-300 group-hover:opacity-80 group-focus-visible:stroke-[#0f2a1c] group-focus-visible:[stroke-width:2.5]" />
                    </a>
                @endforeach
            </svg>

            <div x-show="tip" x-cloak x-transition.opacity.duration.150ms role="status"
                 class="pointer-events-none absolute z-10 w-max max-w-[220px] -translate-x-1/2 rounded-xl bg-forest px-3.5 py-2.5 text-white shadow-lg"
                 :style="tip && `left:${tip.x}px; top:${tip.y}px; transform: translate(-50%, ${tip.above ? 'calc(-100% - 14px)' : '18px'})`">
                <p class="text-[15px] font-bold" x-text="tip?.name"></p>
                <p class="text-sm text-mint" x-text="tip?.text"></p>
                <p class="mt-1 text-xs text-white/70" x-show="tip?.action" x-text="tip?.action"></p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-neutral-600">
            <div class="flex items-center gap-2" x-show="legend.length" x-cloak>
                <span>Fewer</span>
                <template x-for="step in legend" :key="step.colour"><span class="h-3 w-7 rounded-sm" :style="`background:${step.colour}`" :title="`${step.from}+`"></span></template>
                <span>More {{ $noun }}</span>
            </div>
            <p x-show="loaded && !failed && !max" x-cloak>No {{ $noun }} yet. Be the first.</p>
            <p x-show="failed" x-cloak>Member counts could not be loaded right now.</p>
            <p x-show="!loaded">Loading member counts…</p>
            {{ $slot }}
        </div>
    @endif
</div>
