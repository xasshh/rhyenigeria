@php $pct = $of ? round($n / $of * 100) : 0; @endphp
<div>
    <div class="flex justify-between"><span>{{ $label }}</span><span class="tabular-nums text-neutral-500">{{ number_format($n) }} · {{ $pct }}%</span></div>
    <div class="mt-1 h-1.5 rounded-full bg-surface"><div class="h-full rounded-full bg-brand" style="width: {{ $pct }}%"></div></div>
</div>
