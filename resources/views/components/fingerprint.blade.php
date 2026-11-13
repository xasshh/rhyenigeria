@props(['rings' => 15, 'stroke' => 2.2])
@php
    /*
     * An original thumbprint drawn from code: nested ovals that open towards the
     * bottom as they grow, like the ridges of an inked voting thumb. Each ridge
     * gets a few small breaks so it reads as a print rather than a target.
     */
    $cx = 100;
    $cy = 118;
    $ridges = [];

    for ($i = 0; $i < $rings; $i++) {
        $rx = 6 + $i * 6.1;
        $ry = 8 + $i * 7.7;
        $c = $cy - $i * 1.1;
        $gap = $i < 3 ? 26 : min(140, 26 + ($i - 2) * 10);
        $from = deg2rad(270 + $gap / 2);
        $to = deg2rad(270 - $gap / 2 + 360);
        $point = fn (float $t) => [round($cx + $rx * cos($t), 2), round($c - $ry * sin($t), 2)];
        [$x1, $y1] = $point($from);
        [$x2, $y2] = $point($to);
        $large = (360 - $gap) > 180 ? 1 : 0;

        $a = ($i * 29) % 38 + 16;
        $b = ($i * 17) % 27 + 12;
        $ridges[] = [
            'd' => "M{$x1} {$y1}A{$rx} {$ry} 0 {$large} 0 {$x2} {$y2}",
            'dash' => "{$a} 2.6 {$b} 2.2 100",
        ];
    }
@endphp
<svg {{ $attributes->merge(['class' => 'pointer-events-none']) }} viewBox="0 0 200 240" fill="none" stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" aria-hidden="true" focusable="false">
    <g transform="rotate(-9 100 120)">
        @foreach ($ridges as $ridge)
            <path d="{{ $ridge['d'] }}" pathLength="100" stroke-dasharray="{{ $ridge['dash'] }}" />
        @endforeach
    </g>
</svg>
