// resources/js/pages/sales/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import ByCurrencyAmounts from '@/components/money/by-currency-amounts';
import { DeleteAction, EditAction, PrintAction, RowActions, ViewAction } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatOriginalAmount } from '@/lib/original-currency';
import type { OriginalCurrencyTotal } from '@/lib/original-currency';
import { getSwal } from '@/lib/swal';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Banknote, BarChart3, DollarSign, Plus, ShoppingBag, Trash2, TrendingDown, TrendingUp } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Sale {
    id: number;
    invoice_number: string;
    receipt_no: string | null;
    sale_date: string;
    total_afn: string;
    paid_afn: string;
    due_afn: string;
    previous_due_afn: string;
    new_sale_amount_afn: string;
    grand_total_due_afn: string;
    received_now_afn: string;
    remaining_due_afn: string;
    total_profit_afn: string;
    total_investor_profit_afn: string;
    store_profit_afn: string;
    payment_status: 'unpaid' | 'partial' | 'paid';
    status: 'draft' | 'completed' | 'cancelled';
    items_count: number;
    printed_count: number;
    exchange_rate?: number | null;
    sale_currency_code?: string | null;
    sale_currency_symbol?: string | null;
    sale_currency_known?: boolean;
    sale_currency_label?: string;
    display_amount?: number | null;
    display_paid?: number | null;
    display_remaining?: number | null;
    branch: { id: number; name: string; code: string } | null;
    customer: { id: number; name: string; phone: string | null } | null;
    creator: { id: number; name: string } | null;
}

interface Props {
    sales: {
        data: Sale[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    branches: { id: number; name: string; code: string }[];
    customers: { id: number; name: string; phone: string | null }[];
    monthlyRates?: { year: number; month: number; average_rate: string; days: number }[];
    filters: {
        search: string | null;
        branch_id: string | null;
        customer_id: string | null;
        payment_status: string | null;
        payment_method: string | null;
        status: string | null;
        only_due: boolean;
    };
    stats: {
        today_sales: string;
        today_count: number;
        today_profit: string;
        total_due: string;
        today_sales_by_currency?: OriginalCurrencyTotal[];
        today_profit_by_currency?: OriginalCurrencyTotal[];
        total_due_by_currency?: OriginalCurrencyTotal[];
        avg_usd_rate?: number;
        latest_usd_rate?: number;
    };
    canEditCompleted?: boolean;
}

/* ═══════════════════════════════════════════════════════
 |  Constants
 ═══════════════════════════════════════════════════════ */

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales', href: '/sales' },
];

/** Number of visible table columns — keep in sync with <TableHeader>. */
const COLUMN_COUNT = 11;

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const formatPrice = (value: string | number, digits: number = 0): string => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(Number.isFinite(num) ? num : 0);
};

const getPaymentBadge = (status: string) => {
    const map: Record<string, { variant: 'destructive' | 'secondary' | 'success'; label: string }> = {
        unpaid: { variant: 'destructive', label: 'Unpaid' },
        partial: { variant: 'secondary', label: 'Partial' },
        paid: { variant: 'success', label: 'Paid' },
    };
    const item = map[status] ?? map.unpaid;
    return <Badge variant={item.variant}>{item.label}</Badge>;
};

