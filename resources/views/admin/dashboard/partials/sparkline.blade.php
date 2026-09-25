{{-- Tiny trend line. $points: list<int|float>; $class: text-* colour (stroke = currentColor). --}}
@php
    $n = count($points);
    $max = max(1, max($points));
    $w = 100;
    $h = 32;
    $coords = [];
    foreach (array_values($points) as $i => $v) {
        $coords[] = [round($i / max(1, $n - 1) * $w, 2), round($h - 2 - ($v / $max) * ($h - 6), 2)];
    }
    $line = implode(' ', array_map(fn ($c) => $c[0].','.$c[1], $coords));
    $area = 'M0,'.$h.' L'.implode(' L', array_map(fn ($c) => $c[0].','.$c[1], $coords)).' L'.$w.','.$h.' Z';
    [$lx, $ly] = end($coords);
@endphp
<svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" class="{{ $class }} mb-1 h-9 w-24 shrink-0 overflow-visible" aria-hidden="true">
    <path d="{{ $area }}" fill="currentColor" fill-opacity="0.1" />
    <polyline points="{{ $line }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
</svg>
