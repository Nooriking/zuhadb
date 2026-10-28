// resources/js/pages/investor-withdrawals/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Banknote, CheckCircle2, Clock, DollarSign, Eye, Plus, Trash2, TrendingDown, TrendingUp, X } from 'lucide-react';
import { useState } from 'react';

interface Investor {
    id: number;
    full_name: string;
    phone: string | null;
}

interface Currency {
    id: number;
    code: string;
    symbol: string | null;
}

interface User {
    id: number;
    name: string;
}

interface Withdrawal {
    id: number;
    withdrawal_number: string;
    type: 'profit' | 'capital';
    exchange_rate: string;
    amount_currency: string;
    amount_afn: string;
    balance_before_afn: string;
    balance_after_afn: string;
    withdrawal_date: string;
    status: 'pending' | 'approved' | 'paid' | 'rejected';
    notes: string | null;
    investor: Investor | null;
    currency: Currency | null;
    approved_by: User | null;
    paid_by: User | null;
    creator: User | null;
}

interface Props {
    withdrawals: {
        data: Withdrawal[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    investors: Investor[];
    filters: {
        search: string | null;
        investor_id: string | null;
        status: string | null;
        type: string | null;
    };
    stats: {
        total_pending: number;
        total_approved: number;
        total_paid: number;
        total_amount_paid_afn: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investor Withdrawals', href: '/investor-withdrawals' },
];

const formatPrice = (value: string | number, digits: number = 0) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(num);
};

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getStatusBadge = (status: string) => {
    const map: Record<string, { variant: 'outline' | 'secondary' | 'success' | 'destructive'; label: string }> = {
        pending: { variant: 'outline', label: 'Pending' },
        approved: { variant: 'secondary', label: 'Approved' },
        paid: { variant: 'success', label: 'Paid' },
        rejected: { variant: 'destructive', label: 'Rejected' },
    };
    const item = map[status] || map.pending;
    return <Badge variant={item.variant}>{item.label}</Badge>;
};

const getTypeBadge = (type: string) => {
    if (type === 'profit') {
        return (
            <Badge variant="success" className="gap-1">
                <TrendingUp className="h-3 w-3" /> Profit
            </Badge>
        );
    }
    return (
        <Badge variant="secondary" className="gap-1">
            <TrendingDown className="h-3 w-3" /> Capital
        </Badge>
    );
};

export default function InvestorWithdrawalsIndex({ withdrawals, investors, filters, stats }: Props) {
    const { toast } = useToast();
    const [search, setSearch] = useState(filters.search || '');
    const [investorFilter, setInvestorFilter] = useState(filters.investor_id || 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [typeFilter, setTypeFilter] = useState(filters.type || 'all');
    const [withdrawalToDelete, setWithdrawalToDelete] = useState<Withdrawal | null>(null);

    const handleSearch = () => {
        router.get(
            '/investor-withdrawals',
            {
                search: search || undefined,
                investor_id: investorFilter === 'all' ? undefined : investorFilter,
                status: statusFilter === 'all' ? undefined : statusFilter,
                type: typeFilter === 'all' ? undefined : typeFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const confirmDelete = () => {
        if (!withdrawalToDelete) return;

        router.delete(`/investor-withdrawals/${withdrawalToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Deleted', description: `${withdrawalToDelete.withdrawal_number} deleted.` });
                setWithdrawalToDelete(null);
            },
            onError: (errors: any) => {
                toast({
                    title: 'Cannot Delete',
                    description: errors?.message || 'Paid withdrawals cannot be deleted.',
                    variant: 'destructive',
                });
                setWithdrawalToDelete(null);
            },
        });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Investor Withdrawals" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Banknote className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Investor Withdrawals</h1>
                        <Badge variant="outline">{withdrawals.total} Withdrawals</Badge>
                    </div>
                    <Link href="/investor-withdrawals/create">
                        <Button className="gap-2">
                            <Plus className="h-4 w-4" /> New Withdrawal
                        </Button>
                    </Link>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Pending</p>
                            <Clock className="h-4 w-4 text-yellow-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-yellow-600">{stats.total_pending}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Approved</p>
                            <CheckCircle2 className="h-4 w-4 text-blue-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-blue-600">{stats.total_approved}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Paid</p>
                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-green-600">{stats.total_paid}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Total Paid Amount</p>
                            <DollarSign className="h-4 w-4 text-purple-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-purple-600">{formatPrice(stats.total_amount_paid_afn)} AFN</p>
                    </div>
                </div>

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search by withdrawal number...">
                    <FilterField label="Investor">
                    <Select
                        value={investorFilter}
                        onValueChange={(v) => {
                            setInvestorFilter(v);
                            setTimeout(handleSearch, 0);
                        }}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Investor" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Investors</SelectItem>
                            {investors.map((inv) => (
                                <SelectItem key={inv.id} value={String(inv.id)}>
                                    {inv.full_name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    </FilterField>
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
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="approved">Approved</SelectItem>
                            <SelectItem value="paid">Paid</SelectItem>
                            <SelectItem value="rejected">Rejected</SelectItem>
                        </SelectContent>
                    </Select>
                    </FilterField>
                    <FilterField label="Type">
                    <Select
                        value={typeFilter}
                        onValueChange={(v) => {
                            setTypeFilter(v);
                            setTimeout(handleSearch, 0);
                        }}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            <SelectItem value="profit">Profit</SelectItem>
                            <SelectItem value="capital">Capital</SelectItem>
                        </SelectContent>
                    </Select>
                    </FilterField>
                </IndexFilters>

                {/* Table */}
                <div className="overflow-x-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Number</TableHead>
                                <TableHead>Investor</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead className="text-right">Amount</TableHead>
                                <TableHead className="text-right">Ledger (AFN)</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Approved By</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {withdrawals.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={10} className="text-muted-foreground h-24 text-center">
                                        No withdrawals found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                withdrawals.data.map((w, index) => (
                                    <TableRow key={w.id}>
                                        <TableCell>{withdrawals.from ? withdrawals.from + index : index + 1}</TableCell>
                                        <TableCell className="font-mono text-xs font-semibold">{w.withdrawal_number}</TableCell>
                                        <TableCell>
                                            <div className="font-medium">{w.investor?.full_name || '—'}</div>
                                            {w.investor?.phone && (
                                                <div className="text-muted-foreground text-xs" dir="ltr">
                                                    {w.investor.phone}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-xs">{formatDate(w.withdrawal_date)}</TableCell>
                                        <TableCell>{getTypeBadge(w.type)}</TableCell>
                                        <TableCell className="text-right font-mono font-semibold">
                                            {formatPrice(w.amount_currency ?? w.amount_afn, 2)} {w.currency?.code || 'AFN'}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-xs text-gray-600">{formatPrice(w.amount_afn)} AFN</TableCell>
                                        <TableCell>{getStatusBadge(w.status)}</TableCell>
                                        <TableCell className="text-xs">{w.approved_by?.name || '—'}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-1">
                                                <Link href={`/investor-withdrawals/${w.id}`}>
                                                    <Button variant="ghost" size="icon" title="View">
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                                {w.status !== 'paid' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Delete"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => setWithdrawalToDelete(w)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination */}
                {withdrawals.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {withdrawals.from || 0} to {withdrawals.to || 0} of {withdrawals.total} results
                        </div>
                        <div className="flex gap-1">
                            {withdrawals.links.map((link, i) => (
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

            {/* Delete Confirmation Modal */}
            {withdrawalToDelete && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setWithdrawalToDelete(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <Trash2 className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Delete withdrawal?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Withdrawal <strong>{withdrawalToDelete.withdrawal_number}</strong> will be permanently deleted. Only pending or
                                    approved withdrawals can be deleted.
                                </p>
                            </div>
                            <button type="button" onClick={() => setWithdrawalToDelete(null)} className="text-muted-foreground hover:text-foreground">
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setWithdrawalToDelete(null)}>
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
