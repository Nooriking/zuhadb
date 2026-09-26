// resources/js/pages/exchange-rate-history/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Calendar, DollarSign, Edit, Plus, Save, Search, Trash2, TrendingDown, TrendingUp, X } from 'lucide-react';
import { useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Rate {
    id: number;
    rate_date: string;
    usd_to_afn: string;
    notes: string | null;
    created_at: string;
    creator: { id: number; name: string } | null;
}

interface Stats {
    latest_rate: number;
    latest_date: string | null;
    avg_this_month: number;
    min_this_month: number;
    max_this_month: number;
    total_records: number;
}

interface Props {
    rates: {
        data: Rate[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
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
    { title: 'Exchange Rate History', href: '/exchange-rate-history' },
];

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const formatPrice = (value: string | number, digits: number = 4) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(num || 0);
};

const formatDate = (value: string) => {
    return new Date(value).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    });
};

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function ExchangeRateHistoryIndex({ rates, filters, stats }: Props) {
    const { toast } = useToast();

    const [fromDate, setFromDate] = useState(filters.from_date || '');
    const [toDate, setToDate] = useState(filters.to_date || '');
    const [quickRate, setQuickRate] = useState('');
    const [quickLoading, setQuickLoading] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<Rate | null>(null);

    const handleSearch = () => {
        router.get(
            '/exchange-rate-history',
            {
                from_date: fromDate || undefined,
                to_date: toDate || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const clearFilters = () => {
        setFromDate('');
        setToDate('');
        router.get('/exchange-rate-history', {}, { preserveState: true, preserveScroll: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const handleQuickStore = (e: React.FormEvent) => {
        e.preventDefault();
        const rate = parseFloat(quickRate || '0');

        if (rate <= 0) {
            toast({ title: 'Error', description: 'The rate must be greater than zero.', variant: 'destructive' });
            return;
        }

        setQuickLoading(true);

        router.post(
            '/exchange-rate-history/quick',
            { usd_to_afn: rate },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast({ title: 'Success', description: 'Today rate saved.' });
                    setQuickRate('');
                    setQuickLoading(false);
                },
                onError: () => {
                    toast({ title: 'Error', description: 'Could not save the rate.', variant: 'destructive' });
                    setQuickLoading(false);
                },
            },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(`/exchange-rate-history/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Deleted', description: 'Rate deleted.' });
                setDeleteTarget(null);
            },
            onError: () => {
                toast({ title: 'Error', description: 'Could not delete the rate.', variant: 'destructive' });
                setDeleteTarget(null);
            },
        });
    };

    const hasFilters = !!fromDate || !!toDate;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Exchange Rate History" />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <TrendingUp className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Exchange Rate History</h1>
                        <Badge variant="outline">{rates.total} Records</Badge>
                    </div>
                    <Link href="/exchange-rate-history/create">
                        <Button className="gap-2">
                            <Plus className="h-4 w-4" /> Add Rate
                        </Button>
                    </Link>
                </div>

                {/* ═══════════ Quick Rate Input ═══════════ */}
                <Card className="border-2 border-blue-300 bg-blue-50">
                    <CardContent className="p-4">
                        <form onSubmit={handleQuickStore} className="flex flex-wrap items-end gap-3">
                            <div className="flex-1 space-y-1">
                                <label className="text-xs font-semibold">Quick save today rate (USD to AFN)</label>
                                <Input
                                    type="number"
                                    step="0.0001"
                                    min="0.0001"
                                    value={quickRate}
                                    onChange={(e) => setQuickRate(e.target.value)}
                                    placeholder="e.g. 69.5000"
                                    className="font-mono text-lg"
                                />
                            </div>
                            <Button type="submit" disabled={quickLoading || !quickRate} className="gap-2 bg-blue-600 hover:bg-blue-700">
                                <Save className="h-4 w-4" />
                                {quickLoading ? 'Saving...' : 'Save Today'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {/* ═══════════ Stats ═══════════ */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <Card className="border-2 border-emerald-300 bg-emerald-50">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-xs text-emerald-800">Latest Rate</p>
                                <DollarSign className="h-4 w-4 text-emerald-600" />
                            </div>
                            <p className="mt-1 font-mono text-2xl font-bold text-emerald-900">{formatPrice(stats.latest_rate)}</p>
                            {stats.latest_date && <p className="text-muted-foreground mt-0.5 text-[10px]">{formatDate(stats.latest_date)}</p>}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Avg This Month</p>
                                <TrendingUp className="h-4 w-4 text-blue-500" />
                            </div>
                            <p className="mt-1 font-mono text-xl font-bold">{formatPrice(stats.avg_this_month)}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Min This Month</p>
                                <TrendingDown className="h-4 w-4 text-green-500" />
                            </div>
                            <p className="mt-1 font-mono text-xl font-bold text-green-600">{formatPrice(stats.min_this_month)}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Max This Month</p>
                                <TrendingUp className="h-4 w-4 text-red-500" />
                            </div>
                            <p className="mt-1 font-mono text-xl font-bold text-red-600">{formatPrice(stats.max_this_month)}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Records</p>
                                <Calendar className="h-4 w-4 text-indigo-500" />
                            </div>
                            <p className="mt-1 text-xl font-bold">{stats.total_records}</p>
                        </CardContent>
                    </Card>
                </div>

                <IndexFilters onSearch={handleSearch} onClear={clearFilters} showClear={hasFilters}>
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
                                <TableHead className="text-right">USD → AFN</TableHead>
                                <TableHead>Notes</TableHead>
                                <TableHead>Recorded By</TableHead>
                                <TableHead>Recorded At</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rates.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground h-24 text-center">
                                        No records found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                rates.data.map((rate, index) => (
                                    <TableRow key={rate.id}>
                                        <TableCell>{rates.from ? rates.from + index : index + 1}</TableCell>
                                        <TableCell className="font-medium">{formatDate(rate.rate_date)}</TableCell>
                                        <TableCell className="text-right font-mono text-lg font-bold text-emerald-700">
                                            {formatPrice(rate.usd_to_afn, 4)}
                                        </TableCell>
                                        <TableCell>
                                            <span className="text-muted-foreground line-clamp-1 max-w-[200px] text-xs">{rate.notes || '—'}</span>
                                        </TableCell>
                                        <TableCell className="text-xs">{rate.creator?.name || '—'}</TableCell>
                                        <TableCell className="text-xs">{new Date(rate.created_at).toLocaleDateString('en-US')}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-1">
                                                <Link href={`/exchange-rate-history/${rate.id}/edit`}>
                                                    <Button variant="ghost" size="icon" title="Edit">
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Delete"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => setDeleteTarget(rate)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* ═══════════ Pagination ═══════════ */}
                {rates.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {rates.from || 0} to {rates.to || 0} of {rates.total} results
                        </div>
                        <div className="flex gap-1">
                            {rates.links.map((link, i) => (
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
                        <div className="p-6">
                            <h3 className="text-lg font-semibold">Delete Rate?</h3>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Rate for <strong>{formatDate(deleteTarget.rate_date)}</strong> will be deleted.
                            </p>
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
