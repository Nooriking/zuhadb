@extends('reports.pdf.layout', ['coverSubtitle' => 'Goods 100% owned by the store (no investor capital)'])

@php
    $n = fn ($v) => number_format((float) $v, 0);
    $pct = fn ($v) => number_format((float) $v, 1) . '%';
    $t = $totals;
    $net = $t['store_profit'] - $totalExpenses;
    $trends = array_filter([
        ['title' => 'Monthly performance', 'label' => 'Month', 'rows' => $monthlyTrend],
        ['title' => 'Yearly performance (last 5 years)', 'label' => 'Year', 'rows' => $yearlyTrend],
        count($dailyTrend) <= 31 ? ['title' => 'Daily performance', 'label' => 'Date', 'rows' => $dailyTrend] : null,
    ]);
@endphp

@section('content')
    <div class="section">
        <div class="section-head">
            <div class="t">Store-owned goods</div>
            <div class="s">Profit from products bought with the store's own money · {{ $filters['label'] }}</div>
        </div>

        @include('reports.pdf.kpis', ['items' => [
            ['k' => 'Sales', 'v' => $n($t['sales']), 's' => "{$t['orders']} orders · {$t['quantity']} units", 'tone' => ''],
            ['k' => 'Store profit', 'v' => $n($t['store_profit']), 's' => 'Margin ' . $pct($t['margin']), 'tone' => 'green'],
            ['k' => 'All expenses', 'v' => $n($totalExpenses), 's' => 'Operating expenses in period', 'tone' => 'red'],
            ['k' => 'Net after expenses', 'v' => $n($net), 's' => 'Store profit − expenses', 'tone' => $net < 0 ? 'red' : 'violet'],
        ]])
    </div>

    <div class="section">
        <div class="sub-head">Products sold</div>
        @if (count($purchases) === 0)
            <div class="no-data">No store-owned goods sold in this period</div>
        @else
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="c" style="width: 4%;">#</th>
                        <th style="width: 36%;">Product</th>
                        <th class="c">Orders</th>
                        <th class="c">Qty</th>
                        <th class="r">Sales</th>
                        <th class="r">Cost</th>
                        <th class="r">Profit</th>
                        <th class="r">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchases as $i => $r)
                        <tr>
                            <td class="c muted">{{ $i + 1 }}</td>
                            <td class="b">{{ $r['product_name'] }}</td>
                            <td class="c">{{ $r['orders'] }}</td>
                            <td class="c">{{ $r['quantity'] }}</td>
                            <td class="r">{{ $n($r['sales']) }}</td>
                            <td class="r muted">{{ $n($r['cost']) }}</td>
                            <td class="r b green">{{ $n($r['store_profit']) }}</td>
                            <td class="r">{{ $pct($r['margin']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td></td>
                        <td>TOTAL</td>
                        <td class="c">{{ $t['orders'] }}</td>
                        <td class="c">{{ $t['quantity'] }}</td>
                        <td class="r">{{ $n($t['sales']) }}</td>
                        <td class="r">{{ $n($t['cost']) }}</td>
                        <td class="r">{{ $n($t['store_profit']) }}</td>
                        <td class="r">{{ $pct($t['margin']) }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>

    @foreach ($trends as $trend)
        <div class="section">
            <div class="sub-head">{{ $trend['title'] }}</div>
            @if (count($trend['rows']) === 0)
                <div class="no-data">No data</div>
            @else
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="width: 18%;">{{ $trend['label'] }}</th>
                            <th class="c">Orders</th>
                            <th class="c">Qty</th>
                            <th class="r">Sales</th>
                            <th class="r">Cost</th>
                            <th class="r">Store profit</th>
                            <th class="r">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trend['rows'] as $r)
                            <tr>
                                <td class="b">{{ $r['period'] }}</td>
                                <td class="c">{{ $r['orders'] }}</td>
                                <td class="c">{{ $r['quantity'] }}</td>
                                <td class="r">{{ $n($r['sales']) }}</td>
                                <td class="r muted">{{ $n($r['cost']) }}</td>
                                <td class="r b green">{{ $n($r['store_profit']) }}</td>
                                <td class="r">{{ $pct($r['margin']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total">
                            <td>TOTAL</td>
                            <td class="c">{{ array_sum(array_column($trend['rows'], 'orders')) }}</td>
                            <td class="c">{{ array_sum(array_column($trend['rows'], 'quantity')) }}</td>
                            <td class="r">{{ $n(array_sum(array_column($trend['rows'], 'sales'))) }}</td>
                            <td class="r">{{ $n(array_sum(array_column($trend['rows'], 'cost'))) }}</td>
                            <td class="r">{{ $n(array_sum(array_column($trend['rows'], 'store_profit'))) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
@endsection
