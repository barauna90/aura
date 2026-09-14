@props(['series', 'max' => 100, 'label'])
@php
    $w = 600; $h = 180; $pad = 28;
    $n = max(1, max(array_map(fn ($s) => count($s['points']), $series) ?: [1]));
    $x = fn ($i) => $pad + ($n <= 1 ? ($w - 2 * $pad) / 2 : $i * ($w - 2 * $pad) / ($n - 1));
    $y = fn ($v) => $h - $pad - (max(0, min($max, $v)) / $max) * ($h - 2 * $pad);
@endphp
<figure>
    <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full" role="img" aria-label="{{ $label }}">
        @foreach([0, .25, .5, .75, 1] as $f)
            <line x1="{{ $pad }}" x2="{{ $w - $pad }}" y1="{{ $y($f * $max) }}" y2="{{ $y($f * $max) }}" stroke="var(--color-border)" />
            <text x="4" y="{{ $y($f * $max) + 4 }}" font-size="10" fill="var(--color-muted)">{{ round($f * $max) }}</text>
        @endforeach
        @foreach($series as $s)
            <polyline fill="none" stroke="{{ $s['color'] }}" stroke-width="2" points="{{ collect($s['points'])->map(fn ($p, $i) => $x($i).','.$y($p))->implode(' ') }}" />
            @foreach($s['points'] as $i => $p)<circle cx="{{ $x($i) }}" cy="{{ $y($p) }}" r="3" fill="{{ $s['color'] }}" />@endforeach
        @endforeach
    </svg>
    <figcaption class="mt-2 flex flex-wrap gap-3 text-xs text-muted">@foreach($series as $s)<span class="flex items-center gap-1"><span class="inline-block h-2 w-3 rounded-sm" style="background: {{ $s['color'] }}"></span>{{ $s['name'] }}</span>@endforeach</figcaption>
    <table class="sr-only"><caption>{{ $label }}</caption><tbody>@foreach($series as $s)<tr><th scope="row">{{ $s['name'] }}</th>@foreach($s['points'] as $p)<td>{{ $p }}</td>@endforeach</tr>@endforeach</tbody></table>
</figure>
