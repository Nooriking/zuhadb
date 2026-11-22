import { FilterField, IndexFilters } from '@/components/index-filters';
import { EditAction, RowAction, RowActions, ViewAction } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { formatRate } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, FileClock, Plus, RefreshCw, Trash2, X } from 'lucide-react';
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
    source: string | null;
    from_currency: Currency | null;
    to_currency: Currency | null;
    creator: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
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
    currentRates: ExchangeRate[];
    filters: {
        from_currency_id: string | null;
        to_currency_id: string | null;
        search: string | null;
        sort: string;
        direction: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exchange Rates', href: '/exchange-rates' },
];

const formatDateTime = (date: string | null | undefined) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

export default function ExchangeRatesIndex({ exchangeRates, currencies, currentRates, filters }: Props) {
    const { toast } = useToast();
    const [fromFilter, setFromFilter] = useState(filters.from_currency_id || 'all');
    const [toFilter, setToFilter] = useState(filters.to_currency_id || 'all');
    const [search, setSearch] = useState(filters.search || '');
    const [rateToDelete, setRateToDelete] = useState<ExchangeRate | null>(null);

    const applyFilters = (overrides: Record<string, string | undefined> = {}) => {
        router.get(
            '/exchange-rates',
            {
                from_currency_id: fromFilter === 'all' ? undefined : fromFilter,
                to_currency_id: toFilter === 'all' ? undefined : toFilter,
                search: search.trim() || undefined,
                sort: filters.sort,
                direction: filters.direction,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const clearFilters = () => {
        setFromFilter('all');
        setToFilter('all');
        setSearch('');
        router.get('/exchange-rates', {}, { preserveState: true, preserveScroll: true });
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

    const hasFilters = Boolean(filters.from_currency_id || filters.to_currency_id || filters.search);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Exchange Rates" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <RefreshCw className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Exchange Rates</h1>
                        <Badge variant="outline">{exchangeRates.total} Rates</Badge>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href="/exchange-rates/history">
                            <Button variant="outline" className="gap-2">
                                <FileClock className="h-4 w-4" /> History
                            </Button>
                        </Link>
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

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Current active rates</CardTitle>
                        <CardDescription>Latest effective rate for each currency pair.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {currentRates.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No active exchange rates yet.</p>
                        ) : (
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {currentRates.map((rate) => (
                                    <div key={rate.id} className="rounded-md border p-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="flex items-center gap-2 font-mono text-sm font-semibold">
                                                <span>{rate.from_currency?.code || '—'}</span>
                                                <span className="text-muted-foreground">→</span>
                                                <span>{rate.to_currency?.code || '—'}</span>
                                            </div>
                                            <Badge variant="outline" className="capitalize">
                                                {rate.source || 'manual'}
                                            </Badge>
                                        </div>
                                        <p className="mt-2 font-mono text-xl font-bold">{formatRate(rate.rate)}</p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Effective {formatDateTime(rate.effective_at)}
                                        </p>
                                        <p className="text-muted-foreground text-xs">Updated {formatDateTime(rate.updated_at)}</p>
                                        <div className="mt-2">
                                            <Link href={`/exchange-rates/${rate.id}`} className="text-primary text-xs underline-offset-2 hover:underline">
                                                View details
                                            </Link>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <IndexFilters
                    search={search}
                    onSearchChange={setSearch}
                    onSearch={() => applyFilters()}
                    onClear={clearFilters}
                    showClear={hasFilters}
                    placeholder="Search by currency code, name, or source"
                >
                    <FilterField label="From currency">
                        <Select
                            value={fromFilter}
                            onValueChange={(v) => {
                                setFromFilter(v);
                                applyFilters({ from_currency_id: v === 'all' ? undefined : v });
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
                                applyFilters({ to_currency_id: v === 'all' ? undefined : v });
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

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>From</TableHead>
                                <TableHead className="text-center">Rate</TableHead>
                                <TableHead>To</TableHead>
                                <TableHead>Effective</TableHead>
                                <TableHead>Source</TableHead>
                                <TableHead>Created By</TableHead>
                                <TableHead>Updated</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {exchangeRates.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={9} className="text-muted-foreground h-24 text-center">
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
                                                <span className="font-mono text-base font-bold">{formatRate(rate.rate)}</span>
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
                                        <TableCell className="text-sm">{formatDateTime(rate.effective_at)}</TableCell>
                                        <TableCell>
                                            <Badge variant="secondary" className="capitalize">
                                                {rate.source || 'manual'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-sm">{rate.creator?.name || 'System'}</TableCell>
                                        <TableCell className="text-muted-foreground text-sm">{formatDateTime(rate.updated_at)}</TableCell>
                                        <TableCell>
                                            <RowActions>
                                                <ViewAction href={`/exchange-rates/${rate.id}`} />
                                                <EditAction href={`/exchange-rates/${rate.id}/edit`} />
                                                <RowAction title="Delete" destructive onClick={() => setRateToDelete(rate)}>
                                                    <Trash2 className="h-4 w-4" />
                                                </RowAction>
                                            </RowActions>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {exchangeRates.last_page > 1 && (
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {exchangeRates.from || 0} to {exchangeRates.to || 0} of {exchangeRates.total} results
                        </div>
                        <div className="flex flex-wrap gap-1">
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
                                aria-label="Close"
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
