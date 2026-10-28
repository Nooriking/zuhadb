{{--
    Ranked performance table (branches, sellers, payment methods, customers, brands).
    $rows: metrics rows from ProfitReportController::metrics()
    $nameLabel: first column header
    $showQty: show the quantity column
    $subKey: optional row key rendered under the name (phone, role…)
--}}
@php
    $n = fn ($v) => number_format((float) $v, 0);
    $showQty = $showQty ?? true;
    $subKey = $subKey ?? null;
    $totalSales = array_sum(array_column($rows, 'sales'));
@endphp

@if (count($rows) === 0)
    <div class="no-data">No data for this period</div>
@else
    <table class="tbl">
        <thead>
            <tr>
                <th class="c" style="width: 4%;">#</th>
                <th style="width: {{ $showQty ? 26 : 32 }}%;">{{ $nameLabel }}</th>
                <th class="c" style="width: 7%;">Orders</th>
                @if ($showQty)
                    <th class="c" style="width: 6%;">Qty</th>
                @endif
                <th class="r" style="width: 13%;">Sales</th>
                <th class="r" style="width: 12%;">Cost</th>
                <th class="r" style="width: 12%;">Profit</th>
                <th class="r" style="width: 8%;">Margin</th>
                <th style="width: 12%;">Share of sales</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $row)
                @php $share = $totalSales > 0 ? $row['sales'] / $totalSales * 100 : 0; @endphp
                <tr>
                    <td class="c muted">{{ $i + 1 }}</td>
                    <td class="b">
                        {{ $row['name'] }}
                        @if ($subKey && ! empty($row[$subKey]))
                            <div class="small">{{ $row[$subKey] }}</div>
                        @endif
                    </td>
                    <td class="c">{{ $row['orders'] }}</td>
                    @if ($showQty)
                        <td class="c">{{ $row['quantity'] ?? '—' }}</td>
                    @endif
                    <td class="r b">{{ $n($row['sales']) }}</td>
                    <td class="r muted">{{ $n($row['cost']) }}</td>
                    <td class="r b green">{{ $n($row['total_profit']) }}</td>
                    <td class="r">{{ number_format($row['margin'], 1) }}%</td>
                    <td>
                        <span class="small">{{ number_format($share, 1) }}%</span>
                        <div class="bar"><div style="width: {{ min(100, round($share)) }}%;"></div></div>
                    </td>
                </tr>
            @endforeach
            <tr class="total">
                <td></td>
                <td>TOTAL</td>
                <td class="c">{{ array_sum(array_column($rows, 'orders')) }}</td>
                @if ($showQty)
                    <td class="c">{{ array_sum(array_map(fn ($r) => (int) ($r['quantity'] ?? 0), $rows)) }}</td>
                @endif
                <td class="r">{{ $n($totalSales) }}</td>
                <td class="r">{{ $n(array_sum(array_column($rows, 'cost'))) }}</td>
                <td class="r">{{ $n(array_sum(array_column($rows, 'total_profit'))) }}</td>
                <td class="r">
                    {{ $totalSales > 0 ? number_format(array_sum(array_column($rows, 'total_profit')) / $totalSales * 100, 1) : '0.0' }}%
                </td>
                <td>100%</td>
            </tr>
        </tbody>
    </table>
@endif
