<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} — {{ $company }}</title>
    <style>
        /* DomPDF has no flexbox/grid support: every multi-column layout below is a table. */
        @page { margin: 30mm 13mm 18mm 13mm; }

        /* DomPDF applies @page margins to <html>, so the reset must not touch it. */
        body, body * { margin: 0; padding: 0; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            line-height: 1.45;
        }

        /* ── Running header / footer ───────────────────────── */
        .page-header {
            position: fixed;
            top: -24mm;
            left: 0;
            right: 0;
            height: 18mm;
        }
        .page-header table { width: 100%; border-collapse: collapse; }
        .page-header .brand {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 1.5px;
        }
        .page-header .brand-sub { font-size: 7.5px; color: #64748b; margin-top: 2px; }
        .page-header .doc-title {
            text-align: right;
            font-size: 11px;
            font-weight: bold;
            color: #b45309;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .page-header .doc-period { text-align: right; font-size: 7.5px; color: #64748b; margin-top: 2px; }
        .page-header .rule {
            margin-top: 6px;
            height: 2px;
            background: #0f172a;
            border-bottom: 1px solid #b45309;
        }

        .page-footer {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            height: 8mm;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            font-size: 7px;
            color: #94a3b8;
        }
        /* ── Cover block ───────────────────────────────────── */
        .cover {
            background: #0f172a;
            color: #ffffff;
            padding: 14px 16px;
            margin-bottom: 14px;
        }
        .cover .title { font-size: 17px; font-weight: bold; letter-spacing: 0.5px; }
        .cover .subtitle { font-size: 9px; color: #fbbf24; margin-top: 2px; }
        .cover table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .cover td {
            width: 25%;
            padding: 6px 8px;
            background: #1e293b;
            border: 2px solid #0f172a;
            vertical-align: top;
        }
        .cover .k { font-size: 6.5px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.6px; }
        .cover .v { font-size: 9px; font-weight: bold; color: #ffffff; margin-top: 2px; }

        /* ── Sections ──────────────────────────────────────── */
        .section { margin-bottom: 14px; }
        .section.break { page-break-before: always; }

        .section-head {
            border-left: 4px solid #b45309;
            padding: 3px 0 3px 8px;
            margin-bottom: 8px;
        }
        .section-head .t { font-size: 12.5px; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.4px; }
        .section-head .s { font-size: 7.5px; color: #64748b; margin-top: 1px; }

        .sub-head {
            font-size: 9px;
            font-weight: bold;
            color: #0f172a;
            margin: 12px 0 5px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .sub-head .note { font-weight: normal; color: #94a3b8; text-transform: none; letter-spacing: 0; font-size: 7px; }

        /* ── KPI boxes ─────────────────────────────────────── */
        .kpis { width: 100%; border-collapse: separate; border-spacing: 5px; margin: 0 -5px 6px -5px; }
        .kpi {
            width: 25%;
            border: 1px solid #e2e8f0;
            border-top: 3px solid #0f172a;
            background: #f8fafc;
            padding: 7px 9px;
            vertical-align: top;
        }
        .kpi .k { font-size: 6.5px; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px; font-weight: bold; }
        .kpi .v { font-size: 13px; font-weight: bold; color: #0f172a; margin-top: 3px; }
        .kpi .s { font-size: 6.5px; color: #94a3b8; margin-top: 2px; }
        .kpi.blue   { border-top-color: #2563eb; } .kpi.blue .v   { color: #1d4ed8; }
        .kpi.green  { border-top-color: #059669; } .kpi.green .v  { color: #047857; }
        .kpi.red    { border-top-color: #dc2626; } .kpi.red .v    { color: #b91c1c; }
        .kpi.violet { border-top-color: #7c3aed; } .kpi.violet .v { color: #6d28d9; }
        .kpi.amber  { border-top-color: #d97706; } .kpi.amber .v  { color: #b45309; }
        .kpi.empty  { border: none; background: none; }

        /* ── Data tables ───────────────────────────────────── */
        .tbl { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .tbl thead { display: table-header-group; }
        .tbl tr { page-break-inside: avoid; }
        .tbl th {
            background: #0f172a;
            color: #ffffff;
            font-size: 6.8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 5px 5px;
            text-align: left;
        }
        .tbl td {
            padding: 4.5px 5px;
            border-bottom: 1px solid #eef2f7;
            font-size: 7.6px;
            vertical-align: middle;
        }
        .tbl tbody tr:nth-child(even) td { background: #f8fafc; }
        .tbl .r { text-align: right; }
        .tbl .c { text-align: center; }
        .tbl .b { font-weight: bold; }
        .tbl .muted { color: #94a3b8; }
        .tbl .blue { color: #1d4ed8; }
        .tbl .green { color: #047857; }
        .tbl .red { color: #b91c1c; }
        .tbl .violet { color: #6d28d9; }
        .tbl .small { font-size: 6.5px; color: #64748b; }
        .tbl tr.total td {
            background: #fef3c7;
            border-top: 1.5px solid #b45309;
            border-bottom: 1.5px solid #b45309;
            font-weight: bold;
            color: #0f172a;
        }
        .tbl.compact td { padding: 3.5px 3px; font-size: 6.8px; }
        .tbl.compact th { padding: 4px 3px; font-size: 6.2px; }

        .bar { height: 4px; background: #e2e8f0; margin-top: 2px; }
        .bar div { height: 4px; background: #b45309; }

        .badge {
            display: inline-block;
            padding: 1px 5px;
            font-size: 6.2px;
            font-weight: bold;
            text-transform: uppercase;
            background: #e2e8f0;
            color: #334155;
        }
        .badge.green { background: #d1fae5; color: #047857; }

        .no-data {
            padding: 12px;
            text-align: center;
            color: #94a3b8;
            font-style: italic;
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
        }

        .two-col { width: 100%; border-collapse: collapse; }
        .two-col > tbody > tr > td { width: 50%; vertical-align: top; }
        .two-col > tbody > tr > td.left { padding-right: 6px; }
        .two-col > tbody > tr > td.right { padding-left: 6px; }

        .note-box {
            margin-top: 8px;
            padding: 6px 9px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 6.8px;
            color: #64748b;
        }

        .signatures { width: 100%; border-collapse: collapse; margin-top: 30px; page-break-inside: avoid; }
        .signatures td { width: 33%; padding: 0 12px; text-align: center; font-size: 7.5px; color: #475569; }
        .signatures .line { border-top: 1px solid #94a3b8; padding-top: 4px; margin-top: 26px; }
    </style>
</head>
<body>
    <div class="page-header">
        <table>
            <tr>
                <td>
                    <div class="brand">{{ strtoupper($company) }}</div>
                    <div class="brand-sub">{{ $companyInfo ?: 'Perfume Management System' }}</div>
                </td>
                <td>
                    <div class="doc-title">{{ $title }}</div>
                    <div class="doc-period">
                        {{ $filters['label'] }}
                        @if ($filters['branch_name']) · Branch: {{ $filters['branch_name'] }} @endif
                    </div>
                </td>
            </tr>
        </table>
        <div class="rule"></div>
    </div>

    <div class="page-footer">
        Generated {{ $generatedAt }}@if ($generatedBy) by {{ $generatedBy }}@endif · Confidential — for internal use only
    </div>

    <div class="cover">
        <div class="title">{{ $title }}</div>
        <div class="subtitle">{{ $coverSubtitle ?? 'Business performance statement' }}</div>
        <table>
            <tr>
                <td><div class="k">Period</div><div class="v">{{ $filters['label'] }}</div></td>
                <td><div class="k">Date range</div><div class="v">{{ $filters['from'] }} → {{ $filters['to'] }}</div></td>
                <td><div class="k">Branch</div><div class="v">{{ $filters['branch_name'] ?? 'All branches' }}</div></td>
                <td><div class="k">Grouping</div><div class="v">{{ ucfirst($filters['group_by']) }}</div></td>
            </tr>
        </table>
    </div>

    @yield('content')

    <table class="signatures">
        <tr>
            <td><div class="line">Prepared by</div></td>
            <td><div class="line">Checked by (Accountant)</div></td>
            <td><div class="line">Approved by (Manager)</div></td>
        </tr>
    </table>
</body>
</html>
