import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, Eye, FileText, Loader2 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { toast } from 'sonner';

type ReportOption = { value: string; label: string };
type Branch = { id: number; name: string; code: string };

type Defaults = {
    quick: string;
    month: number;
    year: number;
    date_from: string;
    date_to: string;
    report: string;
    branch_id: number | null;
    include_all_products: boolean;
    include_all_parties: boolean;
};

type Props = {
    reportOptions: ReportOption[];
    branches: Branch[];
    defaults: Defaults;
    branchScoped: boolean;
    years: number[];
};

type PreviewSummary = Record<string, number | null | boolean | Record<string, string>>;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
    { title: 'Export PDF', href: '/reports/export' },
];

const MONTHS = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const QUICK = [
    { value: 'this_month', label: 'This Month' },
    { value: 'last_month', label: 'Last Month' },
    { value: 'this_year', label: 'This Year' },
    { value: 'month', label: 'Month / Year' },
    { value: 'custom', label: 'Custom Range' },
] as const;

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function money(n: number | null | undefined): string {
    if (n === null || n === undefined) return '—';
    return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function ReportsExport({ reportOptions, branches, defaults, branchScoped, years }: Props) {
    const [quick, setQuick] = useState(defaults.quick);
    const [month, setMonth] = useState(String(defaults.month));
    const [year, setYear] = useState(String(defaults.year));
    const [dateFrom, setDateFrom] = useState(defaults.date_from);
    const [dateTo, setDateTo] = useState(defaults.date_to);
    const [report, setReport] = useState(defaults.report);
    const [branchId, setBranchId] = useState(defaults.branch_id ? String(defaults.branch_id) : 'all');
    const [includeAllProducts, setIncludeAllProducts] = useState(defaults.include_all_products);
    const [includeAllParties, setIncludeAllParties] = useState(defaults.include_all_parties);
    const [busy, setBusy] = useState<'preview' | 'pdf' | null>(null);
    const [preview, setPreview] = useState<{
        meta: Record<string, string | boolean | null>;
        summary: PreviewSummary | null;
    } | null>(null);

    const params = useMemo(() => {
        const p = new URLSearchParams();
        p.set('quick', quick);
        p.set('report', report);
        p.set('month', month);
        p.set('year', year);
        p.set('date_from', dateFrom);
        p.set('date_to', dateTo);
        if (branchId !== 'all') p.set('branch_id', branchId);
        if (includeAllProducts) p.set('include_all_products', '1');
        if (includeAllParties) p.set('include_all_parties', '1');
        return p;
    }, [quick, report, month, year, dateFrom, dateTo, branchId, includeAllProducts, includeAllParties]);

    const runPreview = useCallback(async () => {
        if (busy) return;
        setBusy('preview');
        try {
            const res = await fetch(`/reports/export/preview?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const json = await res.json();
            if (!res.ok) {
                const msg =
                    json?.message ||
                    (json?.errors ? Object.values(json.errors).flat().join(' ') : null) ||
                    'Preview failed';
                toast.error(String(msg));
                return;
            }
            setPreview({ meta: json.meta, summary: json.summary });
            toast.success('Preview ready');
        } catch {
            toast.error('Could not load preview');
        } finally {
            setBusy(null);
        }
    }, [busy, params]);

    const runExport = useCallback(async () => {
        if (busy) return;
        setBusy('pdf');
        try {
            const res = await fetch(`/reports/export/pdf?${params.toString()}`, {
                headers: {
                    Accept: 'application/pdf',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                let msg = 'PDF export failed';
                try {
                    const json = await res.json();
                    msg =
                        json?.message ||
                        (json?.errors ? Object.values(json.errors).flat().join(' ') : null) ||
                        msg;
                } catch {
                    /* binary or empty */
                }
                toast.error(String(msg));
                return;
            }
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            const cd = res.headers.get('Content-Disposition') || '';
            const match = /filename="?([^"]+)"?/.exec(cd);
            a.href = url;
            a.download = match?.[1] || 'ZUHA-report.pdf';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            toast.success('PDF downloaded');
        } catch {
            toast.error('Could not export PDF');
        } finally {
            setBusy(null);
        }
    }, [busy, params]);

    const summaryCards = preview?.summary
        ? [
              ['Total Sales', preview.summary.total_sales],
              ['Total Purchases', preview.summary.total_purchases],
              ['Gross Profit', preview.summary.gross_profit],
              ['Net Profit', preview.summary.net_profit],
              ['Expenses', preview.summary.total_expenses],
              ['Sales Returns', preview.summary.sales_returns],
              ['Investment', preview.summary.total_investment],
              ['Investor Profit', preview.summary.investor_profit],
              ['Net Cash Flow', preview.summary.net_cash_flow],
              ['Inventory Value', preview.summary.inventory_value],
              ['Receivables', preview.summary.outstanding_receivables],
              ['Payables', preview.summary.outstanding_payables],
          ]
        : [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Export Business Report" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <div className="text-muted-foreground flex items-center gap-2 text-sm">
                            <FileText className="size-4" />
                            Business reports
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">Monthly &amp; Custom PDF Export</h1>
                        <p className="text-muted-foreground max-w-2xl text-sm">
                            Select a period, choose a report, then export a print-ready A4 PDF from live database
                            totals.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/reports">
                            <ArrowLeft className="me-2 size-4" />
                            Reports hub
                        </Link>
                    </Button>
                </div>

                <div className="bg-background space-y-5 rounded-lg border p-4 sm:p-5">
                    <div>
                        <Label className="mb-2 block text-sm font-medium">Quick period</Label>
                        <div className="flex flex-wrap gap-2">
                            {QUICK.map((q) => (
                                <button
                                    key={q.value}
                                    type="button"
                                    onClick={() => setQuick(q.value)}
                                    className={`rounded-md border px-3 py-2 text-sm transition-colors ${
                                        quick === q.value
                                            ? 'bg-primary text-primary-foreground border-primary'
                                            : 'bg-background hover:bg-muted'
                                    }`}
                                    aria-pressed={quick === q.value}
                                >
                                    {q.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {quick === 'month' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="month">Month</Label>
                                <Select value={month} onValueChange={setMonth}>
                                    <SelectTrigger id="month">
                                        <SelectValue placeholder="Month" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {MONTHS.map((m) => (
                                            <SelectItem key={m.value} value={String(m.value)}>
                                                {m.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="year">Year</Label>
                                <Select value={year} onValueChange={setYear}>
                                    <SelectTrigger id="year">
                                        <SelectValue placeholder="Year" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {years.map((y) => (
                                            <SelectItem key={y} value={String(y)}>
                                                {y}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    )}

                    {quick === 'custom' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="date_from">Start date</Label>
                                <Input
                                    id="date_from"
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) => setDateFrom(e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="date_to">End date</Label>
                                <Input
                                    id="date_to"
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                />
                            </div>
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="report">Report</Label>
                            <Select value={report} onValueChange={setReport}>
                                <SelectTrigger id="report">
                                    <SelectValue placeholder="Report type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {reportOptions.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {!branchScoped && (
                            <div className="space-y-2">
                                <Label htmlFor="branch">Branch</Label>
                                <Select value={branchId} onValueChange={setBranchId}>
                                    <SelectTrigger id="branch">
                                        <SelectValue placeholder="Branch" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All branches</SelectItem>
                                        {branches.map((b) => (
                                            <SelectItem key={b.id} value={String(b.id)}>
                                                {b.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row sm:gap-6">
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={includeAllProducts}
                                onChange={(e) => setIncludeAllProducts(e.target.checked)}
                                className="size-4 rounded border"
                            />
                            Include all products (inventory / best sellers)
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={includeAllParties}
                                onChange={(e) => setIncludeAllParties(e.target.checked)}
                                className="size-4 rounded border"
                            />
                            Include all suppliers &amp; customers
                        </label>
                    </div>

                    <div className="flex flex-wrap gap-2 border-t pt-4">
                        <Button type="button" variant="outline" onClick={runPreview} disabled={!!busy}>
                            {busy === 'preview' ? (
                                <Loader2 className="me-2 size-4 animate-spin" />
                            ) : (
                                <Eye className="me-2 size-4" />
                            )}
                            Preview
                        </Button>
                        <Button type="button" onClick={runExport} disabled={!!busy}>
                            {busy === 'pdf' ? (
                                <Loader2 className="me-2 size-4 animate-spin" />
                            ) : (
                                <Download className="me-2 size-4" />
                            )}
                            Export PDF
                        </Button>
                        <p className="text-muted-foreground w-full text-xs sm:ms-auto sm:w-auto sm:self-center">
                            Workflow: Select Period → Select Report → Export PDF
                        </p>
                    </div>
                </div>

                {preview && (
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-lg font-semibold">Preview summary</h2>
                            <Badge variant="secondary">{String(preview.meta.period_label ?? '')}</Badge>
                            <Badge variant="outline">{String(preview.meta.report_label ?? '')}</Badge>
                        </div>
                        {summaryCards.length > 0 ? (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {summaryCards.map(([label, value]) => (
                                    <div key={String(label)} className="bg-background rounded-lg border p-3">
                                        <div className="text-muted-foreground text-xs uppercase tracking-wide">
                                            {label}
                                        </div>
                                        <div className="mt-1 text-lg font-semibold tabular-nums">
                                            {typeof value === 'number' || value === null
                                                ? money(value as number | null)
                                                : '—'}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                This report type has no summary cards. Export PDF to view full tables.
                            </p>
                        )}
                        <p className="text-muted-foreground text-xs">
                            Generated {String(preview.meta.generated_at ?? '')} · Branch{' '}
                            {String(preview.meta.branch_name ?? 'All')} · Currency{' '}
                            {String(preview.meta.currency ?? 'AFN')}
                        </p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
