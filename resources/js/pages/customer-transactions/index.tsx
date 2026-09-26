// resources/js/pages/customer-transactions/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, BookOpen, DollarSign, Eye, RefreshCw, TrendingDown, TrendingUp } from 'lucide-react';
import { useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Customer {
    id: number;
    name: string;
    phone: string | null;
    current_due_afn?: number | string;
}

interface Transaction {
    id: number;
    customer_id: number;
    type: 'sale' | 'payment' | 'return' | 'adjustment';
    amount_afn: string;
    balance_after: string;
    reference_type: string | null;
    reference_id: number | null;
    notes: string | null;
    created_at: string;
    customer: Customer | null;
    creator: { id: number; name: string } | null;
}

interface Stats {
    total_transactions: number;
    total_sales_afn: string;
    total_payments_afn: string;
    total_returns_afn: string;
    current_total_due: string;
}

interface Props {
    transactions: {
        data: Transaction[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    customers: Customer[];
    filters: {
        search: string | null;
        type: string | null;
        customer_id: string | null;
        from_date: string | null;
        to_date: string | null;
    };
    stats: Stats;
}

/* ═══════════════════════════════════════════════════════
 |  Constants
 ═══════════════════════════════════════════════════════ */

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customer Transactions', href: '/customer-transactions' },
];

const TYPE_META: Record<
    string,
    {
        label: string;
        variant: 'success' | 'destructive' | 'secondary' | 'outline';
        icon: any;
        color: string;
    }
> = {
    sale: {
        label: 'Sale',
        variant: 'destructive',
        icon: ArrowUpRight,
        color: 'text-red-600',
    },
    payment: {
        label: 'Payment',
        variant: 'success',
        icon: ArrowDownLeft,
        color: 'text-green-600',
    },
    return: {
        label: 'Return',
        variant: 'secondary',
        icon: TrendingDown,
        color: 'text-amber-600',
    },
    adjustment: {
        label: 'Adjustment',
        variant: 'outline',
        icon: RefreshCw,
        color: 'text-blue-600',
    },
};

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const formatPrice = (value: string | number, digits: number = 0) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(num || 0);
};

const formatDateTime = (value: string) => {
    return new Date(value).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getTypeBadge = (type: string) => {
    const meta = TYPE_META[type] || { label: type, variant: 'outline' as const, color: '' };
    return <Badge variant={meta.variant}>{meta.label}</Badge>;
};

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function CustomerTransactionsIndex({ transactions, customers, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [typeFilter, setTypeFilter] = useState(filters.type || 'all');
    const [customerFilter, setCustomerFilter] = useState(filters.customer_id || 'all');
    const [fromDate, setFromDate] = useState(filters.from_date || '');
    const [toDate, setToDate] = useState(filters.to_date || '');
    const handleSearch = () => {
        router.get(
            '/customer-transactions',
            {
                search: search || undefined,
                type: typeFilter === 'all' ? undefined : typeFilter,
                customer_id: customerFilter === 'all' ? undefined : customerFilter,
                from_date: fromDate || undefined,
                to_date: toDate || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setTypeFilter('all');
        setCustomerFilter('all');
        setFromDate('');
        setToDate('');
        router.get('/customer-transactions', {}, { preserveState: true, preserveScroll: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const hasFilters = !!search || typeFilter !== 'all' || customerFilter !== 'all' || !!fromDate || !!toDate;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Customer Transactions" />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <BookOpen className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Customer Ledger</h1>
                        <Badge variant="outline">{transactions.total} Transactions</Badge>
                    </div>
                </div>

                {/* ═══════════ Stats ═══════════ */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Txns</p>
                                <BookOpen className="h-4 w-4 text-blue-500" />
                            </div>
                            <p className="mt-1 text-2xl font-bold">{stats.total_transactions}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Sales</p>
                                <TrendingUp className="h-4 w-4 text-red-500" />
                            </div>
                            <p className="mt-1 text-xl font-bold text-red-600">{formatPrice(stats.total_sales_afn)} AFN</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Payments</p>
                                <TrendingDown className="h-4 w-4 text-green-500" />
                            </div>
                            <p className="mt-1 text-xl font-bold text-green-600">{formatPrice(stats.total_payments_afn)} AFN</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Returns</p>
                                <RefreshCw className="h-4 w-4 text-amber-500" />
                            </div>
                            <p className="mt-1 text-xl font-bold text-amber-600">{formatPrice(stats.total_returns_afn)} AFN</p>
                        </CardContent>
                    </Card>
                    <Card className="border-2 border-amber-300 bg-amber-50">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-xs text-amber-800">Current Total Due</p>
                                <DollarSign className="h-4 w-4 text-amber-600" />
                            </div>
                            <p className="mt-1 text-xl font-bold text-amber-900">{formatPrice(stats.current_total_due)} AFN</p>
                        </CardContent>
                    </Card>
                </div>

                <IndexFilters
                    search={search}
                    onSearchChange={setSearch}
                    onSearch={handleSearch}
                    onClear={clearFilters}
                    showClear={hasFilters}
                    placeholder="Search customer / notes..."
                >
                    <FilterField label="Type">
                        <Select value={typeFilter} onValueChange={setTypeFilter}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Types</SelectItem>
                                <SelectItem value="sale">Sale</SelectItem>
                                <SelectItem value="payment">Payment</SelectItem>
                                <SelectItem value="return">Return</SelectItem>
                                <SelectItem value="adjustment">Adjustment</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>
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
                    <FilterField label="From">
                        <Input type="date" value={fromDate} onChange={(e) => setFromDate(e.target.value)} />
                    </FilterField>
                    <FilterField label="To">
                        <Input type="date" value={toDate} onChange={(e) => setToDate(e.target.value)} />
                    </FilterField>
                </IndexFilters>

                {/* ═══════════ Table ═══════════ */}
                <div className="overflow-x-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead className="text-right">Amount (AFN)</TableHead>
                                <TableHead className="text-right">Balance After</TableHead>
                                <TableHead>Reference</TableHead>
                                <TableHead>Notes</TableHead>
                                <TableHead>By</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {transactions.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={10} className="text-muted-foreground h-24 text-center">
                                        No transactions found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                transactions.data.map((txn, index) => {
                                    const amount = parseFloat(txn.amount_afn || '0');
                                    const balance = parseFloat(txn.balance_after || '0');
                                    const meta = TYPE_META[txn.type] || TYPE_META.adjustment;
                                    const Icon = meta.icon;

                                    return (
                                        <TableRow key={txn.id}>
                                            <TableCell>{transactions.from ? transactions.from + index : index + 1}</TableCell>
                                            <TableCell className="text-xs">{formatDateTime(txn.created_at)}</TableCell>
                                            <TableCell>
                                                {txn.customer ? (
                                                    <Link
                                                        href={`/customers/${txn.customer.id}/transactions`}
                                                        className="hover:text-primary font-medium"
                                                    >
                                                        {txn.customer.name}
                                                        {txn.customer.phone && (
                                                            <div className="text-muted-foreground text-xs" dir="ltr">
                                                                {txn.customer.phone}
                                                            </div>
                                                        )}
                                                    </Link>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1.5">
                                                    <Icon className={`h-3.5 w-3.5 ${meta.color}`} />
                                                    {getTypeBadge(txn.type)}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <span
                                                    className={`font-mono text-sm font-semibold ${
                                                        amount > 0 ? 'text-red-600' : amount < 0 ? 'text-green-600' : 'text-muted-foreground'
                                                    }`}
                                                >
                                                    {amount > 0 ? '+' : ''}
                                                    {formatPrice(amount)}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <span
                                                    className={`font-mono text-xs font-medium ${balance > 0 ? 'text-amber-700' : 'text-green-600'}`}
                                                >
                                                    {formatPrice(balance)} AFN
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {txn.reference_type ? (
                                                    <span className="text-muted-foreground font-mono text-[10px]">
                                                        {txn.reference_type.split('\\').pop()} #{txn.reference_id}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <span className="text-muted-foreground line-clamp-1 max-w-[200px] text-xs">{txn.notes || '—'}</span>
                                            </TableCell>
                                            <TableCell className="text-xs">{txn.creator?.name || '—'}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center justify-center">
                                                    <Link href={`/customer-transactions/${txn.id}`}>
                                                        <Button variant="ghost" size="icon" title="View">
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* ═══════════ Pagination ═══════════ */}
                {transactions.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {transactions.from || 0} to {transactions.to || 0} of {transactions.total} results
                        </div>
                        <div className="flex gap-1">
                            {transactions.links.map((link, i) => (
                                <Button
                                    key={i}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => handlePageChange(link.url)}
                                    disabled={!link.url}
                                    className="min-w-[40px]"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
