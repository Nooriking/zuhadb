import { FilterField, IndexFilters } from '@/components/index-filters';
import { ViewAction, RowActions } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatRate } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, FileClock } from 'lucide-react';
import { useState } from 'react';

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
}

interface ExchangeRate {
    id: number;
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
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    currencies: Currency[];
    filters: {
        from_currency_id: string | null;
        to_currency_id: string | null;
        from_date: string | null;
        to_date: string | null;
        search: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exchange Rates', href: '/exchange-rates' },
    { title: 'History', href: '/exchange-rates/history' },
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

export default function ExchangeRatesHistory({ exchangeRates, currencies, filters }: Props) {
    const [fromFilter, setFromFilter] = useState(filters.from_currency_id || 'all');
    const [toFilter, setToFilter] = useState(filters.to_currency_id || 'all');
    const [fromDate, setFromDate] = useState(filters.from_date || '');
    const [toDate, setToDate] = useState(filters.to_date || '');
    const [search, setSearch] = useState(filters.search || '');

    const applyFilters = (overrides: Record<string, string | undefined> = {}) => {
        router.get(
            '/exchange-rates/history',
            {
                from_currency_id: fromFilter === 'all' ? undefined : fromFilter,
                to_currency_id: toFilter === 'all' ? undefined : toFilter,
                from_date: fromDate || undefined,
                to_date: toDate || undefined,
                search: search.trim() || undefined,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const clearFilters = () => {
        setFromFilter('all');
        setToFilter('all');
        setFromDate('');
        setToDate('');
        setSearch('');
        router.get('/exchange-rates/history', {}, { preserveState: true, preserveScroll: true });
    };

    const hasFilters = Boolean(
        filters.from_currency_id || filters.to_currency_id || filters.from_date || filters.to_date || filters.search,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Exchange Rate History" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <FileClock className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Exchange Rate History</h1>
                        <Badge variant="outline">{exchangeRates.total} Records</Badge>
                    </div>
                    <Link href="/exchange-rates">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back to Exchange Rates
                        </Button>
                    </Link>
                </div>

                <IndexFilters
                    search={search}
                    onSearchChange={setSearch}
                    onSearch={() => applyFilters()}
                    onClear={clearFilters}
                    showClear={hasFilters}
                    placeholder="Search by currency or source"
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
                                <SelectValue placeholder="From" />
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
                                <SelectValue placeholder="To" />
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
                    <FilterField label="From date">
                        <Input
                            type="date"
                            value={fromDate}
                            onChange={(e) => setFromDate(e.target.value)}
                            onBlur={() => applyFilters({ from_date: fromDate || undefined })}
                        />
                    </FilterField>
                    <FilterField label="To date">
                        <Input
                            type="date"
                            value={toDate}
                            onChange={(e) => setToDate(e.target.value)}
                            onBlur={() => applyFilters({ to_date: toDate || undefined })}
                        />
                    </FilterField>
                </IndexFilters>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Pair</TableHead>
                                <TableHead className="text-center">Rate</TableHead>
                                <TableHead>Effective</TableHead>
                                <TableHead>Source</TableHead>
                                <TableHead>Created By</TableHead>
                                <TableHead>Recorded</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {exchangeRates.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={8} className="text-muted-foreground h-24 text-center">
                                        No historical exchange rates match these filters.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                exchangeRates.data.map((rate, index) => (
                                    <TableRow key={rate.id}>
                                        <TableCell>{exchangeRates.from ? exchangeRates.from + index : index + 1}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2 font-mono text-sm">
                                                <Badge variant="outline">{rate.from_currency?.code || '—'}</Badge>
                                                <span className="text-muted-foreground">→</span>
                                                <Badge variant="outline">{rate.to_currency?.code || '—'}</Badge>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-center font-mono font-bold">{formatRate(rate.rate)}</TableCell>
                                        <TableCell className="text-sm">{formatDateTime(rate.effective_at)}</TableCell>
                                        <TableCell>
                                            <Badge variant="secondary" className="capitalize">
                                                {rate.source || 'manual'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-sm">{rate.creator?.name || 'System'}</TableCell>
                                        <TableCell className="text-muted-foreground text-sm">{formatDateTime(rate.created_at)}</TableCell>
                                        <TableCell>
                                            <RowActions>
                                                <ViewAction href={`/exchange-rates/${rate.id}`} />
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
                                    onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
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
