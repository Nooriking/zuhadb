// resources/js/pages/exchange-rates/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Edit, Eye, Plus, RefreshCw, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
}

interface ExchangeRate {
    id: number;
    from_currency_id: number;
    to_currency_id: number;
    rate: string;
    effective_at: string;
    from_currency: Currency | null;
    to_currency: Currency | null;
    creator: { id: number; name: string } | null;
    created_at: string;
}

interface Props {
    exchangeRates: {
        data: ExchangeRate[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    currencies: Currency[];
    filters: {
        from_currency_id: string | null;
        to_currency_id: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exchange Rates', href: '/exchange-rates' },
];

export default function ExchangeRatesIndex({ exchangeRates, currencies, filters }: Props) {
    const { toast } = useToast();
    const [fromFilter, setFromFilter] = useState(filters.from_currency_id || 'all');
    const [toFilter, setToFilter] = useState(filters.to_currency_id || 'all');
    const [rateToDelete, setRateToDelete] = useState<ExchangeRate | null>(null);

    const handleSearch = () => {
        router.get(
            '/exchange-rates',
            {
                from_currency_id: fromFilter === 'all' ? undefined : fromFilter,
                to_currency_id: toFilter === 'all' ? undefined : toFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const confirmDelete = () => {
        if (!rateToDelete) return;

        router.delete(`/exchange-rates/${rateToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Moved to Trash', description: 'Exchange rate moved to trash.' });
                setRateToDelete(null);
            },
            onError: () => {
                toast({ title: 'Error', description: 'Cannot delete.', variant: 'destructive' });
                setRateToDelete(null);
            },
        });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Exchange Rates" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <RefreshCw className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Exchange Rates</h1>
                        <Badge variant="outline">{exchangeRates.total} Rates</Badge>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/exchange-rates/trash">
                            <Button variant="outline" className="gap-2">
                                <Trash2 className="h-4 w-4" /> Trash
                            </Button>
                        </Link>
                        <Link href="/exchange-rates/create">
                            <Button className="gap-2">
                                <Plus className="h-4 w-4" /> New Rate
                            </Button>
                        </Link>
                    </div>
                </div>

                <IndexFilters>
                    <FilterField label="From currency">
                        <Select
                            value={fromFilter}
                            onValueChange={(v) => {
                                setFromFilter(v);
                                setTimeout(handleSearch, 0);
                            }}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="From Currency" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All From Currencies</SelectItem>
                                {currencies.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.code} — {c.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FilterField>
                    <FilterField label="To currency">
                        <Select
                            value={toFilter}
                            onValueChange={(v) => {
                                setToFilter(v);
                                setTimeout(handleSearch, 0);
                            }}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="To Currency" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All To Currencies</SelectItem>
                                {currencies.map((c) => (
                                    <SelectItem key={`to-${c.id}`} value={String(c.id)}>
                                        {c.code} — {c.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FilterField>
                </IndexFilters>

                {/* Table */}
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>From</TableHead>
                                <TableHead className="text-center">Rate</TableHead>
                                <TableHead>To</TableHead>
                                <TableHead>Effective Date</TableHead>
                                <TableHead>Created By</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {exchangeRates.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground h-24 text-center">
                                        No exchange rates found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                exchangeRates.data.map((rate, index) => (
                                    <TableRow key={rate.id}>
                                        <TableCell>{exchangeRates.from ? exchangeRates.from + index : index + 1}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline" className="font-mono">
                                                    {rate.from_currency?.code || '—'}
                                                </Badge>
                                                <span className="text-sm">{rate.from_currency?.name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <div className="flex items-center justify-center gap-2">
                                                <span className="text-muted-foreground text-xs">1 =</span>
                                                <span className="font-mono text-base font-bold">{rate.rate}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline" className="font-mono">
                                                    {rate.to_currency?.code || '—'}
                                                </Badge>
                                                <span className="text-sm">{rate.to_currency?.name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">{formatDate(rate.effective_at)}</TableCell>
                                        <TableCell className="text-sm">{rate.creator?.name || 'System'}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-1">
                                                <Link href={`/exchange-rates/${rate.id}`}>
                                                    <Button variant="ghost" size="icon" title="View">
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                                <Link href={`/exchange-rates/${rate.id}/edit`}>
                                                    <Button variant="ghost" size="icon" title="Edit">
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Delete"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => setRateToDelete(rate)}
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

                {/* Pagination */}
                {exchangeRates.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {exchangeRates.from || 0} to {exchangeRates.to || 0} of {exchangeRates.total} results
                        </div>
                        <div className="flex gap-1">
                            {exchangeRates.links.map((link, i) => (
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

            {/* Delete Modal */}
            {rateToDelete && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setRateToDelete(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <AlertTriangle className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Are you sure?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Exchange rate{' '}
                                    <strong>
                                        {rateToDelete.from_currency?.code} → {rateToDelete.to_currency?.code}
                                    </strong>{' '}
                                    will be moved to trash.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setRateToDelete(null)}
                                className="text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setRateToDelete(null)}>
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
