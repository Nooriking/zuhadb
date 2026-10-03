// resources/js/pages/sales/report.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    BarChart3,
    Calendar,
    DollarSign,
    Download,
    Filter,
    Package,
    Printer,
    ShoppingBag,
    TrendingDown,
    TrendingUp,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface SaleItem {
    id: number;
    variant_id: number;
    quantity: number;
    unit_price_afn: string;
    line_subtotal_afn: string;
    line_cost_afn: string;
    line_profit_afn: string;
    variant?: {
        id: number;
        name: string;
        volume_ml: number | null;
        concentration: string | null;
        product?: { id: number; name: string };
    };
}

interface Sale {
    id: number;
    invoice_number: string;
    receipt_no: string | null;
    sale_date: string;
    total_afn: string;
    subtotal_afn: string;
    discount_afn: string;
    paid_afn: string;
    remaining_due_afn: string;
    total_cost_afn: string;
    total_profit_afn: string;
    total_investor_profit_afn: string;
    store_profit_afn: string;
    payment_status: 'unpaid' | 'partial' | 'paid';
    status: 'draft' | 'completed' | 'cancelled';
    items_count: number;
    exchange_rate?: number | null;
    branch: { id: number; name: string; code: string } | null;
    customer: { id: number; name: string; phone: string | null } | null;
    creator: { id: number; name: string } | null;
    items?: SaleItem[];
}

interface Summary {
    total_sales: number;
    total_paid: number;
    total_due: number;
    total_cost: number;
    total_profit: number;
    store_profit: number;
    investor_profit: number;
    count: number;
    total_items: number;
    avg_sale: number;
}

interface BranchSummary {
    branch_id: number | null;
    branch_name: string;
    total_sales: number;
    total_profit: number;
    total_due: number;
    count: number;
}

interface DailySummary {
    date: string;
    total_sales: number;
    total_profit: number;
    count: number;
}

interface CustomerSummary {
    customer_id: number | null;
    customer_name: string;
    total_sales: number;
    total_due: number;
    count: number;
}

interface ProductSummary {
    variant_id: number;
    product_name: string;
    variant_name: string;
    total_quantity: number;
    total_sales: number;
    total_profit: number;
}

interface Props {
    sales: Sale[];
    summary: Summary;
    byBranch: BranchSummary[];
    byDay: DailySummary[];
    byCustomer: CustomerSummary[];
    byProduct: ProductSummary[];
    filters: {
        search: string | null;
        branch_id: string | null;
        customer_id: string | null;
        payment_status: string | null;
        status: string | null;
        only_due: boolean;
        date_from?: string | null;
        date_to?: string | null;
    };
    branches: { id: number; name: string; code: string }[];
    customers: { id: number; name: string; phone: string | null }[];
    canEditCompleted?: boolean;
}

/* ═══════════════════════════════════════════════════════
 |  Constants
 ═══════════════════════════════════════════════════════ */

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales', href: '/sales' },
    { title: 'Report', href: '/sales/report' },
];

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const fmt = (value: string | number, digits: number = 0) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(num || 0);
};

const getPaymentBadge = (status: string) => {
    const map: Record<string, { variant: 'destructive' | 'secondary' | 'success'; label: string }> = {
        unpaid: { variant: 'destructive', label: 'Unpaid' },
        partial: { variant: 'secondary', label: 'Partial' },
        paid: { variant: 'success', label: 'Paid' },
    };
    const item = map[status] || map.unpaid;
    return <Badge variant={item.variant}>{item.label}</Badge>;
};

