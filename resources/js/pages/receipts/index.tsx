// resources/js/pages/receipts/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Calendar, Eye, FileText, Printer, Receipt as ReceiptIcon, Trash2, TrendingUp } from 'lucide-react';
import { useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Receipt {
    id: number;
    receipt_number: string;
    receipt_type: 'sale_invoice' | 'payment_receipt';
    amount_afn: string;
    remaining_due_afn: string;
    created_at: string;
    customer: { id: number; name: string; phone: string | null } | null;
    sale: { id: number; invoice_number: string } | null;
    sale_payment: { id: number; amount_afn: string } | null;
    creator: { id: number; name: string } | null;
}

interface Stats {
    total_receipts: number;
    total_invoices: number;
    total_payments: number;
    today_receipts: number;
}

interface Props {
    receipts: {
        data: Receipt[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    customers: { id: number; name: string; phone: string | null }[];
    filters: {
        search: string | null;
        receipt_type: string | null;
        customer_id: string | null;
        from_date: string | null;
        to_date: string | null;
    };
    stats: Stats;
}

/* ═══════════════════════════════════════════════════════
 |  Constants
 ═══════════════════════════════════════════════════════ */

function pageTitle(type: string | null) {
    if (type === 'sale_invoice') return 'Invoices';
    if (type === 'payment_receipt') return 'Payment Receipts';
    return 'Bills';
}

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const formatPrice = (value: string | number, digits: number = 2) => {
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

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function ReceiptsIndex({ receipts, customers, filters, stats }: Props) {
    const { toast } = useToast();
    const title = pageTitle(filters.receipt_type);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title, href: filters.receipt_type ? `/receipts?receipt_type=${filters.receipt_type}` : '/receipts' },
    ];

    const [search, setSearch] = useState(filters.search || '');
    const [typeFilter, setTypeFilter] = useState(filters.receipt_type || 'all');
    const [customerFilter, setCustomerFilter] = useState(filters.customer_id || 'all');
    const [fromDate, setFromDate] = useState(filters.from_date || '');
    const [toDate, setToDate] = useState(filters.to_date || '');
    const [deleteTarget, setDeleteTarget] = useState<Receipt | null>(null);

    const handleSearch = () => {
        router.get(
            '/receipts',
            {
                search: search || undefined,
                receipt_type: typeFilter === 'all' ? undefined : typeFilter,
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
        router.get('/receipts', {}, { preserveState: true, preserveScroll: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const handlePrint = (receipt: Receipt) => {
        window.open(`/receipts/${receipt.id}/print`, '_blank', 'noopener,noreferrer');
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(`/receipts/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Deleted', description: `${deleteTarget.receipt_number} was deleted.` });
                setDeleteTarget(null);
            },
            onError: () => {
                toast({ title: 'Error', description: 'Cannot delete.', variant: 'destructive' });
                setDeleteTarget(null);
            },
        });
    };

    const hasFilters = !!search || typeFilter !== 'all' || customerFilter !== 'all' || !!fromDate || !!toDate;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <ReceiptIcon className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                        <Badge variant="outline">{receipts.total}</Badge>
                    </div>
                </div>

                {/* ═══════════ Stats ═══════════ */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Receipts</p>
                                <FileText className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold">{stats.total_receipts}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Sale Invoices</p>
                                <ReceiptIcon className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold">{stats.total_invoices}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Payment Receipts</p>
                                <TrendingUp className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold">{stats.total_payments}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Today</p>
                                <Calendar className="text-muted-foreground h-4 w-4" />
                            </div>
                            <p className="mt-1 text-2xl font-bold">{stats.today_receipts}</p>
                        </CardContent>
                    </Card>
                </div>

                <IndexFilters
                    search={search}
                    onSearchChange={setSearch}
                    onSearch={handleSearch}
                    onClear={clearFilters}
                    showClear={hasFilters}
                    placeholder="Search receipt number, customer..."
                >
                    <FilterField label="Type">
                        <Select value={typeFilter} onValueChange={setTypeFilter}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Types</SelectItem>
                                <SelectItem value="sale_invoice">Sale Invoice</SelectItem>
                                <SelectItem value="payment_receipt">Payment Receipt</SelectItem>
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
                                <TableHead>Receipt #</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Sale</TableHead>
                                <TableHead className="text-right">Amount (AFN)</TableHead>
                                <TableHead className="text-right">Remaining</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>By</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {receipts.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={10} className="text-muted-foreground h-24 text-center">
                                        No {title.toLowerCase()} found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                receipts.data.map((r, index) => {
                                    const remaining = parseFloat(r.remaining_due_afn || '0');
                                    const isInvoice = r.receipt_type === 'sale_invoice';

                                    return (
                                        <TableRow key={r.id}>
                                            <TableCell>{receipts.from ? receipts.from + index : index + 1}</TableCell>
                                            <TableCell>
                                                <div className="font-mono text-xs font-semibold">{r.receipt_number}</div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={isInvoice ? 'default' : 'success'}>{isInvoice ? 'Invoice' : 'Payment'}</Badge>
                                            </TableCell>
                                            <TableCell>
                                                {r.customer ? (
                                                    <Link href={`/customers/${r.customer.id}`} className="hover:text-primary text-sm font-medium">
                                                        {r.customer.name}
                                                        {r.customer.phone && (
                                                            <div className="text-muted-foreground text-xs" dir="ltr">
                                                                {r.customer.phone}
                                                            </div>
                                                        )}
                                                    </Link>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">Walk-in</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {r.sale ? (
                                                    <Link href={`/sales/${r.sale.id}`} className="hover:text-primary font-mono text-xs">
                                                        {r.sale.invoice_number}
                                                    </Link>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-sm font-semibold">
                                                {formatPrice(r.amount_afn)} AFN
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <span
                                                    className="font-mono text-xs font-medium"
                                                >
                                                    {formatPrice(remaining)}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-xs">{formatDateTime(r.created_at)}</TableCell>
                                            <TableCell className="text-xs">{r.creator?.name || '—'}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center justify-center gap-1">
                                                    <Link href={`/receipts/${r.id}`}>
                                                        <Button variant="ghost" size="icon" title="View">
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Print"
                                                        onClick={() => handlePrint(r)}
                                                    >
                                                        <Printer className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Delete"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => setDeleteTarget(r)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
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
                {receipts.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {receipts.from || 0} to {receipts.to || 0} of {receipts.total} results
                        </div>
                        <div className="flex gap-1">
                            {receipts.links.map((link, i) => (
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

            {/* ═══════════ Delete Modal ═══════════ */}
            {deleteTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setDeleteTarget(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <AlertTriangle className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Delete Receipt?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Receipt <strong>{deleteTarget.receipt_number}</strong> will be permanently deleted.
                                </p>
                            </div>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setDeleteTarget(null)}>
                                Cancel
                            </Button>
                            <Button onClick={confirmDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                                Delete
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
