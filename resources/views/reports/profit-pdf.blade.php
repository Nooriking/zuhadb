<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profit Report</title>
    <style>
        /* ═══════════════════════════════════════════════════
         |  PAGE SETUP — A4 با Margin کافی
         ═══════════════════════════════════════════════════ */
        @page {
            size: A4 portrait;
            margin: 18mm 16mm 20mm 16mm;
        }

        @page :first {
            margin-top: 15mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        html, body {
            width: 100%;
            font-family: 'DejaVu Sans', 'Segoe UI', sans-serif;
            font-size: 9.5px;
            color: #0F172A;
            background: #ffffff;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* ═══════════════════════════════════════════════════
         |  CONTAINER — با padding داخلی
         ═══════════════════════════════════════════════════ */
        .container {
            padding: 0 6mm;
        }

        /* ═══════════════════════════════════════════════════
         |  HEADER
         ═══════════════════════════════════════════════════ */
        .header {
            text-align: center;
            margin-bottom: 22px;
            padding: 0 10mm 16px 10mm;
            border-bottom: 2px solid #0F172A;
        }

        .header h1 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 2px;
            color: #0F172A;
            text-transform: uppercase;
        }

        .header .subtitle {
            margin-top: 5px;
            color: #64748B;
            font-size: 10px;
            letter-spacing: 0.3px;
        }

        .meta {
            margin-top: 14px;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            color: #64748B;
            font-size: 9px;
        }

        .meta span {
            padding: 4px 14px;
            background: #F8FAFC;
            border-radius: 20px;
            border: 1px solid #E2E8F0;
        }

        .meta strong {
            color: #0F172A;
            font-weight: 700;
        }

        /* ═══════════════════════════════════════════════════
         |  KPI CARDS
         ═══════════════════════════════════════════════════ */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .kpi-card {
            background: #ffffff;
            border: 1px solid #E2E8F0;
            border-left: 3px solid #0F172A;
            border-radius: 8px;
            padding: 14px 16px;
            page-break-inside: avoid;
        }

        .kpi-card.investor {
            border-left-color: #2563EB;
        }

        .kpi-card.store {
            border-left-color: #059669;
        }

        .kpi-card.expense {
            border-left-color: #DC2626;
        }

        .kpi-label {
            font-size: 8px;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
        }

        .kpi-value {
            margin-top: 6px;
            font-size: 18px;
            font-weight: 700;
            color: #0F172A;
            line-height: 1.15;
            font-family: 'DejaVu Sans Mono', monospace;
        }

        .kpi-sub {
            margin-top: 5px;
            color: #94A3B8;
            font-size: 8px;
        }

        /* ═══════════════════════════════════════════════════
         |  INFO BOX
         ═══════════════════════════════════════════════════ */
        .info-box {
            background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%);
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 14px 20px;
            margin-bottom: 20px;
            color: #475569;
            font-size: 9px;
            text-align: center;
            page-break-inside: avoid;
        }

        .info-box strong {
            color: #0F172A;
            font-weight: 700;
        }

        .info-box .separator {
            display: inline-block;
            margin: 0 12px;
            color: #CBD5E1;
        }

        /* ═══════════════════════════════════════════════════
         |  SECTION
         ═══════════════════════════════════════════════════ */
        .section {
            margin-top: 22px;
            margin-bottom: 20px;
            page-break-inside: auto;
        }

        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 12px;
            padding-bottom: 8px;
            padding-left: 2px;
            border-bottom: 1px solid #E2E8F0;
            letter-spacing: -0.2px;
            text-transform: uppercase;
        }

        /* ═══════════════════════════════════════════════════
         |  TABLE — با padding کافی در سلول‌ها
         ═══════════════════════════════════════════════════ */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: auto;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            overflow: hidden;
        }

        .data-table thead {
            display: table-header-group;
        }

        .data-table thead th {
            text-align: left;
            padding: 11px 10px;           /* ← padding کافی */
            border-bottom: 2px solid #0F172A;
            color: #475569;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            background: #F8FAFC;
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: middle;
        }

        .data-table thead th:first-child {
            padding-left: 14px;           /* ← فاصله از لبه چپ */
        }

        .data-table thead th:last-child {
            padding-right: 14px;          /* ← فاصله از لبه راست */
        }

        .data-table tbody tr {
            page-break-inside: avoid;
        }

        .data-table tbody td {
            padding: 10px 10px;           /* ← padding کافی */
            border-bottom: 1px solid #F1F5F9;
            font-size: 8.5px;
            color: #0F172A;
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: middle;
        }

        .data-table tbody td:first-child {
            padding-left: 14px;           /* ← فاصله از لبه چپ */
        }

        .data-table tbody td:last-child {
            padding-right: 14px;          /* ← فاصله از لبه راست */
        }

        .data-table tbody tr:nth-child(even) {
            background: #FAFBFC;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table .right {
            text-align: right;
        }

        .data-table .center {
            text-align: center;
        }

        .data-table td.bold {
            font-weight: 700;
        }

        .data-table td.muted {
            color: #94A3B8;
        }

        .data-table td.mono {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 8px;
        }

        .total-row td {
            border-top: 2px solid #0F172A;
            border-bottom: none;
            font-weight: 700;
            background: #F1F5F9;
            font-size: 9px;
            padding: 12px 10px;           /* ← padding بیشتر */
            color: #0F172A;
        }

        .total-row td:first-child {
            padding-left: 14px;
        }

        .total-row td:last-child {
            padding-right: 14px;
        }

        /* ═══════════════════════════════════════════════════
         |  EXPENSE LIST
         ═══════════════════════════════════════════════════ */
        .expense-list {
            width: 100%;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            overflow: hidden;
        }

        .expense-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 11px 18px;           /* ← padding کافی */
            border-bottom: 1px solid #F1F5F9;
            font-size: 9px;
        }

        .expense-item:last-child {
            border-bottom: none;
        }

        .expense-item:nth-child(even) {
            background: #FAFBFC;
        }

        .expense-item .label {
            color: #0F172A;
        }

        .expense-item .label .count {
            color: #94A3B8;
            font-size: 7.5px;
            margin-left: 8px;
        }

        .expense-item .amount {
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: 700;
            color: #0F172A;
        }

        .expense-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 18px;           /* ← padding کافی */
            background: #0F172A;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
        }

        .expense-total .amount {
            font-family: 'DejaVu Sans Mono', monospace;
        }

        /* ═══════════════════════════════════════════════════
         |  DIVIDER
         ═══════════════════════════════════════════════════ */
        .divider {
            border: none;
            border-top: 1px solid #E2E8F0;
            margin: 20px 0;
        }

        /* ═══════════════════════════════════════════════════
         |  FOOTER
         ═══════════════════════════════════════════════════ */
        .footer {
            margin-top: 40px;
            margin-bottom: 10px;
            text-align: center;
            color: #94A3B8;
            font-size: 7.5px;
            border-top: 1px solid #E2E8F0;
            padding-top: 14px;
            padding-bottom: 6px;
            page-break-inside: avoid;
        }

        /* ═══════════════════════════════════════════════════
         |  PAGE BREAK
         ═══════════════════════════════════════════════════ */
        .page-break {
            page-break-after: always;
            height: 0;
        }

        .no-data {
            text-align: center;
            color: #94A3B8;
            padding: 28px 20px;
            font-size: 9px;
            font-style: italic;
            background: #F8FAFC;
            border-radius: 8px;
            border: 1px dashed #E2E8F0;
        }
    </style>
