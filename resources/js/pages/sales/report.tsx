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
    DollarSign,
    Download,
    Filter,
    Package,
    Printer,
    RotateCcw,
    ShoppingBag,
    TrendingDown,
    TrendingUp,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface SaleRow {
    id: number;
    invoice_number: string;
    sale_date: string;
    items_count: number;
    amount_afn: number;
    return_amount_afn: number;
    net_afn: number;
    return_type: 'shop' | 'customer' | 'mixed' | null;
    payment_status: string;
    status: string;
    branch: { id: number; name: string; code: string } | null;
    customer: { id: number; name: string; phone: string | null } | null;
    creator: { id: number; name: string } | null;
}

interface PaginatedSales {
    data: SaleRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Summary {
    gross_sales: number;
    total_returns: number;
    net_sales: number;
    total_sales: number;
    total_paid: number;
    total_due: number;
    total_cost: number;
    total_profit: number;
    store_profit: number;
    investor_profit: number;
    count: number;
    customer_count: number;
    total_items: number;
    avg_sale: number;
}

interface DailySummary {
    date: string;
    net_sales: number;
    total_returns: number;
    gross_sales: number;
    total_sales: number;
    count: number;
}

interface Props {
    sales: PaginatedSales;
    summary: Summary;
    byBranch: { branch_id: number; branch_name: string; total_sales: number; total_profit: number; total_due: number; count: number }[];
    byDay: DailySummary[];
    byCustomer: { customer_id: number | null; customer_name: string; total_sales: number; total_due: number; count: number }[];
    byProduct: { variant_id: number; product_name: string; variant_name: string; total_quantity: number; total_sales: number; total_profit: number }[];
    filters: {
        search: string | null;
        branch_id: string | null;
        customer_id: string | null;
        salesperson_id: string | null;
        payment_status: string | null;
        status: string | null;
        only_due: boolean;
        date_from?: string | null;
        date_to?: string | null;
    };
    branches: { id: number; name: string; code: string }[];
    customers: { id: number; name: string; phone: string | null }[];
    salespeople: { id: number; name: string }[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales', href: '/sales' },
    { title: 'Report', href: '/sales/report' },
];

const fmt = (value: string | number, digits: number = 0) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(num || 0);
};

const returnTypeLabel = (type: string | null) => {
    if (type === 'shop') return 'Shop';
    if (type === 'customer') return 'Customer';
    if (type === 'mixed') return 'Mixed';
    return '—';
};

export default function SalesReport({
    sales,
    summary,
    byBranch,
    byDay,
    byCustomer,
    byProduct,
    filters,
    branches,
    customers,
    salespeople = [],
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [branchFilter, setBranchFilter] = useState(filters.branch_id ? String(filters.branch_id) : 'all');
    const [customerFilter, setCustomerFilter] = useState(filters.customer_id ? String(filters.customer_id) : 'all');
    const [salespersonFilter, setSalespersonFilter] = useState(filters.salesperson_id ? String(filters.salesperson_id) : 'all');
    const [paymentFilter, setPaymentFilter] = useState(filters.payment_status || 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'completed');
    const [onlyDue, setOnlyDue] = useState<boolean>(filters.only_due || false);
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');

    const applyFilters = () => {
        router.get(
            '/sales/report',
            {
                search: search || undefined,
                branch_id: branchFilter === 'all' ? undefined : branchFilter,
                customer_id: customerFilter === 'all' ? undefined : customerFilter,
                salesperson_id: salespersonFilter === 'all' ? undefined : salespersonFilter,
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
        setSalespersonFilter('all');
        setPaymentFilter('all');
        setStatusFilter('completed');
        setOnlyDue(false);
        setDateFrom('');
        setDateTo('');
        router.get('/sales/report', {}, { preserveState: false });
    };

    const exportCsv = () => {
        const headers = ['Date', 'Invoice', 'Customer', 'Branch', 'Items', 'Amount', 'Return Expense By', 'Return Amount', 'Net', 'Salesperson'];
        const rows = (sales.data || []).map((s) => [
            new Date(s.sale_date).toLocaleDateString('en-GB'),
            s.invoice_number,
            s.customer?.name || 'Walk-in',
            s.branch?.name || '—',
            s.items_count,
            s.amount_afn,
            returnTypeLabel(s.return_type),
            s.return_amount_afn,
            s.net_afn,
            s.creator?.name || '—',
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

    const chartData = useMemo(
        () =>
            [...byDay].map((d) => ({
                date: d.date,
                net: d.net_sales,
                returns: d.total_returns,
                gross: d.gross_sales,
            })),
        [byDay],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sales Report" />

            <div className="flex flex-col gap-6 p-6 print:p-0">
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
                                {summary.count} invoices · {summary.customer_count} customers · Net = Gross − Returns
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" className="gap-2" onClick={exportCsv}>
                            <Download className="h-4 w-4" /> Export CSV / Excel
                        </Button>
                        <Button variant="outline" className="gap-2" onClick={() => window.print()}>
                            <Printer className="h-4 w-4" /> Print / PDF
                        </Button>
                    </div>
                </div>

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
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
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
                                <Label>Salesperson</Label>
                                <Select value={salespersonFilter} onValueChange={setSalespersonFilter}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Salespeople</SelectItem>
                                        {salespeople.map((u) => (
                                            <SelectItem key={u.id} value={String(u.id)}>
                                                {u.name}
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

                {/* Summary cards */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Gross Sales</p>
                                <DollarSign className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold">{fmt(summary.gross_sales)} AFN</p>
                            <p className="text-muted-foreground mt-0.5 text-xs">{summary.count} invoices</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Sales Returns</p>
                                <RotateCcw className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold text-amber-700">{fmt(summary.total_returns)} AFN</p>
                            <p className="text-muted-foreground mt-0.5 text-xs">completed returns</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Net Sales</p>
                                <TrendingUp className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold text-emerald-700">{fmt(summary.net_sales)} AFN</p>
                            <p className="text-muted-foreground mt-0.5 text-xs">Gross − Returns</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Customers / Invoices</p>
                                <Users className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold">
                                {summary.customer_count} / {summary.count}
                            </p>
                            <p className="text-muted-foreground mt-0.5 text-xs">{summary.total_items} items</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Paid</p>
                                <Banknote className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold text-emerald-700">{fmt(summary.total_paid)} AFN</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Due</p>
                                <TrendingDown className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold text-red-700">{fmt(summary.total_due)} AFN</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Store Profit</p>
                                <ShoppingBag className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold text-emerald-700">{fmt(summary.store_profit)} AFN</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Avg Sale</p>
                                <Package className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold">{fmt(summary.avg_sale)} AFN</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Net sales chart */}
                {chartData.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Daily Net Sales</CardTitle>
                            <CardDescription>Net sales over the selected date range</CardDescription>
                        </CardHeader>
                        <CardContent className="h-72">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={chartData} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                    <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                                    <YAxis tick={{ fontSize: 11 }} />
                                    <Tooltip formatter={(v: number) => `${fmt(v)} AFN`} />
                                    <Legend />
                                    <Line type="monotone" dataKey="net" name="Net Sales" stroke="#059669" strokeWidth={2} dot={false} />
                                    <Line type="monotone" dataKey="returns" name="Returns" stroke="#d97706" strokeWidth={2} dot={false} />
                                </LineChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                )}

                {byBranch.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">By Branch</CardTitle>
                        </CardHeader>
                        <CardContent className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={byBranch} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="branch_name" tick={{ fontSize: 11 }} />
                                    <YAxis tick={{ fontSize: 11 }} />
                                    <Tooltip formatter={(v: number) => `${fmt(v)} AFN`} />
                                    <Bar dataKey="total_sales" name="Net Sales" fill="#0ea5e9" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                )}

                {/* Detailed table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Sales Detail</CardTitle>
                        <CardDescription>
                            Showing {sales.data?.length || 0} of {sales.total || 0} invoices
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Invoice #</TableHead>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Branch</TableHead>
                                        <TableHead className="text-center">Items</TableHead>
                                        <TableHead className="text-right">Amount</TableHead>
                                        <TableHead>Return Expense By</TableHead>
                                        <TableHead className="text-right">Return Amount</TableHead>
                                        <TableHead className="text-right">Net</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {(sales.data || []).length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={9} className="text-muted-foreground h-24 text-center">
                                                No sales found for these filters.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        sales.data.map((sale) => (
                                            <TableRow
                                                key={sale.id}
                                                className="hover:bg-muted/50 cursor-pointer"
                                                onClick={() => router.visit(`/sales/${sale.id}`)}
                                            >
                                                <TableCell className="whitespace-nowrap text-sm">
                                                    {new Date(sale.sale_date).toLocaleDateString('en-GB')}
                                                </TableCell>
                                                <TableCell className="font-mono text-sm">
                                                    <Link href={`/sales/${sale.id}`} className="hover:text-primary" onClick={(e) => e.stopPropagation()}>
                                                        {sale.invoice_number}
                                                    </Link>
                                                </TableCell>
                                                <TableCell>{sale.customer?.name || 'Walk-in'}</TableCell>
                                                <TableCell>{sale.branch?.name || '—'}</TableCell>
                                                <TableCell className="text-center font-mono">{sale.items_count}</TableCell>
                                                <TableCell className="text-right font-mono">{fmt(sale.amount_afn)}</TableCell>
                                                <TableCell>
                                                    {sale.return_type ? (
                                                        <Badge variant="outline">{returnTypeLabel(sale.return_type)}</Badge>
                                                    ) : (
                                                        <span className="text-muted-foreground">—</span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-amber-700">
                                                    {sale.return_amount_afn > 0 ? fmt(sale.return_amount_afn) : '—'}
                                                </TableCell>
                                                <TableCell className="text-right font-mono font-semibold text-emerald-700">
                                                    {fmt(sale.net_afn)}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {sales.last_page > 1 && (
                            <div className="mt-4 flex flex-wrap justify-center gap-1 print:hidden">
                                {sales.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        size="sm"
                                        variant={link.active ? 'default' : 'outline'}
                                        disabled={!link.url}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {byCustomer.length > 0 && (
                    <Card className="print:hidden">
                        <CardHeader>
                            <CardTitle className="text-base">Top Customers</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Customer</TableHead>
                                            <TableHead className="text-center">Orders</TableHead>
                                            <TableHead className="text-right">Net Sales</TableHead>
                                            <TableHead className="text-right">Due</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {byCustomer.slice(0, 15).map((row) => (
                                            <TableRow key={row.customer_id ?? 'walkin'}>
                                                <TableCell>{row.customer_name}</TableCell>
                                                <TableCell className="text-center font-mono">{row.count}</TableCell>
                                                <TableCell className="text-right font-mono">{fmt(row.total_sales)}</TableCell>
                                                <TableCell className="text-right font-mono">{fmt(row.total_due)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {byProduct.length > 0 && (
                    <Card className="print:hidden">
                        <CardHeader>
                            <CardTitle className="text-base">Top Products</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Product</TableHead>
                                            <TableHead className="text-center">Qty</TableHead>
                                            <TableHead className="text-right">Sales</TableHead>
                                            <TableHead className="text-right">Profit</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {byProduct.slice(0, 15).map((row) => (
                                            <TableRow key={row.variant_id}>
                                                <TableCell>
                                                    {row.product_name}
                                                    {row.variant_name ? ` — ${row.variant_name}` : ''}
                                                </TableCell>
                                                <TableCell className="text-center font-mono">{row.total_quantity}</TableCell>
                                                <TableCell className="text-right font-mono">{fmt(row.total_sales)}</TableCell>
                                                <TableCell className="text-right font-mono">{fmt(row.total_profit)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
