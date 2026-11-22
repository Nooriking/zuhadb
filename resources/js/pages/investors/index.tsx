// resources/js/pages/investors/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import type { CurrencySectionMetric } from '@/components/investors/currency-section-cards';
import { DeleteAction, EditAction, RowAction, RowActions, ViewAction } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { formatOriginalAmount } from '@/lib/original-currency';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ToggleLeft, X } from 'lucide-react';
import { useState } from 'react';

type CurrencyCode = 'AFN' | 'USD' | 'AED';

const CURRENCIES: CurrencyCode[] = ['AFN', 'USD', 'AED'];

interface Wallet {
    id: number;
    capital_balance_afn: string;
    profit_balance_afn: string;
    capital_balance_currency?: string | number | null;
    profit_balance_currency?: string | number | null;
    currency?: { id: number; code: string } | null;
}

interface Investor {
    id: number;
    name?: string;
    full_name: string;
    phone: string | null;
    email: string | null;
    status: 'active' | 'inactive' | 'blocked';
    wallet: Wallet | null;
}

interface Props {
    investors: {
        data: Investor[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        search: string | null;
        status: string | null;
    };
    currencySections?: Record<string, CurrencySectionMetric> | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investors', href: '/investors' },
];

/** Currency an investor belongs to (their wallet currency); AFN when unknown. */
function investorCurrency(investor: Investor): CurrencyCode {
    const code = investor.wallet?.currency?.code?.toUpperCase();
    return code === 'USD' || code === 'AED' ? code : 'AFN';
}

export default function InvestorsIndex({ investors, filters, currencySections = null }: Props) {
    const { toast } = useToast();
    const [activeCurrency, setActiveCurrency] = useState<CurrencyCode>('AFN');
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [investorToDelete, setInvestorToDelete] = useState<Investor | null>(null);

    const visibleInvestors = investors.data.filter((inv) => investorCurrency(inv) === activeCurrency);

    const handleSearch = () => {
        router.get(
            '/investors',
            {
                search: search || undefined,
                status: statusFilter === 'all' ? undefined : statusFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleToggleStatus = (investor: Investor) => {
        router.post(
            `/investors/${investor.id}/toggle-status`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Status changed', description: `${investor.full_name} toggled.` }),
                onError: () => toast({ title: 'Error', description: 'Failed to toggle status.', variant: 'destructive' }),
            },
        );
    };

    const confirmDelete = () => {
        if (!investorToDelete) return;

        router.delete(`/investors/${investorToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Moved to Trash', description: `${investorToDelete.full_name} moved to trash.` });
                setInvestorToDelete(null);
            },
            onError: (errors: any) => {
                toast({
                    title: 'Cannot Delete',
                    description: errors?.message || 'Investor has active capital or investments.',
                    variant: 'destructive',
                });
                setInvestorToDelete(null);
            },
        });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    /** Server value formatted in its own currency; "—" when the server did not send it. */
    const amount = (value: string | number | null | undefined, code: CurrencyCode) =>
        value === null || value === undefined ? '—' : formatOriginalAmount(value, code, 2);

    const summaryRows: { label: string; key: keyof CurrencySectionMetric }[] = [
        { label: 'Cash Capital', key: 'cash_capital' },
        { label: 'Investor Profit', key: 'investor_profit' },
        { label: 'Withdrawals', key: 'withdrawals' },
        { label: 'Current Balance', key: 'current_balance' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Investors" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Investors</h1>
                        <Badge variant="outline">{investors.total}</Badge>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/investors/trash">
                            <Button variant="outline">Trash</Button>
                        </Link>
                        <Link href="/users/create">
                            <Button>New Investor</Button>
                        </Link>
                    </div>
                </div>

                {/* Currency summary cards (values come from the server, no UI math) */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    {CURRENCIES.map((code) => {
                        const section = currencySections?.[code] ?? null;
                        const isActive = code === activeCurrency;

                        return (
                            <button
                                type="button"
                                key={code}
                                onClick={() => setActiveCurrency(code)}
                                className={`rounded-lg border bg-white p-4 text-left shadow-sm transition-colors dark:bg-transparent ${
                                    isActive ? 'border-primary ring-primary/30 ring-2' : 'hover:border-primary/50'
                                }`}
                            >
                                <p className="mb-2 text-sm font-semibold">{code}</p>
                                <dl className="space-y-1">
                                    {summaryRows.map((row) => (
                                        <div key={row.key} className="flex items-center justify-between gap-3 text-sm">
                                            <dt className="text-muted-foreground">{row.label}</dt>
                                            <dd className="font-mono font-medium">
                                                {section ? amount(section[row.key] as string | number | null | undefined, code) : '—'}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            </button>
                        );
                    })}
                </div>

                {/* Currency tabs */}
                <div role="tablist" aria-label="Investor currency" className="flex gap-1 border-b">
                    {CURRENCIES.map((code) => {
                        const isActive = code === activeCurrency;

                        return (
                            <button
                                key={code}
                                type="button"
                                role="tab"
                                aria-selected={isActive}
                                onClick={() => setActiveCurrency(code)}
                                className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
                                    isActive
                                        ? 'border-primary text-foreground'
                                        : 'text-muted-foreground hover:text-foreground border-transparent'
                                }`}
                            >
                                {code}
                            </button>
                        );
                    })}
                </div>

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search by name, phone, email...">
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
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
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
                                <TableHead>Investor</TableHead>
                                <TableHead className="text-right">Capital ({activeCurrency})</TableHead>
                                <TableHead className="text-right">Profit ({activeCurrency})</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {visibleInvestors.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground h-24 text-center">
                                        No {activeCurrency} investors found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                visibleInvestors.map((investor, index) => {
                                    const wallet = investor.wallet;
                                    // Original-currency wallet values; AFN wallets fall back to the ledger column.
                                    const capital = wallet?.capital_balance_currency ?? (activeCurrency === 'AFN' ? wallet?.capital_balance_afn : null);
                                    const profit = wallet?.profit_balance_currency ?? (activeCurrency === 'AFN' ? wallet?.profit_balance_afn : null);

                                    return (
                                        <TableRow key={investor.id}>
                                            <TableCell>{index + 1}</TableCell>
                                            <TableCell>
                                                <div className="font-medium">{investor.full_name || investor.name}</div>
                                                {investor.phone && (
                                                    <div className="text-muted-foreground text-xs" dir="ltr">
                                                        {investor.phone}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-sm">{amount(capital, activeCurrency)}</TableCell>
                                            <TableCell className="text-right font-mono text-sm">{amount(profit, activeCurrency)}</TableCell>
                                            <TableCell>
                                                {investor.status === 'active' ? (
                                                    <Badge variant="success">Active</Badge>
                                                ) : (
                                                    <Badge variant="outline">Inactive</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <RowActions>
                                                    <ViewAction href={`/investors/${investor.id}`} />
                                                    <EditAction href={`/users/${investor.id}/edit`} />
                                                    <RowAction title="Toggle Status" onClick={() => handleToggleStatus(investor)}>
                                                        <ToggleLeft className="h-4 w-4" />
                                                    </RowAction>
                                                    <DeleteAction onClick={() => setInvestorToDelete(investor)} />
                                                </RowActions>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination */}
                {investors.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {investors.from || 0} to {investors.to || 0} of {investors.total} results
                        </div>
                        <div className="flex gap-1">
                            {investors.links.map((link, i) => (
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

            {investorToDelete && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setInvestorToDelete(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <AlertTriangle className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Are you sure?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Investor <strong>{investorToDelete.full_name || investorToDelete.name}</strong> will
                                    be deactivated. Financial history is preserved.
                                </p>
                            </div>
                            <button type="button" onClick={() => setInvestorToDelete(null)} className="text-muted-foreground hover:text-foreground">
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setInvestorToDelete(null)}>
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
