// resources/js/pages/sale-returns/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Calendar, DollarSign, Eye, Plus, RotateCcw, Trash2, TrendingDown } from 'lucide-react';
import { useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface SaleReturn {
    id: number;
    return_number: string;
    return_date: string;
    total_afn: string;
    previous_due_afn: string;
    new_due_afn: string;
    refund_amount_afn: string;
    status: 'draft' | 'completed' | 'cancelled';
    items_count: number;
    reason: string | null;
    sale: { id: number; invoice_number: string } | null;
    branch: { id: number; name: string; code: string } | null;
    customer: { id: number; name: string; phone: string | null } | null;
    creator: { id: number; name: string } | null;
}

interface Stats {
    total_returns: number;
    today_returns: number;
    total_amount: number;
    today_amount: number;
}

interface Props {
    saleReturns: {
        data: SaleReturn[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    branches: { id: number; name: string; code: string }[];
    customers: { id: number; name: string; phone: string | null }[];
    filters: {
        search: string | null;
        branch_id: string | null;
        status: string | null;
        customer_id: string | null;
    };
    stats: Stats;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sale Returns', href: '/sale-returns' },
];

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const formatPrice = (value: string | number, digits: number = 0) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(num || 0);
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

export default function SaleReturnsIndex({ saleReturns, branches, filters, stats }: Props) {
    const { toast } = useToast();
    const [search, setSearch] = useState(filters.search || '');
    const [branchFilter, setBranchFilter] = useState(filters.branch_id || 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [deleteTarget, setDeleteTarget] = useState<SaleReturn | null>(null);

    const handleSearch = () => {
        router.get(
            '/sale-returns',
            {
                search: search || undefined,
                branch_id: branchFilter === 'all' ? undefined : branchFilter,
                status: statusFilter === 'all' ? undefined : statusFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleMarkCompleted = (sr: SaleReturn) => {
        if (!confirm(`Mark "${sr.return_number}" as completed? Good items return to stock and the customer due is updated.`)) return;

        router.patch(
            `/sale-returns/${sr.id}/mark-as-completed`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Success', description: 'Return completed.' }),
                onError: (err) => toast({ title: 'Error', description: Object.values(err).join(' '), variant: 'destructive' }),
            },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(`/sale-returns/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Moved to Trash', description: `${deleteTarget.return_number} moved.` });
                setDeleteTarget(null);
            },
            onError: () => {
                toast({ title: 'Error', description: 'Cannot delete.', variant: 'destructive' });
                setDeleteTarget(null);
            },
        });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sale Returns" />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <RotateCcw className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Sale Returns</h1>
                        <Badge variant="outline">{saleReturns.total} Returns</Badge>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/sale-returns/trash">
                            <Button variant="outline" className="gap-2">
                                <Trash2 className="h-4 w-4" /> Trash
                            </Button>
                        </Link>
                        <Link href="/sale-returns/create">
                            <Button className="gap-2">
                                <Plus className="h-4 w-4" /> New Return
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* ═══════════ Stats ═══════════ */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Returns</p>
                                <RotateCcw className="h-4 w-4 text-blue-500" />
                            </div>
                            <p className="mt-1 text-2xl font-bold">{stats.total_returns}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Today</p>
                                <Calendar className="h-4 w-4 text-amber-500" />
                            </div>
                            <p className="mt-1 text-2xl font-bold text-amber-600">{stats.today_returns}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Amount</p>
                                <DollarSign className="h-4 w-4 text-green-500" />
                            </div>
                            <p className="mt-1 text-xl font-bold text-green-600">{formatPrice(stats.total_amount)} AFN</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Today Amount</p>
                                <TrendingDown className="h-4 w-4 text-red-500" />
                            </div>
                            <p className="mt-1 text-xl font-bold text-red-600">{formatPrice(stats.today_amount)} AFN</p>
                        </CardContent>
                    </Card>
                </div>

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search return number, invoice, customer...">
                    {branches.length > 1 && (
                        <FilterField label="Branch">
                            <Select
                                value={branchFilter}
                                onValueChange={(v) => {
                                    setBranchFilter(v);
                                    setTimeout(handleSearch, 0);
                                }}
                            >
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
                    <FilterField label="Status">
                        <Select
                            value={statusFilter}
                            onValueChange={(v) => {
                                setStatusFilter(v);
                                setTimeout(handleSearch, 0);
                            }}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>
                </IndexFilters>

                {/* ═══════════ Table ═══════════ */}
                <div className="overflow-x-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Return #</TableHead>
                                <TableHead>Original Sale</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead className="text-right">Total AFN</TableHead>
                                <TableHead className="text-right">Prev Due</TableHead>
                                <TableHead className="text-right">New Due</TableHead>
                                <TableHead className="text-right">Refund</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {saleReturns.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={11} className="text-muted-foreground h-24 text-center">
                                        No sale returns found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                saleReturns.data.map((sr, index) => {
                                    const prevDue = parseFloat(sr.previous_due_afn || '0');
                                    const newDue = parseFloat(sr.new_due_afn || '0');
                                    const refund = parseFloat(sr.refund_amount_afn || '0');

                                    return (
                                        <TableRow key={sr.id}>
                                            <TableCell>{saleReturns.from ? saleReturns.from + index : index + 1}</TableCell>
                                            <TableCell>
                                                <span className="font-mono text-xs font-semibold">{sr.return_number}</span>
                                            </TableCell>
                                            <TableCell>
                                                {sr.sale ? (
                                                    <Link href={`/sales/${sr.sale.id}`} className="hover:text-primary font-mono text-xs">
                                                        {sr.sale.invoice_number}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {sr.customer ? (
                                                    <Link href={`/customers/${sr.customer.id}`} className="hover:text-primary text-sm font-medium">
                                                        {sr.customer.name}
                                                    </Link>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">Walk-in</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-xs">{new Date(sr.return_date).toLocaleDateString('en-US')}</TableCell>
                                            <TableCell className="text-right font-mono text-sm font-semibold text-green-600">
                                                {formatPrice(sr.total_afn)} AFN
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-xs text-amber-600">
                                                {prevDue > 0 ? formatPrice(prevDue) : '—'}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-xs">
                                                <span className={newDue > 0 ? 'text-red-600' : 'text-green-600'}>{formatPrice(newDue)}</span>
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-xs">
                                                {refund > 0 ? (
                                                    <Badge variant="destructive" className="text-[10px]">
                                                        {formatPrice(refund)} AFN
                                                    </Badge>
                                                ) : (
                                                    '—'
                                                )}
                                            </TableCell>
                                            <TableCell>{getStatusBadge(sr.status)}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center justify-center gap-1">
                                                    {sr.status === 'draft' && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleMarkCompleted(sr)}
                                                            className="text-xs text-green-600 hover:text-green-700"
                                                        >
                                                            ✓ Complete
                                                        </Button>
                                                    )}
                                                    <Link href={`/sale-returns/${sr.id}`}>
                                                        <Button variant="ghost" size="icon" title="View">
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                    {sr.status !== 'completed' && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-destructive"
                                                            onClick={() => setDeleteTarget(sr)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
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
                {saleReturns.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {saleReturns.from || 0} to {saleReturns.to || 0} of {saleReturns.total} results
                        </div>
                        <div className="flex gap-1">
                            {saleReturns.links.map((link, i) => (
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
                                <h3 className="text-lg font-semibold">Move to Trash?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Return <strong>{deleteTarget.return_number}</strong> will be moved to trash.
                                </p>
                            </div>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setDeleteTarget(null)}>
                                Cancel
                            </Button>
                            <Button onClick={confirmDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                                Move to Trash
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
