@extends('reports.pdf.layout', ['coverSubtitle' => count($sections) === 1 ? 'Detailed section report' : 'Overview · Sales · Products · Investors'])

@php
    $n = fn ($v) => number_format((float) $v, 0);
    $pct = fn ($v) => number_format((float) $v, 1) . '%';
    $s = $summary;
    $periodLabel = ['daily' => 'Date', 'monthly' => 'Month', 'yearly' => 'Year'][$filters['group_by']];
    $first = true;
    $breakClass = function () use (&$first) {
        $class = $first ? '' : 'break';
        $first = false;

        return $class;
    };
    $productLimit = 150;
@endphp

@section('content')

{{-- ═══════════════════════════════════════════════════
 |  OVERVIEW
 ═══════════════════════════════════════════════════ --}}
@if (in_array('overview', $sections))
    <div class="section {{ $breakClass() }}">
        <div class="section-head">
            <div class="t">1 · Profit Overview</div>
            <div class="s">Sales, cost, profit split and expenses for {{ $filters['label'] }}</div>
        </div>

        @include('reports.pdf.kpis', ['items' => [
            ['k' => 'Total sales', 'v' => $n($s['total_sales']), 's' => "{$s['total_orders']} orders · {$s['total_quantity']} units", 'tone' => ''],
            ['k' => 'Gross profit', 'v' => $n($s['total_profit']), 's' => 'Margin ' . $pct($s['margin']), 'tone' => 'green'],
            ['k' => 'Investor profit', 'v' => $n($s['investor_profit']), 's' => 'Payable to investors', 'tone' => 'blue'],
            ['k' => 'Store profit', 'v' => $n($s['store_profit']), 's' => 'Before expenses', 'tone' => 'green'],
            ['k' => 'Expenses', 'v' => $n($s['total_expenses']), 's' => count($expenses['by_category']) . ' categories', 'tone' => 'red'],
            ['k' => 'Store net profit', 'v' => $n($s['store_net_profit']), 's' => 'Store profit − expenses', 'tone' => 'violet'],
            ['k' => 'Returns', 'v' => $n($s['total_returns']), 's' => "{$s['returns_count']} completed returns", 'tone' => 'amber'],
            ['k' => 'Average order', 'v' => $n($s['avg_order']), 's' => "{$s['customers']} registered customers", 'tone' => ''],
        ]])

        <table class="two-col">
            <tr>
                <td class="left">
                    <div class="sub-head">Profit statement <span class="note">(AFN)</span></div>
                    <table class="tbl">
                        <tbody>
                            <tr><td>Sales revenue</td><td class="r b">{{ $n($s['total_sales']) }}</td></tr>
                            <tr><td>Less: cost of goods sold</td><td class="r red">({{ $n($s['total_cost']) }})</td></tr>
                            <tr class="total"><td>Gross profit</td><td class="r">{{ $n($s['total_profit']) }}</td></tr>
                            <tr><td>&nbsp;&nbsp;Investors' share</td><td class="r blue">({{ $n($s['investor_profit']) }})</td></tr>
                            <tr><td>&nbsp;&nbsp;Store share</td><td class="r green">{{ $n($s['store_profit']) }}</td></tr>
                            <tr><td>Less: operating expenses</td><td class="r red">({{ $n($s['total_expenses']) }})</td></tr>
                            <tr class="total"><td>Store net profit</td><td class="r">{{ $n($s['store_net_profit']) }}</td></tr>
                        </tbody>
                    </table>
                    <div class="note-box">
                        Discounts given: <b>{{ $n($s['total_discount']) }}</b> AFN ·
                        Returns are already deducted from sales.
                    </div>
                </td>
                <td class="right">
                    <div class="sub-head">Expenses by category</div>
                    @if (count($expenses['by_category']) === 0)
                        <div class="no-data">No expenses in this period</div>
                    @else
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="c">Count</th>
                                    <th class="r">Amount</th>
                                    <th style="width: 26%;">Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($expenses['by_category'] as $e)
                                    @php $share = $expenses['total'] > 0 ? $e['total'] / $expenses['total'] * 100 : 0; @endphp
                                    <tr>
                                        <td class="b">{{ $e['category'] }}</td>
                                        <td class="c">{{ $e['count'] }}</td>
                                        <td class="r red">{{ $n($e['total']) }}</td>
                                        <td>
                                            <span class="small">{{ $pct($share) }}</span>
                                            <div class="bar"><div style="width: {{ min(100, round($share)) }}%; background: #dc2626;"></div></div>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr class="total">
                                    <td>TOTAL</td>
                                    <td class="c">{{ array_sum(array_column($expenses['by_category'], 'count')) }}</td>
                                    <td class="r">{{ $n($expenses['total']) }}</td>
                                    <td>100%</td>
                                </tr>
                            </tbody>
                        </table>
                    @endif
                </td>
            </tr>
        </table>

        <div class="sub-head">{{ ucfirst($filters['group_by']) }} breakdown</div>
        @if (count($breakdown) === 0)
            <div class="no-data">No sales data for this period</div>
        @else
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 11%;">{{ $periodLabel }}</th>
                        <th class="c" style="width: 6%;">Orders</th>
                        <th class="c" style="width: 6%;">Qty</th>
                        <th class="r">Sales</th>
                        <th class="r">Cost</th>
                        <th class="r">Investor</th>
                        <th class="r">Store</th>
                        <th class="r">Gross</th>
                        <th class="r">Expenses</th>
                        <th class="r">Store net</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($breakdown as $r)
                        <tr>
                            <td class="b">{{ $r['period'] }}</td>
                            <td class="c">{{ $r['orders'] }}</td>
                            <td class="c">{{ $r['quantity'] }}</td>
                            <td class="r">{{ $n($r['sales']) }}</td>
                            <td class="r muted">{{ $n($r['cost']) }}</td>
                            <td class="r blue">{{ $n($r['investor_profit']) }}</td>
                            <td class="r green">{{ $n($r['store_profit']) }}</td>
                            <td class="r b">{{ $n($r['total_profit']) }}</td>
                            <td class="r red">{{ $r['expenses'] > 0 ? $n($r['expenses']) : '—' }}</td>
                            <td class="r b {{ $r['store_net'] < 0 ? 'red' : 'violet' }}">{{ $n($r['store_net']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td>TOTAL</td>
                        <td class="c">{{ array_sum(array_column($breakdown, 'orders')) }}</td>
                        <td class="c">{{ array_sum(array_column($breakdown, 'quantity')) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($breakdown, 'sales'))) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($breakdown, 'cost'))) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($breakdown, 'investor_profit'))) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($breakdown, 'store_profit'))) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($breakdown, 'total_profit'))) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($breakdown, 'expenses'))) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($breakdown, 'store_net'))) }}</td>
                    </tr>
                </tbody>
            </table>
        @endif

        @if (count($exchangeRates) > 0)
            <div class="sub-head">USD exchange rate by month <span class="note">(AFN per 1 USD)</span></div>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="r">Average</th>
                        <th class="r">Minimum</th>
                        <th class="r">Maximum</th>
                        <th class="c">Days recorded</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exchangeRates as $r)
                        <tr>
                            <td class="b">{{ $r['month'] }}</td>
                            <td class="r b">{{ number_format($r['avg_rate'], 2) }}</td>
                            <td class="r muted">{{ number_format($r['min_rate'], 2) }}</td>
                            <td class="r muted">{{ number_format($r['max_rate'], 2) }}</td>
                            <td class="c">{{ $r['days'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif

{{-- ═══════════════════════════════════════════════════
 |  SALES
 ═══════════════════════════════════════════════════ --}}
@if (in_array('sales', $sections))
    <div class="section {{ $breakClass() }}">
        <div class="section-head">
            <div class="t">2 · Sales Report</div>
            <div class="s">Performance by branch, seller, payment method and customer</div>
        </div>

        @include('reports.pdf.kpis', ['items' => [
            ['k' => 'Net sales', 'v' => $n($s['total_sales']), 's' => "{$s['total_orders']} completed orders", 'tone' => ''],
            ['k' => 'Gross profit', 'v' => $n($s['total_profit']), 's' => 'Margin ' . $pct($s['margin']), 'tone' => 'green'],
            ['k' => 'Returns', 'v' => $n($s['total_returns']), 's' => "{$s['returns_count']} returns", 'tone' => 'red'],
            ['k' => 'Discounts', 'v' => $n($s['total_discount']), 's' => 'Given to customers', 'tone' => 'amber'],
            ['k' => 'Average order', 'v' => $n($s['avg_order']), 's' => 'AFN per order', 'tone' => 'violet'],
            ['k' => 'Units sold', 'v' => $n($s['total_quantity']), 's' => 'Pieces', 'tone' => 'blue'],
            ['k' => 'Customers', 'v' => $s['customers'], 's' => 'Registered buyers', 'tone' => ''],
            ['k' => 'Sellers', 'v' => count($sales['by_seller']), 's' => 'Users who sold', 'tone' => ''],
        ]])

        <div class="sub-head">Sales by branch</div>
        @include('reports.pdf.entity-table', ['rows' => $sales['by_branch'], 'nameLabel' => 'Branch'])

        <div class="sub-head">Sales by seller <span class="note">(user who recorded the sale)</span></div>
        @include('reports.pdf.entity-table', ['rows' => $sales['by_seller'], 'nameLabel' => 'Seller', 'subKey' => 'role'])

        @if (count($sales['seller_matrix']['periods']) > 1)
            <div class="sub-head">Seller sales per {{ $filters['matrix_by'] === 'yearly' ? 'year' : 'month' }} <span class="note">(AFN)</span></div>
            @include('reports.pdf.matrix', ['matrix' => $sales['seller_matrix'], 'nameLabel' => 'Seller'])
        @endif

        <div class="sub-head">Payment methods</div>
        @include('reports.pdf.entity-table', ['rows' => $sales['by_payment'], 'nameLabel' => 'Method', 'showQty' => false])

        <div class="sub-head">Top customers <span class="note">(up to 30, by sales)</span></div>
        @include('reports.pdf.entity-table', ['rows' => $sales['by_customer'], 'nameLabel' => 'Customer', 'showQty' => false, 'subKey' => 'phone'])
    </div>
@endif

{{-- ═══════════════════════════════════════════════════
 |  PRODUCTS
 ═══════════════════════════════════════════════════ --}}
@if (in_array('products', $sections))
    <div class="section {{ $breakClass() }}">
        <div class="section-head">
            <div class="t">3 · Product Report</div>
            <div class="s">Which products and brands sold, and how much profit each one made</div>
        </div>

        @include('reports.pdf.kpis', ['items' => [
            ['k' => 'Products sold', 'v' => $products['stats']['variants_sold'], 's' => 'Distinct variants', 'tone' => ''],
            ['k' => 'Units sold', 'v' => $n($s['total_quantity']), 's' => 'Pieces', 'tone' => 'blue'],
            ['k' => 'Best seller', 'v' => $products['stats']['top_product'] ?? '—', 's' => 'By sales amount', 'tone' => 'amber'],
            ['k' => 'Top brand', 'v' => $products['stats']['top_brand'] ?? '—', 's' => "{$products['stats']['brands_sold']} brands sold", 'tone' => 'violet'],
        ]])

        <div class="sub-head">Sales by brand</div>
        @include('reports.pdf.entity-table', ['rows' => $products['by_brand'], 'nameLabel' => 'Brand'])

        <div class="sub-head">
            Sales by product
            @if (count($products['by_product']) > $productLimit)
                <span class="note">(top {{ $productLimit }} of {{ count($products['by_product']) }} — export CSV for the full list)</span>
            @endif
        </div>
        @if (count($products['by_product']) === 0)
            <div class="no-data">No products sold in this period</div>
        @else
            @php $rows = array_slice($products['by_product'], 0, $productLimit); @endphp
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="c" style="width: 4%;">#</th>
                        <th style="width: 25%;">Product</th>
                        <th style="width: 11%;">Brand</th>
                        <th class="c" style="width: 5%;">Qty</th>
                        <th class="r">Sales</th>
                        <th class="r">Cost</th>
                        <th class="r">Investor</th>
                        <th class="r">Store</th>
                        <th class="r">Profit</th>
                        <th class="r" style="width: 7%;">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $r)
                        <tr>
                            <td class="c muted">{{ $i + 1 }}</td>
                            <td class="b">{{ $r['name'] }}</td>
                            <td class="small">{{ $r['brand'] }}</td>
                            <td class="c">{{ $r['quantity'] }}</td>
                            <td class="r">{{ $n($r['sales']) }}</td>
                            <td class="r muted">{{ $n($r['cost']) }}</td>
                            <td class="r blue">{{ $n($r['investor_profit']) }}</td>
                            <td class="r green">{{ $n($r['store_profit']) }}</td>
                            <td class="r b">{{ $n($r['total_profit']) }}</td>
                            <td class="r">{{ $pct($r['margin']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td></td>
                        <td colspan="2">TOTAL ({{ count($products['by_product']) }} products)</td>
                        <td class="c">{{ array_sum(array_column($products['by_product'], 'quantity')) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($products['by_product'], 'sales'))) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($products['by_product'], 'cost'))) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($products['by_product'], 'investor_profit'))) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($products['by_product'], 'store_profit'))) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($products['by_product'], 'total_profit'))) }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
            <div class="note-box">Product figures are based on sold line items (before returns).</div>
        @endif
    </div>
