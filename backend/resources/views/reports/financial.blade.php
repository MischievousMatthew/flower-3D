@php
    $fmt = function ($v, $type) {
        if ($v === null || $v === '') return '—';
        return match ($type) {
            'money' => ($v < 0 ? '-' : '') . '₱' . number_format(abs((float) $v), 2),
            'int' => number_format((float) $v),
            'percent' => rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%',
            'date' => \Carbon\Carbon::parse($v)->format('M j, Y'),
            default => (string) $v,
        };
    };
    $align = fn ($type) => in_array($type, ['money', 'int', 'percent'], true) ? 'num' : '';
    // Four tiles sit in one row; otherwise rows of three
    $perRow = count($r['summary']) === 4 ? 4 : 3;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $r['shop'] }} · {{ $r['title'] }}</title>
<style>
    @page { margin: 28px 32px 40px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5px; color: #1a202c; }
    h1 { font-size: 18px; margin: 0 0 2px; color: #2f855a; }
    h2 { font-size: 12px; margin: 18px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #c6f6d5; }
    .muted { color: #718096; }
    .note { margin: 6px 0 0; padding: 6px 8px; background: #fffaf0; border-left: 3px solid #f6ad55; color: #744210; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: .4px; color: #718096; background: #f7fdf9; padding: 6px; }
    th.num { text-align: right; }
    td { padding: 5px 6px; border-bottom: 1px solid #edf2f7; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .neg { color: #c53030; }
    .total td { font-weight: bold; border-top: 2px solid #c6f6d5; background: #f7fdf9; }
    tr.sub td { font-weight: bold; background: #f7fafc; }
    tr.kind-total td { font-weight: bold; font-size: 11px; background: #f0fff4; border-top: 2px solid #9ae6b4; }
    .kpis { table-layout: fixed; }
    .kpis td { border: 1px solid #e2e8f0; padding: 8px 10px; }
    .kpi-label { font-size: 8px; color: #718096; text-transform: uppercase; letter-spacing: .4px; }
    .kpi-value { font-size: 14px; font-weight: bold; margin-top: 3px; }
    .footer { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 7.5px; color: #a0aec0; text-align: center; }
</style>
</head>
<body>
    <div class="footer">{{ $r['shop'] }} · BloomCraft · {{ $r['title'] }} · {{ $r['range']['label'] }} · generated {{ $generated->format('M j, Y g:i A') }}</div>

    <h1>{{ $r['shop'] }}</h1>
    <div><b>{{ $r['title'] }}</b> · {{ $r['range']['label'] }}</div>
    @if ($r['subtitle'])<div class="muted">{{ $r['subtitle'] }}</div>@endif
    @foreach ($r['notes'] as $note)
        <div class="note">{{ $note }}</div>
    @endforeach

    <h2>Summary</h2>
    <table class="kpis">
        @foreach (array_chunk($r['summary'], $perRow) as $chunk)
            <tr>
                @foreach ($chunk as $s)
                    <td>
                        <div class="kpi-label">{{ $s[0] }}</div>
                        <div class="kpi-value {{ ($s[2] === 'money' && $s[1] < 0) ? 'neg' : '' }}">{{ $fmt($s[1], $s[2]) }}</div>
                        @if (!empty($s[3]))<div class="muted">{{ $s[3] }}</div>@endif
                    </td>
                @endforeach
                @for ($i = count($chunk); $i < $perRow; $i++)<td style="border:0"></td>@endfor
            </tr>
        @endforeach
    </table>

    @foreach ($r['tables'] as $table)
        <h2>{{ $table['title'] }}</h2>
        @if (!count($table['rows']))
            <div class="muted">Nothing in this period.</div>
        @else
            <table>
                <thead><tr>@foreach ($table['columns'] as $c)<th class="{{ $align($c[2]) }}">{{ $c[1] }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($table['rows'] as $row)
                        <tr class="{{ isset($row['kind']) ? ($row['kind'] === 'total' ? 'kind-total' : $row['kind']) : '' }}">
                            @foreach ($table['columns'] as $c)
                                @php $v = $row[$c[0]] ?? null; @endphp
                                <td class="{{ $align($c[2]) }} {{ ($c[2] === 'money' && is_numeric($v) && $v < 0) ? 'neg' : '' }}">{{ $fmt($v, $c[2]) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    @if ($table['totals'])
                        <tr class="total">
                            @foreach ($table['columns'] as $c)
                                @php $v = $table['totals'][$c[0]] ?? null; @endphp
                                <td class="{{ $align($c[2]) }}">{{ $v === null ? '' : $fmt($v, $c[2]) }}</td>
                            @endforeach
                        </tr>
                    @endif
                </tbody>
            </table>
        @endif
    @endforeach
</body>
</html>