const getStatusBadge = (status: string) => {
    const map: Record<string, { variant: 'outline' | 'success' | 'destructive'; label: string }> = {
        draft: { variant: 'outline', label: 'Draft' },
        completed: { variant: 'success', label: 'Completed' },
        cancelled: { variant: 'destructive', label: 'Cancelled' },
    };
    const item = map[status] ?? map.draft;
    return <Badge variant={item.variant}>{item.label}</Badge>;
};

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function SalesIndex({
    sales,
    branches,
    customers,
    filters,
    stats,
    monthlyRates = [],
    canEditCompleted = false,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [branchFilter, setBranchFilter] = useState(filters.branch_id || 'all');
    const [customerFilter, setCustomerFilter] = useState(filters.customer_id || 'all');
    const [paymentFilter, setPaymentFilter] = useState(filters.payment_status || 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [onlyDue, setOnlyDue] = useState<boolean>(filters.only_due || false);

    /* ═══════════════════════════════════════════════════════
     |  URL builder (single source of truth)
     ═══════════════════════════════════════════════════════ */

    const buildQuery = (overrides: Partial<{ search: string; branch_id: string; customer_id: string; payment_status: string; status: string; only_due: boolean }> = {}) => ({
        search: (overrides.search ?? search) || undefined,
        branch_id: (overrides.branch_id ?? branchFilter) === 'all' ? undefined : (overrides.branch_id ?? branchFilter),
        customer_id: (overrides.customer_id ?? customerFilter) === 'all' ? undefined : (overrides.customer_id ?? customerFilter),
        payment_status: (overrides.payment_status ?? paymentFilter) === 'all' ? undefined : (overrides.payment_status ?? paymentFilter),
        status: (overrides.status ?? statusFilter) === 'all' ? undefined : (overrides.status ?? statusFilter),
        only_due: (overrides.only_due ?? onlyDue) ? 1 : undefined,
    });

    /* ═══════════════════════════════════════════════════════
     |  Handlers
     ═══════════════════════════════════════════════════════ */

    /** Manual search submit — pushes a new history entry. */
    const handleSearch = () => {
        router.get('/sales', buildQuery(), { preserveState: true, preserveScroll: true });
    };

    /** Skip the very first render so mount doesn't trigger a redundant fetch. */
    const isFirstRender = useRef(true);

    /** Debounced live-search when *any* filter or the search text changes. */
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = window.setTimeout(() => {
            router.get('/sales', buildQuery(), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, branchFilter, customerFilter, paymentFilter, statusFilter, onlyDue]);

    const handlePageChange = (url: string | null) => {
        if (!url) return;
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    /** Open the receipt print view in a new tab. */
    const handlePrint = (sale: Sale) => {
        window.open(`/sales/${sale.id}/receipt`, '_blank', 'noopener,noreferrer');
    };

    /** Delete a sale after explicit confirmation. */
    const handleDelete = async (sale: Sale) => {
        const Swal = await getSwal();
        const result = await Swal.fire({
            icon: 'warning',
            title: 'Delete this sale?',
            html: `Are you sure you want to delete invoice <strong>${sale.invoice_number}</strong>?<br><span style="color:#b91c1c;">This will move it to trash.</span>`,
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
        });

        if (!result.isConfirmed) return;

        router.delete(`/sales/${sale.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                void Swal.fire({
                    icon: 'success',
                    title: 'Deleted',
                    text: `Sale "${sale.invoice_number}" moved to trash.`,
                    timer: 1800,
                    showConfirmButton: false,
                });
            },
            onError: () => {
                void Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Could not delete this sale.',
                    confirmButtonColor: '#dc2626',
                });
            },
        });
    };

    /** Open the sales report in a new tab, carrying the current filters. */
    const handleOpenReport = () => {
        const params = new URLSearchParams();
        const q = buildQuery();
        if (q.search) params.append('search', q.search);
        if (q.branch_id) params.append('branch_id', q.branch_id);
        if (q.customer_id) params.append('customer_id', q.customer_id);
        if (q.payment_status) params.append('payment_status', q.payment_status);
        if (q.status) params.append('status', q.status);
        if (q.only_due) params.append('only_due', '1');

        const query = params.toString();
        window.open(`/sales/report${query ? `?${query}` : ''}`, '_blank', 'noopener,noreferrer');
    };

    /* ═══════════════════════════════════════════════════════
     |  Render
     ═══════════════════════════════════════════════════════ */

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sales" />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <ShoppingBag className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Sales</h1>
                        <Badge variant="outline">{sales.total} Sales</Badge>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" className="gap-2" onClick={handleOpenReport}>
                            <BarChart3 className="h-4 w-4" /> Sales Report
                        </Button>
                        <Link href="/sales/trash">
                            <Button variant="outline" className="gap-2">
                                <Trash2 className="h-4 w-4" /> Trash
                            </Button>
                        </Link>
                        <Link href="/sales/create">
                            <Button className="gap-2">
                                <Plus className="h-4 w-4" /> New Sale
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* ═══════════ Stats Cards ═══════════ */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <div className="bg-card rounded-lg border p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Today Sales</p>
                            <DollarSign className="text-muted-foreground h-4 w-4" />
                        </div>
                        <ByCurrencyAmounts
                            amounts={stats.today_sales_by_currency}
                            titlePrefix="Sales"
                            valueClassName="font-mono text-sm font-bold"
                            digits={2}
                        />
                    </div>
                    <div className="bg-card rounded-lg border p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Today Count</p>
                            <ShoppingBag className="text-muted-foreground h-4 w-4" />
                        </div>
                        <p className="mt-1 text-2xl font-bold">{stats.today_count}</p>
                    </div>
                    <div className="bg-card rounded-lg border p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Today Profit</p>
                            <TrendingUp className="text-muted-foreground h-4 w-4" />
                        </div>
                        <ByCurrencyAmounts
                            amounts={stats.today_profit_by_currency}
                            titlePrefix="Profit"
                            valueClassName="font-mono text-sm font-bold"
                            digits={2}
                        />
                    </div>
                    <div className="bg-card rounded-lg border p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Total Due</p>
                            <TrendingDown className="text-muted-foreground h-4 w-4" />
                        </div>
                        <ByCurrencyAmounts
                            amounts={stats.total_due_by_currency}
                            titlePrefix="Due"
                            valueClassName="font-mono text-sm font-bold"
                            digits={2}
                        />
                    </div>
                    <div className="bg-card rounded-lg border p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Avg USD→AFN</p>
                            <DollarSign className="text-muted-foreground h-4 w-4" />
                        </div>
                        <p className="mt-1 font-mono text-2xl font-bold">
                            {stats.avg_usd_rate && stats.avg_usd_rate > 0 ? formatPrice(stats.avg_usd_rate, 2) : '—'}
                        </p>
                        <p className="text-muted-foreground mt-0.5 text-[10px]">
                            {stats.latest_usd_rate && stats.latest_usd_rate > 0
                                ? `latest ${formatPrice(stats.latest_usd_rate, 2)}`
                                : 'this month average'}
                        </p>
                    </div>
                </div>

                <IndexFilters
                    search={search}
                    onSearchChange={setSearch}
                    onSearch={handleSearch}
                    placeholder="Search invoice, receipt, or customer phone..."
                >
                    {branches.length > 1 && (
                        <FilterField label="Branch">
                            <Select value={branchFilter} onValueChange={setBranchFilter}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Branch" />
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
                        </FilterField>
                    )}
                    <FilterField label="Customer">
                        <Select value={customerFilter} onValueChange={setCustomerFilter}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Customer" />
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
                    </FilterField>
                    <FilterField label="Payment">
                        <Select value={paymentFilter} onValueChange={setPaymentFilter}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Payment" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Payments</SelectItem>
                                <SelectItem value="unpaid">Unpaid</SelectItem>
                                <SelectItem value="partial">Partial</SelectItem>
                                <SelectItem value="paid">Paid</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>
                    <FilterField label="Status">
                        <Select value={statusFilter} onValueChange={setStatusFilter}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>
                    <FilterField label="Due">
                        <Button
                            type="button"
                            variant={onlyDue ? 'default' : 'outline'}
                            onClick={() => setOnlyDue((v) => !v)}
                            className="w-full gap-2"
                            aria-pressed={onlyDue}
                        >
                            <Banknote className="h-4 w-4" />
                            Only with Due
                        </Button>
                    </FilterField>
                </IndexFilters>

                {monthlyRates.length > 0 && (
                    <section aria-label="Monthly average USD to AFN exchange rates">
                        <div className="flex flex-wrap gap-2">
                            {monthlyRates.map((row) => (
                                <div key={`${row.year}-${row.month}`} className="rounded-md border px-3 py-2 text-sm">
                                    <span className="text-muted-foreground">
                                        {new Date(row.year, row.month - 1, 1).toLocaleString('en-US', {
                                            month: 'long',
                                            year: 'numeric',
                                        })}
                                    </span>
                                    <span className="ml-2 font-mono font-semibold">{row.average_rate}</span>
                                    <span className="text-muted-foreground ml-1 text-xs">avg USD to AFN</span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {/* ═══════════ Table ═══════════ */}
                <div className="overflow-x-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-12">#</TableHead>
                                <TableHead>Invoice</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead className="text-center">Currency</TableHead>
                                <TableHead className="text-center">USD→AFN</TableHead>
                                <TableHead className="text-right">Sale</TableHead>
                                <TableHead className="text-right">Paid</TableHead>
                                <TableHead className="text-right">Remaining</TableHead>
                                <TableHead>Payment</TableHead>
                                <TableHead className="w-[178px] px-1 text-center whitespace-nowrap">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sales.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={COLUMN_COUNT} className="text-muted-foreground h-24 text-center">
                                        No sales found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                sales.data.map((sale, index) => {
                                    const canDelete = sale.status !== 'completed' || canEditCompleted;
                                    const code = sale.sale_currency_known === false ? null : sale.sale_currency_code;

                                    return (
                                        <TableRow key={sale.id}>
                                            <TableCell>{sales.from ? sales.from + index : index + 1}</TableCell>
                                            <TableCell>
                                                <div className="font-mono text-xs font-semibold">{sale.invoice_number}</div>
                                                {sale.receipt_no && (
                                                    <div className="text-muted-foreground font-mono text-[10px]">{sale.receipt_no}</div>
                                                )}
                                                {sale.printed_count > 0 && (
                                                    <div className="text-muted-foreground mt-0.5 text-[10px]">
                                                        🖨️ Printed {sale.printed_count}×
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {sale.customer ? (
                                                    <>
                                                        <div className="font-medium">{sale.customer.name}</div>
                                                        {sale.customer.phone && (
                                                            <div className="text-muted-foreground text-xs" dir="ltr">
                                                                {sale.customer.phone}
                                                            </div>
                                                        )}
                                                    </>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">Walk-in</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                {new Date(sale.sale_date).toLocaleDateString('en-US')}
                                            </TableCell>
                                            <TableCell className="text-center text-xs font-semibold">
                                                {sale.sale_currency_label || (code ? code : 'Unknown')}
                                            </TableCell>
                                            <TableCell className="text-center font-mono text-sm font-semibold">
                                                {sale.exchange_rate ? formatPrice(sale.exchange_rate, 2) : '—'}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-sm font-semibold">
                                                {formatOriginalAmount(sale.display_amount, code)}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-xs">
                                                {formatOriginalAmount(sale.display_paid, code)}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-xs font-semibold">
                                                {formatOriginalAmount(sale.display_remaining, code)}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-col gap-1">
                                                    {getPaymentBadge(sale.payment_status)}
                                                    {getStatusBadge(sale.status)}
                                                </div>
                                            </TableCell>
                                            <TableCell className="px-1 text-center whitespace-nowrap">
                                                <RowActions>
                                                    <ViewAction href={`/sales/${sale.id}`} />
                                                    <EditAction href={`/sales/${sale.id}/edit`} />
                                                    <PrintAction onClick={() => handlePrint(sale)} />
                                                    {canDelete && <DeleteAction onClick={() => handleDelete(sale)} />}
                                                </RowActions>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* ═══════════ Pagination ═══════════ */}
                {sales.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {sales.from || 0} to {sales.to || 0} of {sales.total} results
                        </div>
                        <nav aria-label="Pagination" className="flex gap-1">
                            {sales.links.map((link, i) => (
                                <Button
                                    key={`${link.label}-${i}`}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => handlePageChange(link.url)}
                                    disabled={!link.url}
                                    className="min-w-[40px]"
                                    aria-current={link.active ? 'page' : undefined}
                                    aria-label={link.label.replace(/&[a-z]+;/gi, '').trim() || `Page ${i + 1}`}
                                    // Pagination labels come from Laravel and include HTML entities like &laquo;
                                    // They are server-controlled, not user-supplied.
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </nav>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}