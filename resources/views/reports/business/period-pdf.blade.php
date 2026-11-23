@php
    /** @var array $report */
    $meta = $report['meta'] ?? [];
    $currency = $meta['currency'] ?? 'AFN';
    $canProfit = (bool) ($meta['can_view_profit'] ?? false);
    $fmt = static function ($n) use ($currency): string {
        if ($n === null) {
            return '—';
        }
        return number_format((float) $n, 2).' '.$currency;
    };
    $sections = $report['sections'] ?? [];
    $has = static fn (string $s) => in_array($s, $sections, true);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['company'] ?? 'ZUHA' }} — {{ $meta['report_label'] ?? 'Report' }}</title>
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #1a1a1a;
            line-height: 1.35;
        }
        h1 { font-size: 14pt; margin: 0 0 2px; font-weight: 700; }
        h2 {
            font-size: 11pt;
            margin: 14px 0 6px;
            padding-bottom: 3px;
            border-bottom: 1px solid #333;
            font-weight: 700;
        }
        h3 { font-size: 9.5pt; margin: 10px 0 4px; font-weight: 700; }
        .muted { color: #555; font-size: 8pt; }
        .header { margin-bottom: 10px; }
        .meta-row { margin: 1px 0; }
        .cards {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 4px;
        }
        .cards td {
            width: 25%;
            border: 1px solid #ccc;
            padding: 6px 7px;
            vertical-align: top;
        }
        .cards .label { font-size: 7.5pt; color: #555; text-transform: uppercase; letter-spacing: 0.02em; }
        .cards .value { font-size: 10pt; font-weight: 700; margin-top: 2px; }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0 8px;
            font-size: 8pt;
        }
        table.data th, table.data td {
            border: 1px solid #ddd;
            padding: 3px 5px;
            text-align: left;
        }
        table.data th {
            background: #f0f0f0;
            font-weight: 700;
        }
        table.data td.num, table.data th.num { text-align: right; }
        .note {
            font-size: 7.5pt;
            color: #444;
            margin: 4px 0 8px;
            padding: 5px 6px;
            border-left: 2px solid #999;
            background: #fafafa;
        }
        .footer {
            position: fixed;
            bottom: -8mm;
            left: 0;
            right: 0;
            font-size: 7.5pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 3px;
        }
        .footer .page:after { content: counter(page); }
        .kv { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .kv td { padding: 2px 4px; border-bottom: 1px solid #eee; }
        .kv td.k { width: 55%; color: #444; }
        .kv td.v { text-align: right; font-weight: 600; }
        .two-col { width: 100%; }
        .two-col td { width: 50%; vertical-align: top; padding-right: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $meta['company'] ?? 'ZUHA' }}</h1>
        <div style="font-size:11pt;font-weight:700;">{{ $meta['report_label'] ?? 'Business Report' }}</div>
        <div class="meta-row muted">Period: <strong>{{ $meta['period_label'] ?? '' }}</strong>
            ({{ $meta['period_from'] ?? '' }} → {{ $meta['period_to'] ?? '' }})</div>
        <div class="meta-row muted">Branch: {{ $meta['branch_name'] ?? 'All' }} · Generated: {{ $meta['generated_at'] ?? '' }} · Currency: {{ $currency }}</div>
    </div>

    @if($has('summary') && !empty($report['summary']))
        @php $s = $report['summary']; @endphp
        <h2>1. Business Summary</h2>
        <table class="cards">
            <tr>
                <td><div class="label">Total Sales (Net)</div><div class="value">{{ $fmt($s['total_sales'] ?? 0) }}</div></td>
                <td><div class="label">Total Purchases</div><div class="value">{{ $fmt($s['total_purchases'] ?? 0) }}</div></td>
                <td><div class="label">Gross Profit</div><div class="value">{{ $fmt($s['gross_profit'] ?? null) }}</div></td>
                <td><div class="label">Net Profit (Store)</div><div class="value">{{ $fmt($s['net_profit'] ?? null) }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Total Expenses</div><div class="value">{{ $fmt($s['total_expenses'] ?? 0) }}</div></td>
                <td><div class="label">Sales Returns</div><div class="value">{{ $fmt($s['sales_returns'] ?? 0) }}</div></td>
                <td><div class="label">Purchase Returns</div><div class="value">Not supported</div></td>
                <td><div class="label">Period Investment</div><div class="value">{{ $fmt($s['total_investment'] ?? 0) }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Investor Profit</div><div class="value">{{ $fmt($s['investor_profit'] ?? null) }}</div></td>
                <td><div class="label">Net Cash Flow</div><div class="value">{{ $fmt($s['net_cash_flow'] ?? 0) }}</div></td>
                <td><div class="label">Inventory Value (Cost)</div><div class="value">{{ $fmt($s['inventory_value'] ?? 0) }}</div></td>
                <td><div class="label">AR / AP</div><div class="value">{{ $fmt($s['outstanding_receivables'] ?? 0) }} / {{ $fmt($s['outstanding_payables'] ?? 0) }}</div></td>
            </tr>
        </table>
        <div class="note">
            <strong>Profit vs cash:</strong> {{ $s['distinction']['profit'] ?? '' }}
            <br>{{ $s['distinction']['cash_flow'] ?? '' }}
        </div>
    @endif

    @if($has('sales') && !empty($report['sales']))
        @php $sales = $report['sales']; @endphp
        <h2>2. Sales Report</h2>
        <table class="kv">
            <tr><td class="k">Transactions</td><td class="v">{{ $sales['transaction_count'] ?? 0 }}</td></tr>
            <tr><td class="k">Quantity sold</td><td class="v">{{ $sales['quantity_sold'] ?? 0 }}</td></tr>
            <tr><td class="k">Gross sales</td><td class="v">{{ $fmt($sales['gross_sales'] ?? 0) }}</td></tr>
            <tr><td class="k">Discounts</td><td class="v">{{ $fmt($sales['discounts'] ?? 0) }}</td></tr>
            <tr><td class="k">Sales returns (on period sales)</td><td class="v">{{ $fmt($sales['sales_returns'] ?? 0) }}</td></tr>
            <tr><td class="k">Net sales</td><td class="v">{{ $fmt($sales['net_sales'] ?? 0) }}</td></tr>
            <tr><td class="k">Average sale</td><td class="v">{{ $fmt($sales['average_sale'] ?? 0) }}</td></tr>
            <tr><td class="k">Collections in period</td><td class="v">{{ $fmt($sales['collections_total'] ?? 0) }}</td></tr>
            <tr><td class="k">Credit outstanding (on period sales)</td><td class="v">{{ $fmt($sales['credit_outstanding'] ?? 0) }}</td></tr>
            @if($canProfit)
                <tr><td class="k">COGS (FIFO)</td><td class="v">{{ $fmt($sales['cogs'] ?? 0) }}</td></tr>
                <tr><td class="k">Gross profit</td><td class="v">{{ $fmt($sales['gross_profit'] ?? 0) }}</td></tr>
            @endif
        </table>
        <div class="note">Net Sales = current post-return sale totals. Gross Sales = Net + returns linked to those sales (no double-count).</div>

        @if(!empty($sales['by_payment_method']))
            <h3>By payment method</h3>
            <table class="data">
                <thead><tr><th>Method</th><th class="num">Count</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @foreach($sales['by_payment_method'] as $row)
                    <tr>
                        <td>{{ ucfirst($row['method']) }}</td>
                        <td class="num">{{ $row['count'] }}</td>
                        <td class="num">{{ $fmt($row['total']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if(!empty($sales['by_branch']))
            <h3>By branch</h3>
            <table class="data">
                <thead>
                <tr>
                    <th>Branch</th>
                    <th class="num">Txns</th>
                    <th class="num">Net sales</th>
                    @if($canProfit)<th class="num">Gross profit</th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach($sales['by_branch'] as $row)
                    <tr>
                        <td>{{ $row['branch_name'] }}</td>
                        <td class="num">{{ $row['count'] }}</td>
                        <td class="num">{{ $fmt($row['net_sales']) }}</td>
                        @if($canProfit)<td class="num">{{ $fmt($row['gross_profit']) }}</td>@endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if($has('purchases') && !empty($report['purchases']))
        @php $p = $report['purchases']; @endphp
        <h2>3. Purchase Report</h2>
        <table class="kv">
            <tr><td class="k">Purchase batches</td><td class="v">{{ $p['transaction_count'] ?? 0 }}</td></tr>
            <tr><td class="k">Quantity purchased</td><td class="v">{{ $p['total_quantity'] ?? 0 }}</td></tr>
            <tr><td class="k">Net purchase cost</td><td class="v">{{ $fmt($p['net_purchases'] ?? 0) }}</td></tr>
            <tr><td class="k">Supplier invoices (period)</td><td class="v">{{ $fmt($p['supplier_invoices_total'] ?? 0) }}</td></tr>
            <tr><td class="k">Invoices paid / due</td><td class="v">{{ $fmt($p['supplier_invoices_paid'] ?? 0) }} / {{ $fmt($p['supplier_invoices_due'] ?? 0) }}</td></tr>
            <tr><td class="k">Purchase returns</td><td class="v">Not supported</td></tr>
        </table>
        <div class="note">{{ $p['note'] ?? '' }}</div>

        @if(!empty($p['by_supplier']))
            <h3>Purchases by supplier</h3>
            <table class="data">
                <thead><tr><th>Supplier</th><th class="num">Invoices</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Due</th></tr></thead>
                <tbody>
                @foreach($p['by_supplier'] as $row)
                    <tr>
                        <td>{{ $row['supplier_name'] }}</td>
                        <td class="num">{{ $row['count'] }}</td>
                        <td class="num">{{ $fmt($row['total']) }}</td>
                        <td class="num">{{ $fmt($row['paid'] ?? 0) }}</td>
                        <td class="num">{{ $fmt($row['due'] ?? 0) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if(!empty($p['by_product']))
            <h3>Purchases by product (top)</h3>
            <table class="data">
                <thead><tr><th>Product</th><th>SKU</th><th class="num">Qty</th><th class="num">Cost</th></tr></thead>
                <tbody>
                @foreach(array_slice($p['by_product'], 0, 25) as $row)
                    <tr>
                        <td>{{ $row['product_name'] }}</td>
                        <td>{{ $row['sku'] }}</td>
                        <td class="num">{{ $row['qty'] }}</td>
                        <td class="num">{{ $fmt($row['total']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if($has('profit') && !empty($report['profit']))
        @php $pr = $report['profit']; @endphp
        <h2>4. Profit Report</h2>
        @if(!empty($pr['restricted']))
            <div class="note">{{ $pr['message'] ?? 'Restricted' }}</div>
        @else
            <table class="kv">
                <tr><td class="k">Gross sales</td><td class="v">{{ $fmt($pr['gross_sales'] ?? 0) }}</td></tr>
                <tr><td class="k">Net sales</td><td class="v">{{ $fmt($pr['net_sales'] ?? 0) }}</td></tr>
                <tr><td class="k">COGS (FIFO)</td><td class="v">{{ $fmt($pr['cogs'] ?? 0) }}</td></tr>
                <tr><td class="k">Gross profit</td><td class="v">{{ $fmt($pr['gross_profit'] ?? 0) }}</td></tr>
                <tr><td class="k">Investor profit allocation</td><td class="v">{{ $fmt($pr['investor_profit'] ?? 0) }}</td></tr>
                <tr><td class="k">Store profit</td><td class="v">{{ $fmt($pr['store_profit'] ?? 0) }}</td></tr>
                <tr><td class="k">Operating expenses</td><td class="v">{{ $fmt($pr['operating_expenses'] ?? 0) }}</td></tr>
                <tr><td class="k">Other income</td><td class="v">Not tracked</td></tr>
                <tr><td class="k">Net profit (store)</td><td class="v">{{ $fmt($pr['net_profit_store'] ?? 0) }}</td></tr>
            </table>
            @if(!empty($pr['formulas']))
                <div class="note">
                    @foreach($pr['formulas'] as $f)
                        {{ $f }}<br>
                    @endforeach
                </div>
            @endif
        @endif
    @endif

    @if($has('expenses') && !empty($report['expenses']))
        @php $ex = $report['expenses']; @endphp
        <h2>5. Expense Report</h2>
        <table class="kv">
            <tr><td class="k">Total expenses</td><td class="v">{{ $fmt($ex['total'] ?? 0) }}</td></tr>
            <tr><td class="k">Expense count</td><td class="v">{{ $ex['count'] ?? 0 }}</td></tr>
        </table>
        @if(!empty($ex['by_category']))
            <h3>By category</h3>
            <table class="data">
                <thead><tr><th>Category</th><th class="num">Count</th><th class="num">Total</th></tr></thead>
                <tbody>
                @foreach($ex['by_category'] as $row)
                    <tr>
                        <td>{{ $row['category'] }}</td>
                        <td class="num">{{ $row['count'] }}</td>
                        <td class="num">{{ $fmt($row['total']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
        @if(!empty($ex['by_payment_method']))
            <h3>By payment method</h3>
            <table class="data">
                <thead><tr><th>Method</th><th class="num">Count</th><th class="num">Total</th></tr></thead>
                <tbody>
                @foreach($ex['by_payment_method'] as $row)
                    <tr>
                        <td>{{ $row['method'] }}</td>
                        <td class="num">{{ $row['count'] }}</td>
                        <td class="num">{{ $fmt($row['total']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
        @if(!empty($ex['by_branch']))
            <h3>By branch</h3>
            <table class="data">
                <thead><tr><th>Branch</th><th class="num">Count</th><th class="num">Total</th></tr></thead>
                <tbody>
                @foreach($ex['by_branch'] as $row)
                    <tr>
                        <td>{{ $row['branch_name'] }}</td>
                        <td class="num">{{ $row['count'] }}</td>
                        <td class="num">{{ $fmt($row['total']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if($has('inventory') && !empty($report['inventory']))
        @php $inv = $report['inventory']; @endphp
        <h2>6. Inventory / Remaining Stock</h2>
        <div class="muted">As of {{ $inv['as_of'] ?? '' }} · {{ $inv['method'] ?? '' }}</div>
        <table class="kv">
            <tr><td class="k">Total quantity</td><td class="v">{{ $inv['total_quantity'] ?? 0 }}</td></tr>
            <tr><td class="k">Inventory cost value</td><td class="v">{{ $fmt($inv['total_cost_value'] ?? 0) }}</td></tr>
            <tr><td class="k">Potential sales value</td><td class="v">{{ $fmt($inv['potential_sales_value'] ?? 0) }}</td></tr>
            <tr><td class="k">Estimated potential profit</td><td class="v">{{ $fmt($inv['estimated_potential_profit'] ?? 0) }}</td></tr>
            <tr><td class="k">Low-stock / Out-of-stock SKUs</td><td class="v">{{ $inv['low_stock_count'] ?? 0 }} / {{ $inv['out_of_stock_count'] ?? 0 }}</td></tr>
        </table>
        @if(!empty($inv['items']))
            <table class="data">
                <thead>
                <tr>
                    <th>Product</th><th>SKU</th><th>Brand</th>
                    <th class="num">Qty</th><th class="num">Cost</th><th class="num">Sell</th><th class="num">Value</th>
                </tr>
                </thead>
                <tbody>
                @foreach(array_slice($inv['items'], 0, 80) as $row)
                    <tr>
                        <td>{{ $row['product_name'] }}</td>
                        <td>{{ $row['sku'] }}</td>
                        <td>{{ $row['category'] }}</td>
                        <td class="num">{{ $row['quantity'] }}</td>
                        <td class="num">{{ $fmt($row['cost_price']) }}</td>
                        <td class="num">{{ $fmt($row['selling_price']) }}</td>
                        <td class="num">{{ $fmt($row['inventory_value']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if(!empty($inv['items_truncated']))
                <div class="muted">Showing first {{ count(array_slice($inv['items'], 0, 80)) }} of {{ $inv['items_total'] ?? 0 }} products. Export with “include all” for full list.</div>
            @endif
        @endif
    @endif

    @if($has('best_sellers') && !empty($report['best_sellers']))
        @php $bs = $report['best_sellers']; @endphp
        <h2>7. Best-Selling Products</h2>
        <div class="muted">Ranked by net quantity sold (sold − returned in period).</div>
        <table class="data">
            <thead>
            <tr>
                <th>#</th><th>Product</th><th>SKU</th>
                <th class="num">Sold</th><th class="num">Returned</th><th class="num">Net</th>
                <th class="num">Revenue</th>
                @if($canProfit)<th class="num">Profit</th>@endif
            </tr>
            </thead>
            <tbody>
            @foreach($bs['products'] ?? [] as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['product_name'] }}</td>
                    <td>{{ $row['sku'] }}</td>
                    <td class="num">{{ $row['quantity_sold'] }}</td>
                    <td class="num">{{ $row['quantity_returned'] }}</td>
                    <td class="num">{{ $row['net_quantity_sold'] }}</td>
                    <td class="num">{{ $fmt($row['sales_revenue']) }}</td>
                    @if($canProfit)<td class="num">{{ $fmt($row['profit_contribution']) }}</td>@endif
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if($has('returns') && !empty($report['returns']))
        @php $rt = $report['returns']; @endphp
        <h2>8. Returned Products</h2>
        <table class="kv">
            <tr><td class="k">Return transactions</td><td class="v">{{ $rt['transaction_count'] ?? 0 }}</td></tr>
            <tr><td class="k">Returned qty / restocked / not restocked</td><td class="v">{{ $rt['returned_quantity'] ?? 0 }} / {{ $rt['restocked_quantity'] ?? 0 }} / {{ $rt['non_restocked_quantity'] ?? 0 }}</td></tr>
            <tr><td class="k">Refund total</td><td class="v">{{ $fmt($rt['refund_total'] ?? 0) }}</td></tr>
            <tr><td class="k">Cash refunded</td><td class="v">{{ $fmt($rt['cash_refunded'] ?? 0) }}</td></tr>
            <tr><td class="k">COGS reversed</td><td class="v">{{ $fmt($rt['cogs_reversed'] ?? 0) }}</td></tr>
        </table>
        <div class="note">{{ $rt['note'] ?? '' }}</div>
        @if(!empty($rt['items']))
            <table class="data">
                <thead>
                <tr>
                    <th>Date</th><th>Return #</th><th>Sale #</th><th>Product</th>
                    <th class="num">Qty</th><th>Cond.</th><th class="num">Refund</th>
                </tr>
                </thead>
                <tbody>
                @foreach(array_slice($rt['items'], 0, 60) as $row)
                    <tr>
                        <td>{{ $row['return_date'] }}</td>
                        <td>{{ $row['return_number'] }}</td>
                        <td>{{ $row['invoice_number'] }}</td>
                        <td>{{ $row['product_name'] }}</td>
                        <td class="num">{{ $row['quantity'] }}</td>
                        <td>{{ $row['condition'] }}</td>
                        <td class="num">{{ $fmt($row['refund_amount']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if($has('investors') && !empty($report['investors']))
        @php $iv = $report['investors']; $c = $iv['combined'] ?? []; @endphp
        <h2>9. Investor Report</h2>
        <table class="kv">
            <tr><td class="k">Investors</td><td class="v">{{ $c['investor_count'] ?? 0 }}</td></tr>
            <tr><td class="k">Lifetime investment (capital)</td><td class="v">{{ $fmt($c['total_investment'] ?? 0) }}</td></tr>
            <tr><td class="k">Period investment</td><td class="v">{{ $fmt($c['period_investment'] ?? 0) }}</td></tr>
            <tr><td class="k">Capital balance</td><td class="v">{{ $fmt($c['investment_balance'] ?? 0) }}</td></tr>
            <tr><td class="k">Profit allocated (period)</td><td class="v">{{ $fmt($c['allocated_profit_period'] ?? 0) }}</td></tr>
            <tr><td class="k">Profit paid (period)</td><td class="v">{{ $fmt($c['profit_paid_period'] ?? 0) }}</td></tr>
            <tr><td class="k">Remaining profit payable</td><td class="v">{{ $fmt($c['remaining_profit_payable'] ?? 0) }}</td></tr>
            <tr><td class="k">Withdrawals (period)</td><td class="v">{{ $fmt($c['withdrawals_period'] ?? 0) }}</td></tr>
        </table>
        <div class="note">{{ $iv['note'] ?? '' }}</div>
        @if(!empty($iv['investors']))
            <table class="data">
                <thead>
                <tr>
                    <th>Investor</th><th>Status</th>
                    <th class="num">Capital bal.</th>
                    <th class="num">Period inv.</th>
                    <th class="num">Profit earned</th>
                    <th class="num">Profit paid</th>
                    <th class="num">Payable</th>
                </tr>
                </thead>
                <tbody>
                @foreach($iv['investors'] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['status'] }}</td>
                        <td class="num">{{ $fmt($row['investment_balance']) }}</td>
                        <td class="num">{{ $fmt($row['period_investment']) }}</td>
                        <td class="num">{{ $fmt($row['allocated_profit_period']) }}</td>
                        <td class="num">{{ $fmt($row['profit_paid_period']) }}</td>
                        <td class="num">{{ $fmt($row['remaining_profit_payable']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if($has('cash_flow') && !empty($report['cash_flow']))
        @php $cf = $report['cash_flow']; @endphp
        <h2>10. Financial &amp; Cash Flow</h2>
        <table class="kv">
            <tr><td class="k">Opening cash balance</td><td class="v">Not tracked</td></tr>
            <tr><td class="k">Cash inflows</td><td class="v">{{ $fmt($cf['cash_inflows'] ?? 0) }}</td></tr>
            <tr><td class="k">— Sales receipts</td><td class="v">{{ $fmt($cf['sales_receipts'] ?? 0) }}</td></tr>
            <tr><td class="k">— Investor contributions</td><td class="v">{{ $fmt($cf['investor_contributions'] ?? 0) }}</td></tr>
            <tr><td class="k">Cash outflows</td><td class="v">{{ $fmt($cf['cash_outflows'] ?? 0) }}</td></tr>
            <tr><td class="k">— Purchase payments</td><td class="v">{{ $fmt($cf['purchase_payments'] ?? 0) }}</td></tr>
            <tr><td class="k">— Expenses</td><td class="v">{{ $fmt($cf['expenses'] ?? 0) }}</td></tr>
            <tr><td class="k">— Investor distributions</td><td class="v">{{ $fmt($cf['investor_distributions'] ?? 0) }}</td></tr>
            <tr><td class="k">— Refunds</td><td class="v">{{ $fmt($cf['refunds'] ?? 0) }}</td></tr>
            <tr><td class="k">Net cash flow</td><td class="v">{{ $fmt($cf['net_cash_flow'] ?? 0) }}</td></tr>
            <tr><td class="k">Closing cash balance</td><td class="v">Not tracked</td></tr>
            <tr><td class="k">Accounts receivable</td><td class="v">{{ $fmt($cf['accounts_receivable'] ?? 0) }}</td></tr>
            <tr><td class="k">Accounts payable</td><td class="v">{{ $fmt($cf['accounts_payable'] ?? 0) }}</td></tr>
        </table>
        <div class="note">{{ $cf['note'] ?? '' }}</div>
    @endif

    @if($has('suppliers') && !empty($report['suppliers']))
        @php $sup = $report['suppliers']; @endphp
        <h2>11. Suppliers</h2>
        <table class="data">
            <thead><tr><th>Supplier</th><th class="num">Purchases</th><th class="num">Payments</th><th class="num">Outstanding</th></tr></thead>
            <tbody>
            @foreach($sup['top'] ?? [] as $row)
                <tr>
                    <td>{{ $row['supplier_name'] }}</td>
                    <td class="num">{{ $fmt($row['purchases']) }}</td>
                    <td class="num">{{ $fmt($row['payments']) }}</td>
                    <td class="num">{{ $fmt($row['outstanding_payable']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if($has('customers') && !empty($report['customers']))
        @php $cu = $report['customers']; @endphp
        <h2>12. Customers</h2>
        <table class="data">
            <thead><tr><th>Customer</th><th class="num">Sales</th><th class="num">Payments</th><th class="num">Outstanding</th></tr></thead>
            <tbody>
            @foreach($cu['top'] ?? [] as $row)
                <tr>
                    <td>{{ $row['customer_name'] }}</td>
                    <td class="num">{{ $fmt($row['sales']) }}</td>
                    <td class="num">{{ $fmt($row['payments_received']) }}</td>
                    <td class="num">{{ $fmt($row['outstanding_receivable']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        {{ $meta['company'] ?? 'ZUHA' }} · {{ $meta['report_label'] ?? 'Report' }} · {{ $meta['period_label'] ?? '' }}
        · Page <span class="page"></span>
    </div>
</body>
</html>
