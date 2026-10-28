@extends('reports.pdf.layout', ['coverSubtitle' => 'Statement for ' . $investor['full_name']])

@php
    $n = fn ($v) => number_format((float) $v, 0);
    $pct = fn ($v) => number_format((float) $v, 1) . '%';
    $c = $capitalInfo;
    $t = $totals;
    $trends = array_filter([
        ['title' => 'Monthly performance', 'label' => 'Month', 'rows' => $monthlyTrend],
        ['title' => 'Yearly performance (last 5 years)', 'label' => 'Year', 'rows' => $yearlyTrend],
        count($dailyTrend) <= 31 ? ['title' => 'Daily performance', 'label' => 'Date', 'rows' => $dailyTrend] : null,
    ]);
@endphp

@section('content')
    <div class="section">
        <div class="section-head">
            <div class="t">Investor profile</div>
            <div class="s">Contact details and current wallet position</div>
        </div>

        <table class="two-col">
            <tr>
                <td class="left">
                    <table class="tbl">
                        <tbody>
                            <tr><td class="muted">Full name</td><td class="b">{{ $investor['full_name'] }}</td></tr>
                            <tr><td class="muted">Investor ID</td><td>#{{ $investor['id'] }}</td></tr>
                            <tr><td class="muted">Phone</td><td>{{ $investor['phone'] ?: '—' }}</td></tr>
                            <tr><td class="muted">Email</td><td>{{ $investor['email'] ?: '—' }}</td></tr>
                            <tr>
                                <td class="muted">Status</td>
                                <td><span class="badge {{ $investor['status'] === 'active' ? 'green' : '' }}">{{ $investor['status'] }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </td>
                <td class="right">
                    <table class="tbl">
                        <tbody>
                            <tr><td class="muted">Total invested</td><td class="r b violet">{{ $n($c['total_invested']) }} AFN</td></tr>
                            <tr><td class="muted">Capital balance</td><td class="r b">{{ $n($c['capital_balance']) }} AFN</td></tr>
                            <tr><td class="muted">Lifetime profit</td><td class="r b green">{{ $n($c['total_profit']) }} AFN</td></tr>
                            <tr><td class="muted">Total withdrawn</td><td class="r b red">{{ $n($c['total_withdrawn']) }} AFN</td></tr>
                            <tr class="total"><td>Profit balance (withdrawable)</td><td class="r">{{ $n($c['profit_balance']) }} AFN</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <div class="sub-head">Period summary · {{ $filters['label'] }}</div>
        @include('reports.pdf.kpis', ['items' => [
            ['k' => 'Sales of your goods', 'v' => $n($t['sales']), 's' => "{$t['orders']} orders · {$t['quantity']} units", 'tone' => ''],
            ['k' => 'Your profit', 'v' => $n($t['investor_profit']), 's' => 'Earned in this period', 'tone' => 'blue'],
            ['k' => 'Store share', 'v' => $n($t['store_profit']), 's' => 'Store commission', 'tone' => 'green'],
            ['k' => 'Margin', 'v' => $pct($t['margin']), 's' => 'Total profit / sales', 'tone' => 'violet'],
            ['k' => 'Profit paid', 'v' => $n($entries['paid']['total']), 's' => "{$entries['paid']['count']} entries", 'tone' => 'green'],
            ['k' => 'Profit pending', 'v' => $n($entries['pending']['total']), 's' => "{$entries['pending']['count']} entries", 'tone' => 'amber'],
            ['k' => 'Cancelled', 'v' => $n($entries['cancelled']['total']), 's' => "{$entries['cancelled']['count']} entries", 'tone' => 'red'],
            ['k' => 'Cost of goods sold', 'v' => $n($t['cost']), 's' => 'Your capital returned', 'tone' => ''],
        ]])
    </div>

    <div class="section">
        <div class="sub-head">Products sold from your capital</div>
        @if (count($purchases) === 0)
            <div class="no-data">No sales from this investor's goods in the selected period</div>
        @else
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="c" style="width: 4%;">#</th>
                        <th style="width: 27%;">Product</th>
                        <th style="width: 12%;">Batch</th>
                        <th class="c" style="width: 6%;">Orders</th>
                        <th class="c" style="width: 5%;">Qty</th>
                        <th class="r">Sales</th>
                        <th class="r">Cost</th>
                        <th class="r">Your profit</th>
                        <th class="r">Store share</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchases as $i => $r)
                        <tr>
                            <td class="c muted">{{ $i + 1 }}</td>
                            <td class="b">{{ $r['product_name'] }}</td>
                            <td class="small">{{ $r['batch_code'] }}</td>
                            <td class="c">{{ $r['orders'] }}</td>
                            <td class="c">{{ $r['quantity'] }}</td>
                            <td class="r">{{ $n($r['sales']) }}</td>
                            <td class="r muted">{{ $n($r['cost']) }}</td>
                            <td class="r b blue">{{ $n($r['investor_profit']) }}</td>
                            <td class="r green">{{ $n($r['store_profit']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td></td>
                        <td colspan="2">TOTAL</td>
                        <td class="c">{{ $t['orders'] }}</td>
                        <td class="c">{{ $t['quantity'] }}</td>
                        <td class="r">{{ $n($t['sales']) }}</td>
                        <td class="r">{{ $n($t['cost']) }}</td>
                        <td class="r">{{ $n($t['investor_profit']) }}</td>
                        <td class="r">{{ $n($t['store_profit']) }}</td>
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
                            <th style="width: 16%;">{{ $trend['label'] }}</th>
                            <th class="c">Orders</th>
                            <th class="c">Qty</th>
                            <th class="r">Sales</th>
                            <th class="r">Cost</th>
                            <th class="r">Your profit</th>
                            <th class="r">Store share</th>
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
                                <td class="r b blue">{{ $n($r['investor_profit']) }}</td>
                                <td class="r green">{{ $n($r['store_profit']) }}</td>
                                <td class="r">{{ $pct($r['margin']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total">
                            <td>TOTAL</td>
                            <td class="c">{{ array_sum(array_column($trend['rows'], 'orders')) }}</td>
                            <td class="c">{{ array_sum(array_column($trend['rows'], 'quantity')) }}</td>
                            <td class="r">{{ $n(array_sum(array_column($trend['rows'], 'sales'))) }}</td>
                            <td class="r">{{ $n(array_sum(array_column($trend['rows'], 'cost'))) }}</td>
                            <td class="r">{{ $n(array_sum(array_column($trend['rows'], 'investor_profit'))) }}</td>
                            <td class="r">{{ $n(array_sum(array_column($trend['rows'], 'store_profit'))) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    <div class="note-box">
        "Your profit" is the investor share recorded on each sale of goods bought with this investor's capital.
        The profit balance is the amount currently available for withdrawal.
    </div>
@endsection
