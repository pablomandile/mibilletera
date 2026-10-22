@php
    // Mismo formato que resources/js/money.js: "$ 1.234,56".
    $money = fn ($v) => $symbol.' '.number_format((float) $v, 2, ',', '.');
    $maxTotal = max($totals['income'], $totals['expense'], 0.01);
    $maxDay = max(collect($days)->max('expense'), 0.01);
    $activeDays = collect($days)->filter(fn ($d) => $d['income'] > 0 || $d['expense'] > 0);
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Mi Billetera · {{ $label }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #27272a; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 12px; margin: 0 0 8px; padding-bottom: 4px; border-bottom: 1px solid #e4e4e7; }
        .muted { color: #71717a; }
        .header { margin-bottom: 16px; }
        .section { margin-bottom: 18px; page-break-inside: avoid; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 3px 4px; vertical-align: middle; }
        th { text-align: left; font-weight: bold; color: #71717a; font-size: 9px; border-bottom: 1px solid #e4e4e7; }
        .right { text-align: right; }
        .income { color: #16a34a; }
        .expense { color: #e11d48; }
        .summary td { width: 33.3%; text-align: center; padding: 8px 4px; border: 1px solid #e4e4e7; }
        .summary .value { font-size: 14px; font-weight: bold; margin-top: 2px; }
        .dot { display: inline-block; width: 8px; height: 8px; border-radius: 4px; margin-right: 4px; }
        .track { background: #f4f4f5; height: 8px; border-radius: 4px; }
        .bar { height: 8px; border-radius: 4px; }
        .bigbar { height: 16px; border-radius: 4px; }
        .daily { table-layout: fixed; }
        .daily td { vertical-align: bottom; text-align: center; padding: 0 1px; }
        .daily .col { background: #f43f5e; border-radius: 2px 2px 0 0; }
        .daily .lbl { font-size: 7px; color: #71717a; padding-top: 2px; }
        .striped tr:nth-child(even) td { background: #fafafa; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Mi Billetera · {{ $label }}</h1>
        <div class="muted">{{ $userName }} · Generado el {{ $generatedAt }} · Montos en {{ $symbol }}</div>
    </div>

    {{-- Resumen --}}
    <div class="section">
        <table class="summary">
            <tr>
                <td>
                    <div class="muted">Ingresos</div>
                    <div class="value income">{{ $money($totals['income']) }}</div>
                    @if ($totals['incomeDelta'] !== null)
                        <div class="{{ $totals['incomeDelta'] >= 0 ? 'income' : 'expense' }}">{{ $totals['incomeDelta'] >= 0 ? '▲' : '▼' }} {{ abs($totals['incomeDelta']) }}% vs mes anterior</div>
                    @endif
                </td>
                <td>
                    <div class="muted">Gastos</div>
                    <div class="value expense">{{ $money($totals['expense']) }}</div>
                    @if ($totals['expenseDelta'] !== null)
                        <div class="{{ $totals['expenseDelta'] <= 0 ? 'income' : 'expense' }}">{{ $totals['expenseDelta'] >= 0 ? '▲' : '▼' }} {{ abs($totals['expenseDelta']) }}% vs mes anterior</div>
                    @endif
                </td>
                <td>
                    <div class="muted">Balance</div>
                    <div class="value {{ $totals['balance'] < 0 ? 'expense' : '' }}">{{ $money($totals['balance']) }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Ingresos vs gastos --}}
    <div class="section">
        <h2>Ingresos vs gastos</h2>
        <table>
            @foreach ([['Ingresos', $totals['income'], '#22c55e'], ['Gastos', $totals['expense'], '#f43f5e']] as [$name, $value, $color])
                <tr>
                    <td style="width: 60px;">{{ $name }}</td>
                    <td><div class="bigbar" style="width: {{ max($value / $maxTotal * 100, 0.5) }}%; background: {{ $color }};"></div></td>
                    <td class="right" style="width: 110px;">{{ $money($value) }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    @php
        $breakdowns = [['Gastos por categoría', $expenseByCategory, 'Sin gastos este mes.']];
        if ($hasSupercategories) {
            $breakdowns[] = ['A vuelo de pájaro (por supercategoría)', $bySupercategory, 'Sin gastos este mes.'];
        }
        $breakdowns[] = ['Ingresos por categoría', $incomeByCategory, 'Sin ingresos este mes.'];
    @endphp

    @foreach ($breakdowns as [$title, $rows, $empty])
        <div class="section">
            <h2>{{ $title }}</h2>
            @if (count($rows))
                <table>
                    @foreach ($rows as $c)
                        <tr>
                            <td style="width: 150px;"><span class="dot" style="background: {{ $c['color'] }};"></span>{{ $c['name'] }}</td>
                            <td><div class="track"><div class="bar" style="width: {{ $c['pct'] }}%; background: {{ $c['color'] }};"></div></div></td>
                            <td class="right muted" style="width: 40px;">{{ number_format($c['pct'], 1, ',', '.') }}%</td>
                            <td class="right" style="width: 100px;">{{ $money($c['total']) }}</td>
                        </tr>
                    @endforeach
                </table>
            @else
                <p class="muted">{{ $empty }}</p>
            @endif
        </div>
    @endforeach

    {{-- Día por día --}}
    <div class="section">
        <h2>Gasto día por día</h2>
        <table class="daily">
            <tr>
                @foreach ($days as $d)
                    <td style="height: 90px;">
                        @if ($d['expense'] > 0)
                            <div class="col" style="height: {{ max(round($d['expense'] / $maxDay * 85), 1) }}px;"></div>
                        @endif
                    </td>
                @endforeach
            </tr>
            <tr>
                @foreach ($days as $d)
                    <td class="lbl">{{ $d['day'] }}</td>
                @endforeach
            </tr>
        </table>
    </div>

    {{-- Sin page-break-inside: con muchos días la tabla ocupa más de una hoja. --}}
    <div style="margin-bottom: 18px;">
        <h2>Días con movimientos</h2>
        @if ($activeDays->isNotEmpty())
            <table class="striped">
                <tr><th>Día</th><th class="right">Ingresos</th><th class="right">Gastos</th></tr>
                @foreach ($activeDays as $d)
                    <tr>
                        <td>{{ ucfirst($d['label']) }}</td>
                        <td class="right income">{{ $d['income'] > 0 ? $money($d['income']) : '—' }}</td>
                        <td class="right expense">{{ $d['expense'] > 0 ? $money($d['expense']) : '—' }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <p class="muted">Sin movimientos este mes.</p>
        @endif
    </div>
</body>
</html>