const getStatusBadge = (status: string) => {
    const map: Record<string, { variant: 'outline' | 'success' | 'destructive'; label: string }> = {
        draft: { variant: 'outline', label: 'Draft' },
        completed: { variant: 'success', label: 'Completed' },
        cancelled: { variant: 'destructive', label: 'Cancelled' },
    };
    const item = map[status] || map.draft;
    return <Badge variant={item.variant}>{item.label}</Badge>;
};

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function SalesReport({ sales, summary, byBranch, byDay, byCustomer, byProduct, filters, branches, customers }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [branchFilter, setBranchFilter] = useState(filters.branch_id || 'all');
    const [customerFilter, setCustomerFilter] = useState(filters.customer_id || 'all');
    const [paymentFilter, setPaymentFilter] = useState(filters.payment_status || 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [onlyDue, setOnlyDue] = useState<boolean>(filters.only_due || false);
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [activeTab, setActiveTab] = useState<'summary' | 'daily' | 'branch' | 'customer' | 'product' | 'sales'>('summary');

    /* ═══════════ Apply Filters ═══════════ */

    const applyFilters = () => {
        router.get(
            '/sales/report',
            {
                search: search || undefined,
                branch_id: branchFilter === 'all' ? undefined : branchFilter,
                customer_id: customerFilter === 'all' ? undefined : customerFilter,
                payment_status: paymentFilter === 'all' ? undefined : paymentFilter,
                status: statusFilter === 'all' ? undefined : statusFilter,
                only_due: onlyDue ? 1 : undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const resetFilters = () => {
        setSearch('');
        setBranchFilter('all');
        setCustomerFilter('all');
        setPaymentFilter('all');
        setStatusFilter('all');
        setOnlyDue(false);
        setDateFrom('');
        setDateTo('');
        router.get('/sales/report', {}, { preserveState: false });
    };

    /* ═══════════ Print ═══════════ */

    const handlePrintReport = () => {
        window.print();
    };

    /* ═══════════ Export CSV ═══════════ */

    const exportCsv = () => {
        const headers = [
            'Invoice',
            'Date',
            'Customer',
            'Branch',
            'Total AFN',
            'Paid AFN',
            'Due AFN',
            'Cost AFN',
            'Profit AFN',
            'Store Profit',
            'Investor Profit',
            'Payment Status',
            'Status',
        ];

        const rows = sales.map((s) => [
            s.invoice_number,
            new Date(s.sale_date).toLocaleDateString('en-US'),
            s.customer?.name || 'Walk-in',
            s.branch?.name || '—',
            s.total_afn,
            s.paid_afn,
            s.remaining_due_afn,
            s.total_cost_afn,
            s.total_profit_afn,
            s.store_profit_afn,
            s.total_investor_profit_afn,
            s.payment_status,
            s.status,
        ]);

        const csvContent = [headers, ...rows].map((row) => row.map((c) => `"${c}"`).join(',')).join('\n');

        const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `sales-report-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    };

    /* ═══════════ Derived ═══════════ */

    const maxDailySales = useMemo(() => Math.max(...byDay.map((d) => d.total_sales), 1), [byDay]);
    const maxBranchSales = useMemo(() => Math.max(...byBranch.map((b) => b.total_sales), 1), [byBranch]);

    /* ═══════════════════════════════════════════════════════
     |  Render
     ═══════════════════════════════════════════════════════ */

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sales Report" />

            <div className="flex flex-col gap-6 p-6 print:p-0">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <div className="flex items-center gap-3">
                        <Link href="/sales">
                            <Button variant="outline" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <BarChart3 className="text-primary h-6 w-6" />
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Sales Report</h1>
                            <p className="text-muted-foreground text-xs">
                                {summary.count} sales · {summary.total_items} items
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" className="gap-2" onClick={exportCsv}>
                            <Download className="h-4 w-4" /> Export CSV
                        </Button>
                        <Button variant="outline" className="gap-2" onClick={handlePrintReport}>
                            <Printer className="h-4 w-4" /> Print
                        </Button>
                    </div>
                </div>

                {/* ═══════════ Filters ═══════════ */}
                <Card className="print:hidden">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Filter className="h-4 w-4" /> Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-4">
                            <div className="space-y-2 md:col-span-2">
                                <Label>Search</Label>
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') applyFilters();
                                    }}
                                    placeholder="Invoice, receipt, or phone..."
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>From Date</Label>
                                <Input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
                            </div>

                            <div className="space-y-2">
                                <Label>To Date</Label>
                                <Input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
                            </div>

                            {branches.length > 1 && (
                                <div className="space-y-2">
                                    <Label>Branch</Label>
                                    <Select value={branchFilter} onValueChange={setBranchFilter}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Branches</SelectItem>
                                            {branches.map((b) => (
                                                <SelectItem key={b.id} value={String(b.id)}>
                                                    {b.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label>Customer</Label>
                                <Select value={customerFilter} onValueChange={setCustomerFilter}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Customers</SelectItem>
                                        {customers.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label>Payment</Label>
                                <Select value={paymentFilter} onValueChange={setPaymentFilter}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Payment</SelectItem>
                                        <SelectItem value="unpaid">Unpaid</SelectItem>
                                        <SelectItem value="partial">Partial</SelectItem>
                                        <SelectItem value="paid">Paid</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label>Status</Label>
                                <Select value={statusFilter} onValueChange={setStatusFilter}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="draft">Draft</SelectItem>
                                        <SelectItem value="completed">Completed</SelectItem>
                                        <SelectItem value="cancelled">Cancelled</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex items-end gap-2">
                                <Button
                                    type="button"
                                    variant={onlyDue ? 'default' : 'outline'}
                                    onClick={() => setOnlyDue(!onlyDue)}
                                    className="w-full gap-2"
                                >
                                    <Banknote className="h-4 w-4" /> Only with Due
                                </Button>
                            </div>

                            <div className="flex items-end gap-2 md:col-span-2">
                                <Button onClick={applyFilters} className="flex-1">
                                    Apply Filters
                                </Button>
                                <Button variant="outline" onClick={resetFilters}>
                                    Reset
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ Summary Cards ═══════════ */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Sales</p>
                                <DollarSign className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold">{fmt(summary.total_sales)} AFN</p>
                            <p className="text-muted-foreground mt-0.5 text-xs">{summary.count} invoices</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Paid</p>
                                <Banknote className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold text-emerald-700">{fmt(summary.total_paid)} AFN</p>
                            <p className="text-muted-foreground mt-0.5 text-xs">
                                {summary.total_sales > 0 ? `${((summary.total_paid / summary.total_sales) * 100).toFixed(1)}% of total` : '—'}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Due</p>
                                <TrendingDown className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold text-red-700">{fmt(summary.total_due)} AFN</p>
                            <p className="text-muted-foreground mt-0.5 text-xs">outstanding from customers</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Profit</p>
                                <TrendingUp className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold text-emerald-700">{fmt(summary.total_profit)} AFN</p>
                            <p className="text-muted-foreground mt-0.5 text-xs">avg {fmt(summary.avg_sale)} per sale</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Store Profit</p>
                                <ShoppingBag className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold text-emerald-700">{fmt(summary.store_profit)} AFN</p>
                            <p className="text-muted-foreground mt-0.5 text-xs">shop share</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Investor Profit</p>
                                <Users className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold text-blue-700">{fmt(summary.investor_profit)} AFN</p>
                            <p className="text-muted-foreground mt-0.5 text-xs">owed to investors</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Cost</p>
                                <Package className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold">{fmt(summary.total_cost)} AFN</p>
                            <p className="text-muted-foreground mt-0.5 text-xs">cost of goods sold</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Items</p>
                                <Package className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold">{summary.total_items}</p>
                            <p className="text-muted-foreground mt-0.5 text-xs">units sold</p>
                        </CardContent>
                    </Card>
                </div>

                {/* ═══════════ Tabs ═══════════ */}
                <div className="flex flex-wrap gap-1 border-b print:hidden">
                    {[
                        { key: 'summary', label: 'Summary', icon: BarChart3 },
                        { key: 'daily', label: 'By Day', icon: Calendar },
                        { key: 'branch', label: 'By Branch', icon: ShoppingBag },
                        { key: 'customer', label: 'By Customer', icon: Users },
                        { key: 'product', label: 'By Product', icon: Package },
                        { key: 'sales', label: 'All Sales', icon: DollarSign },
                    ].map((tab) => {
                        const Icon = tab.icon;
                        return (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveTab(tab.key as typeof activeTab)}
                                className={`flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition ${
                                    activeTab === tab.key
                                        ? 'border-primary text-primary'
                                        : 'text-muted-foreground hover:text-foreground border-transparent'
                                }`}
                            >
                                <Icon className="h-4 w-4" />
                                {tab.label}
                            </button>
                        );
                    })}
                </div>

                {/* ═══════════ Tab Content ═══════════ */}

                {/* ─── Summary Tab ─── */}
                {activeTab === 'summary' && (
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        {/* Top Products */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Top Products</CardTitle>
                                <CardDescription>Best selling variants by quantity</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Product</TableHead>
                                            <TableHead className="text-right">Qty</TableHead>
                                            <TableHead className="text-right">Sales</TableHead>
                                            <TableHead className="text-right">Profit</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {byProduct.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={4} className="text-muted-foreground h-16 text-center">
                                                    No product sales
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            byProduct.slice(0, 10).map((p) => (
                                                <TableRow key={p.variant_id}>
                                                    <TableCell>
                                                        <div className="text-sm font-medium">{p.product_name}</div>
                                                        <div className="text-muted-foreground text-xs">{p.variant_name}</div>
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono text-sm">{p.total_quantity}</TableCell>
                                                    <TableCell className="text-right font-mono text-sm">{fmt(p.total_sales)}</TableCell>
                                                    <TableCell className="text-right font-mono text-sm font-semibold text-emerald-700">
                                                        {fmt(p.total_profit)}
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        {/* Top Customers */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Top Customers</CardTitle>
                                <CardDescription>Customers with highest purchase amounts</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Customer</TableHead>
                                            <TableHead className="text-right">Count</TableHead>
                                            <TableHead className="text-right">Total</TableHead>
                                            <TableHead className="text-right">Due</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {byCustomer.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={4} className="text-muted-foreground h-16 text-center">
                                                    No customer data
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            byCustomer.slice(0, 10).map((c) => (
                                                <TableRow key={c.customer_id ?? 'walk-in'}>
                                                    <TableCell className="text-sm font-medium">{c.customer_name}</TableCell>
                                                    <TableCell className="text-right font-mono text-sm">{c.count}</TableCell>
                                                    <TableCell className="text-right font-mono text-sm">{fmt(c.total_sales)}</TableCell>
                                                    <TableCell className="text-right font-mono text-sm font-semibold text-red-700">
                                                        {fmt(c.total_due)}
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* ─── Daily Tab ─── */}
                {activeTab === 'daily' && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Sales by Day</CardTitle>
                            <CardDescription>Daily breakdown of sales and profit</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 space-y-2">
                                {byDay.slice(0, 15).map((d) => (
                                    <div key={d.date} className="flex items-center gap-3">
                                        <span className="text-muted-foreground w-24 font-mono text-xs">{d.date}</span>
                                        <div className="bg-muted h-6 flex-1 overflow-hidden rounded">
                                            <div
                                                className="bg-primary h-full transition-all"
                                                style={{ width: `${(d.total_sales / maxDailySales) * 100}%` }}
                                            />
                                        </div>
                                        <span className="w-24 text-right font-mono text-xs font-semibold">{fmt(d.total_sales)}</span>
                                        <span className="text-muted-foreground w-16 text-right text-xs">{d.count}×</span>
                                    </div>
                                ))}
                            </div>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead className="text-right">Sales Count</TableHead>
                                        <TableHead className="text-right">Total Sales</TableHead>
                                        <TableHead className="text-right">Total Profit</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {byDay.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-muted-foreground h-16 text-center">
                                                No daily data
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        byDay.map((d) => (
                                            <TableRow key={d.date}>
                                                <TableCell className="font-mono text-xs">{d.date}</TableCell>
                                                <TableCell className="text-right font-mono text-sm">{d.count}</TableCell>
                                                <TableCell className="text-right font-mono text-sm font-semibold">{fmt(d.total_sales)}</TableCell>
                                                <TableCell className="text-right font-mono text-sm text-emerald-700">{fmt(d.total_profit)}</TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {/* ─── Branch Tab ─── */}
                {activeTab === 'branch' && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Sales by Branch</CardTitle>
                            <CardDescription>Performance comparison across branches</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 space-y-2">
                                {byBranch.map((b) => (
                                    <div key={b.branch_id ?? 'none'} className="flex items-center gap-3">
                                        <span className="w-32 truncate text-sm font-medium">{b.branch_name}</span>
                                        <div className="bg-muted h-6 flex-1 overflow-hidden rounded">
                                            <div
                                                className="h-full bg-emerald-500 transition-all"
                                                style={{ width: `${(b.total_sales / maxBranchSales) * 100}%` }}
                                            />
                                        </div>
                                        <span className="w-24 text-right font-mono text-xs font-semibold">{fmt(b.total_sales)}</span>
                                    </div>
                                ))}
                            </div>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Branch</TableHead>
                                        <TableHead className="text-right">Sales Count</TableHead>
                                        <TableHead className="text-right">Total Sales</TableHead>
                                        <TableHead className="text-right">Total Profit</TableHead>
                                        <TableHead className="text-right">Total Due</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {byBranch.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={5} className="text-muted-foreground h-16 text-center">
                                                No branch data
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        byBranch.map((b) => (
                                            <TableRow key={b.branch_id ?? 'none'}>
                                                <TableCell className="font-medium">{b.branch_name}</TableCell>
                                                <TableCell className="text-right font-mono text-sm">{b.count}</TableCell>
                                                <TableCell className="text-right font-mono text-sm font-semibold">{fmt(b.total_sales)}</TableCell>
                                                <TableCell className="text-right font-mono text-sm text-emerald-700">{fmt(b.total_profit)}</TableCell>
                                                <TableCell className="text-right font-mono text-sm text-red-700">{fmt(b.total_due)}</TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {/* ─── Customer Tab ─── */}
                {activeTab === 'customer' && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Sales by Customer</CardTitle>
                            <CardDescription>Customer purchase history</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Customer</TableHead>
                                        <TableHead className="text-right">Sales Count</TableHead>
                                        <TableHead className="text-right">Total Sales</TableHead>
                                        <TableHead className="text-right">Total Due</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {byCustomer.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-muted-foreground h-16 text-center">
                                                No customer data
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        byCustomer.map((c) => (
                                            <TableRow key={c.customer_id ?? 'walk-in'}>
                                                <TableCell className="font-medium">{c.customer_name}</TableCell>
                                                <TableCell className="text-right font-mono text-sm">{c.count}</TableCell>
                                                <TableCell className="text-right font-mono text-sm font-semibold">{fmt(c.total_sales)}</TableCell>
                                                <TableCell className="text-right font-mono text-sm text-red-700">{fmt(c.total_due)}</TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {/* ─── Product Tab ─── */}
                {activeTab === 'product' && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Sales by Product</CardTitle>
                            <CardDescription>Product variant performance</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead>Variant</TableHead>
                                        <TableHead className="text-right">Qty Sold</TableHead>
                                        <TableHead className="text-right">Total Sales</TableHead>
                                        <TableHead className="text-right">Total Profit</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {byProduct.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={5} className="text-muted-foreground h-16 text-center">
                                                No product data
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        byProduct.map((p) => (
                                            <TableRow key={p.variant_id}>
                                                <TableCell className="font-medium">{p.product_name}</TableCell>
                                                <TableCell className="text-sm">{p.variant_name}</TableCell>
                                                <TableCell className="text-right font-mono text-sm">{p.total_quantity}</TableCell>
                                                <TableCell className="text-right font-mono text-sm font-semibold">{fmt(p.total_sales)}</TableCell>
                                                <TableCell className="text-right font-mono text-sm text-emerald-700">{fmt(p.total_profit)}</TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {/* ─── All Sales Tab ─── */}
                {activeTab === 'sales' && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">All Sales</CardTitle>
                            <CardDescription>Detailed list of all filtered sales</CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Invoice</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Branch</TableHead>
                                        <TableHead className="text-right">Total</TableHead>
                                        <TableHead className="text-right">Paid</TableHead>
                                        <TableHead className="text-right">Due</TableHead>
                                        <TableHead className="text-right">Profit</TableHead>
                                        <TableHead>Payment</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sales.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={10} className="text-muted-foreground h-24 text-center">
                                                No sales match the filters
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        sales.map((s) => (
                                            <TableRow key={s.id}>
                                                <TableCell>
                                                    <Link
                                                        href={`/sales/${s.id}`}
                                                        className="text-primary font-mono text-xs font-semibold hover:underline"
                                                    >
                                                        {s.invoice_number}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="text-xs">{new Date(s.sale_date).toLocaleDateString('en-US')}</TableCell>
                                                <TableCell className="text-sm">
                                                    {s.customer?.name || <span className="text-muted-foreground">Walk-in</span>}
                                                </TableCell>
                                                <TableCell className="text-sm">{s.branch?.name || '—'}</TableCell>
                                                <TableCell className="text-right font-mono text-sm font-semibold">{fmt(s.total_afn)}</TableCell>
                                                <TableCell className="text-right font-mono text-xs text-emerald-700">{fmt(s.paid_afn)}</TableCell>
                                                <TableCell className="text-right font-mono text-xs text-red-700">
                                                    {fmt(s.remaining_due_afn)}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-xs text-emerald-700">
                                                    {fmt(s.total_profit_afn)}
                                                </TableCell>
                                                <TableCell>{getPaymentBadge(s.payment_status)}</TableCell>
                                                <TableCell>{getStatusBadge(s.status)}</TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Print Styles */}
            <style>{`
                @media print {
                    body { background: white !important; }
                    .print\\:hidden { display: none !important; }
                    .print\\:p-0 { padding: 0 !important; }
                    table { font-size: 10px; }
                }
            `}</style>
        </AppLayout>
    );
}