</head>
<body>

    <div class="container">

        {{-- ═══════════════════════════════════════════════════
         |  HEADER
         ═══════════════════════════════════════════════════ --}}
        <div class="header">
            <h1>Profit Report</h1>
            <div class="subtitle">Business Performance Report</div>

            <div class="meta">
                <span><strong>{{ $from }}</strong> → <strong>{{ $to }}</strong></span>
                <span>Group by: <strong>{{ ucfirst($groupBy) }}</strong></span>
                @if (! empty($investorName))
                    <span>Investor: <strong>{{ $investorName }}</strong></span>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════
         |  KPI CARDS
         ═══════════════════════════════════════════════════ --}}
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Total Sales</div>
                <div class="kpi-value">{{ number_format($summary['total_sales'], 0) }}</div>
                <div class="kpi-sub">{{ $summary['total_orders'] }} orders · {{ $summary['total_quantity'] }} pcs</div>
            </div>
            <div class="kpi-card investor">
                <div class="kpi-label">Investor Profit</div>
                <div class="kpi-value">{{ number_format($summary['investor_profit'], 0) }}</div>
                <div class="kpi-sub">AFN</div>
            </div>
            <div class="kpi-card store">
                <div class="kpi-label">Store Profit</div>
                <div class="kpi-value">{{ number_format($summary['store_profit'], 0) }}</div>
                <div class="kpi-sub">AFN</div>
            </div>
            <div class="kpi-card expense">
                <div class="kpi-label">Total Expenses</div>
                <div class="kpi-value">{{ number_format($summary['total_expenses'], 0) }}</div>
                <div class="kpi-sub">{{ count($expenses['by_category']) }} categories</div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════
         |  INFO BOX
         ═══════════════════════════════════════════════════ --}}
        <div class="info-box">
            <strong>Gross Profit:</strong> {{ number_format($summary['total_profit'], 0) }} AFN
            <span class="separator">|</span>
            <strong>Net Profit:</strong> {{ number_format($summary['net_profit'], 0) }} AFN
            <span class="separator">|</span>
            <strong>Investors:</strong> {{ $investorStats['total_investors'] }} total
            ({{ $investorStats['active_investors'] }} active)
            @if (! empty($latestRate))
                <span class="separator">|</span>
                <strong>USD Rate:</strong> {{ number_format($latestRate['avg_rate'], 2) }} AFN
                <em>({{ $latestRate['month'] }})</em>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════
         |  BREAKDOWN TABLE
         ═══════════════════════════════════════════════════ --}}
        <div class="section">
            <div class="section-title">
                {{ ucfirst($groupBy) }} Breakdown
            </div>

            @if (count($breakdown) === 0)
                <div class="no-data">No sales data for this period</div>
            @else
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 12%;">{{ $groupBy === 'monthly' ? 'Month' : 'Date' }}</th>
                            <th class="center" style="width: 7%;">Orders</th>
                            <th class="center" style="width: 6%;">Qty</th>
                            <th class="right" style="width: 11%;">Sales</th>
                            <th class="right" style="width: 11%;">Cost</th>
                            <th class="right" style="width: 11%;">Investor</th>
                            <th class="right" style="width: 11%;">Store</th>
                            <th class="right" style="width: 11%;">Gross</th>
                            <th class="right" style="width: 10%;">Expenses</th>
                            <th class="right" style="width: 10%;">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $tOrders = 0; $tQty = 0; $tSales = 0; $tCost = 0;
                            $tInv = 0; $tStore = 0; $tGross = 0; $tExp = 0; $tNet = 0;
                        @endphp

                        @foreach ($breakdown as $row)
                            @php
                                $tOrders += $row['orders']; $tQty += $row['quantity'];
                                $tSales += $row['sales']; $tCost += $row['cost'];
                                $tInv += $row['investor_profit']; $tStore += $row['store_profit'];
                                $tGross += $row['total_profit'];
                                $tExp += $row['expenses'] ?? 0;
                                $tNet += $row['net'] ?? ($row['total_profit'] - ($row['expenses'] ?? 0));
                            @endphp
                            <tr>
                                <td class="bold">{{ $row['date'] }}</td>
                                <td class="center mono">{{ $row['orders'] }}</td>
                                <td class="center mono">{{ $row['quantity'] }}</td>
                                <td class="right mono">{{ number_format($row['sales'], 0) }}</td>
                                <td class="right mono muted">{{ number_format($row['cost'], 0) }}</td>
                                <td class="right mono">{{ number_format($row['investor_profit'], 0) }}</td>
                                <td class="right mono">{{ number_format($row['store_profit'], 0) }}</td>
                                <td class="right mono bold">{{ number_format($row['total_profit'], 0) }}</td>
                                <td class="right mono">{{ ($row['expenses'] ?? 0) > 0 ? number_format($row['expenses'], 0) : '—' }}</td>
                                <td class="right mono bold">{{ number_format($row['net'] ?? 0, 0) }}</td>
                            </tr>
                        @endforeach

                        <tr class="total-row">
                            <td>TOTAL</td>
                            <td class="center mono">{{ $tOrders }}</td>
                            <td class="center mono">{{ $tQty }}</td>
                            <td class="right mono">{{ number_format($tSales, 0) }}</td>
                            <td class="right mono">{{ number_format($tCost, 0) }}</td>
                            <td class="right mono">{{ number_format($tInv, 0) }}</td>
                            <td class="right mono">{{ number_format($tStore, 0) }}</td>
                            <td class="right mono">{{ number_format($tGross, 0) }}</td>
                            <td class="right mono">{{ number_format($tExp, 0) }}</td>
                            <td class="right mono">{{ number_format($tNet, 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════
         |  PER-INVESTOR TABLE
         ═══════════════════════════════════════════════════ --}}
        <div class="section">
            <div class="section-title">Per-Investor Breakdown</div>

            @if (count($perInvestor) === 0)
                <div class="no-data">No data</div>
            @else
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="center" style="width: 5%;">#</th>
                            <th style="width: 30%;">Investor</th>
                            <th class="center" style="width: 10%;">Orders</th>
                            <th class="center" style="width: 10%;">Qty</th>
                            <th class="right" style="width: 15%;">Sales (AFN)</th>
                            <th class="right" style="width: 15%;">Investor Profit</th>
                            <th class="right" style="width: 15%;">Store Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $sumInv = 0; $sumStore = 0; $sumSales = 0; $sumOrders = 0; $sumQty = 0; @endphp
                        @foreach ($perInvestor as $i => $row)
                            @php
                                $sumInv += $row['investor_profit'];
                                $sumStore += $row['store_profit'];
                                $sumSales += $row['sales'];
                                $sumOrders += $row['orders'];
                                $sumQty += $row['quantity'];
                            @endphp
                            <tr>
                                <td class="center muted mono">{{ $i + 1 }}</td>
                                <td class="bold">{{ $row['investor_name'] }}</td>
                                <td class="center mono">{{ $row['orders'] }}</td>
                                <td class="center mono">{{ $row['quantity'] }}</td>
                                <td class="right mono">{{ number_format($row['sales'], 0) }}</td>
                                <td class="right mono">
                                    {{ $row['investor_profit'] > 0 ? number_format($row['investor_profit'], 0) : '—' }}
                                </td>
                                <td class="right mono">{{ number_format($row['store_profit'], 0) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total-row">
                            <td colspan="2">TOTAL</td>
                            <td class="center mono">{{ $sumOrders }}</td>
                            <td class="center mono">{{ $sumQty }}</td>
                            <td class="right mono">{{ number_format($sumSales, 0) }}</td>
                            <td class="right mono">{{ number_format($sumInv, 0) }}</td>
                            <td class="right mono">{{ number_format($sumStore, 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════
         |  PAGE 2
         ═══════════════════════════════════════════════════ --}}
        <div class="page-break"></div>

        {{-- EXPENSES BY CATEGORY --}}
        <div class="section">
            <div class="section-title">Expenses Summary</div>

            @if (count($expenses['by_category']) === 0)
                <div class="no-data">No expenses in this period</div>
            @else
                <div class="expense-list">
                    @foreach ($expenses['by_category'] as $e)
                        <div class="expense-item">
                            <div class="label">
                                {{ $e['category'] }}
                                <span class="count">({{ $e['count'] }} item{{ $e['count'] > 1 ? 's' : '' }})</span>
                            </div>
                            <div class="amount">{{ number_format($e['total'], 0) }} AFN</div>
                        </div>
                    @endforeach

                    <div class="expense-total">
                        <div>TOTAL EXPENSES</div>
                        <div class="amount">{{ number_format($expenses['total'], 0) }} AFN</div>
                    </div>
                </div>
            @endif
        </div>

        {{-- EXCHANGE RATES --}}
        <div class="section">
            <div class="section-title">Exchange Rates by Month</div>

            @if (count($exchangeRates) === 0)
                <div class="no-data">No exchange rate data</div>
            @else
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Month</th>
                            <th class="right" style="width: 20%;">Avg Rate (AFN/USD)</th>
                            <th class="right" style="width: 15%;">Min</th>
                            <th class="right" style="width: 15%;">Max</th>
                            <th class="center" style="width: 20%;">Days Recorded</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($exchangeRates as $r)
                            <tr>
                                <td class="bold">{{ $r['month'] }}</td>
                                <td class="right mono bold">{{ number_format($r['avg_rate'], 2) }}</td>
                                <td class="right mono muted">{{ number_format($r['min_rate'], 2) }}</td>
                                <td class="right mono muted">{{ number_format($r['max_rate'], 2) }}</td>
                                <td class="center mono">{{ $r['days'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- FOOTER --}}
        <div class="footer">
            Generated by Zuha Perfume Management System · {{ $generatedAt }}
        </div>

    </div>

</body>
</html>