@endif

{{-- ═══════════════════════════════════════════════════
 |  INVESTORS
 ═══════════════════════════════════════════════════ --}}
@if (in_array('investors', $sections))
    @php $inv = $investors; $st = $inv['stats']; @endphp
    <div class="section {{ $breakClass() }}">
        <div class="section-head">
            <div class="t">4 · Investor Report</div>
            <div class="s">Profit earned by each investor's goods, payouts and wallet balances</div>
        </div>

        @include('reports.pdf.kpis', ['items' => [
            ['k' => 'Investors', 'v' => $st['active_investors'] . ' / ' . $st['total_investors'], 's' => 'Active in period / total', 'tone' => ''],
            ['k' => 'Investor profit', 'v' => $n($st['investor_profit']), 's' => 'Earned in period', 'tone' => 'blue'],
            ['k' => 'Profit paid', 'v' => $n($st['entries_paid']), 's' => 'Entries marked paid', 'tone' => 'green'],
            ['k' => 'Profit pending', 'v' => $n($st['entries_pending']), 's' => 'Waiting for payout', 'tone' => 'amber'],
            ['k' => 'Total invested', 'v' => $n($st['total_invested']), 's' => 'Lifetime capital', 'tone' => 'violet'],
            ['k' => 'Capital balance', 'v' => $n($st['capital_balance']), 's' => 'Unused capital now', 'tone' => ''],
            ['k' => 'Profit balance', 'v' => $n($st['profit_balance']), 's' => 'Withdrawable now', 'tone' => 'green'],
            ['k' => 'Store own goods', 'v' => $n($inv['store']['store_profit']), 's' => 'Profit without investor', 'tone' => 'green'],
        ]])

        <div class="sub-head">Profit per investor <span class="note">({{ $filters['label'] }})</span></div>
        @if (count($inv['rows']) === 0)
            <div class="no-data">No investors registered</div>
        @else
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="c" style="width: 4%;">#</th>
                        <th style="width: 20%;">Investor</th>
                        <th class="c" style="width: 6%;">Orders</th>
                        <th class="c" style="width: 5%;">Qty</th>
                        <th class="r">Sales</th>
                        <th class="r">Investor profit</th>
                        <th class="r">Store share</th>
                        <th style="width: 11%;">Share</th>
                        <th class="r">Paid</th>
                        <th class="r">Pending</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($inv['rows'] as $i => $r)
                        <tr>
                            <td class="c muted">{{ $i + 1 }}</td>
                            <td class="b">
                                {{ $r['name'] }}
                                <div class="small">{{ $r['phone'] ?: '—' }} · <span class="badge {{ $r['status'] === 'active' ? 'green' : '' }}">{{ $r['status'] }}</span></div>
                            </td>
                            <td class="c">{{ $r['orders'] }}</td>
                            <td class="c">{{ $r['quantity'] }}</td>
                            <td class="r">{{ $n($r['sales']) }}</td>
                            <td class="r b blue">{{ $n($r['investor_profit']) }}</td>
                            <td class="r green">{{ $n($r['store_profit']) }}</td>
                            <td>
                                <span class="small">{{ $pct($r['share']) }}</span>
                                <div class="bar"><div style="width: {{ min(100, round($r['share'])) }}%; background: #2563eb;"></div></div>
                            </td>
                            <td class="r green">{{ $n($r['entries_paid']) }}</td>
                            <td class="r {{ $r['entries_pending'] > 0 ? 'red' : 'muted' }}">{{ $n($r['entries_pending']) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td class="c muted">—</td>
                        <td class="b green">Store (own goods)<div class="small">No investor capital</div></td>
                        <td class="c">{{ $inv['store']['orders'] }}</td>
                        <td class="c">{{ $inv['store']['quantity'] }}</td>
                        <td class="r">{{ $n($inv['store']['sales']) }}</td>
                        <td class="r muted">—</td>
                        <td class="r green">{{ $n($inv['store']['store_profit']) }}</td>
                        <td></td>
                        <td class="r muted">—</td>
                        <td class="r muted">—</td>
                    </tr>
                    <tr class="total">
                        <td></td>
                        <td>TOTAL</td>
                        <td class="c">—</td>
                        <td class="c">{{ array_sum(array_column($inv['rows'], 'quantity')) + $inv['store']['quantity'] }}</td>
                        <td class="r">{{ $n(array_sum(array_column($inv['rows'], 'sales')) + $inv['store']['sales']) }}</td>
                        <td class="r">{{ $n($st['investor_profit']) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($inv['rows'], 'store_profit')) + $inv['store']['store_profit']) }}</td>
                        <td>100%</td>
                        <td class="r">{{ $n($st['entries_paid']) }}</td>
                        <td class="r">{{ $n($st['entries_pending']) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="sub-head">Investor wallets <span class="note">(current balances, lifetime)</span></div>
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="c" style="width: 4%;">#</th>
                        <th style="width: 24%;">Investor</th>
                        <th class="r">Total invested</th>
                        <th class="r">Capital balance</th>
                        <th class="r">Lifetime profit</th>
                        <th class="r">Withdrawn</th>
                        <th class="r">Profit balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($inv['rows'] as $i => $r)
                        <tr>
                            <td class="c muted">{{ $i + 1 }}</td>
                            <td class="b">{{ $r['name'] }}</td>
                            <td class="r violet">{{ $n($r['total_invested']) }}</td>
                            <td class="r">{{ $n($r['capital_balance']) }}</td>
                            <td class="r green">{{ $n($r['lifetime_profit']) }}</td>
                            <td class="r red">{{ $n($r['total_withdrawn']) }}</td>
                            <td class="r b green">{{ $n($r['profit_balance']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td></td>
                        <td>TOTAL</td>
                        <td class="r">{{ $n($st['total_invested']) }}</td>
                        <td class="r">{{ $n($st['capital_balance']) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($inv['rows'], 'lifetime_profit'))) }}</td>
                        <td class="r">{{ $n(array_sum(array_column($inv['rows'], 'total_withdrawn'))) }}</td>
                        <td class="r">{{ $n($st['profit_balance']) }}</td>
                    </tr>
                </tbody>
            </table>
        @endif

        @if (count($inv['matrix']['periods']) > 1)
            <div class="sub-head">Investor profit per {{ $filters['matrix_by'] === 'yearly' ? 'year' : 'month' }} <span class="note">(AFN)</span></div>
            @include('reports.pdf.matrix', ['matrix' => $inv['matrix'], 'nameLabel' => 'Investor'])
        @endif
    </div>
@endif

@endsection
