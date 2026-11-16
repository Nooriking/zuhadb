// resources/js/pages/investors/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import CurrencySectionCards, {
    type CurrencySectionMetric,
    type ExchangeValuation,
} from '@/components/investors/currency-section-cards';
import { DeleteAction, EditAction, RowAction, RowActions, ViewAction } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { formatLedgerMoney, type SharedCurrencyPayload } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Plus, ToggleLeft, Trash2, Users, X } from 'lucide-react';
import { useState } from 'react';

interface Wallet {
    id: number;
    capital_balance_afn: string;
    profit_balance_afn: string;
    total_invested_afn: string;
    total_profit_earned_afn: string;
    total_withdrawn_afn: string;
}

interface Investor {
    id: number;
    name?: string;
    full_name: string;
    phone: string | null;
    email: string | null;
    status: 'active' | 'inactive' | 'blocked';
    investments_count: number;
    withdrawals_count: number;
    purchase_rows_count?: number;
    purchase_items_count?: number;
    batches_count: number;
    unallocated_capital_afn?: string | number | null;
    cash_capital_afn?: string | number | null;
    goods_contribution_afn?: string | number | null;
    goods_held_afn?: string | number | null;
    goods_sold_afn?: string | number | null;
    total_investor_profit_afn?: string | number | null;
    paid_profit_afn?: string | number | null;
    pending_profit_afn?: string | number | null;
    total_withdrawals_afn?: string | number | null;
    current_balance_afn?: string | number | null;
    wallet: Wallet | null;
    creator: { id: number; name: string } | null;
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
    stats: {
        total_investors: number;
        active_investors: number;
        cash_capital?: string | number;
        goods_contribution?: string | number;
        goods_held?: string | number;
        goods_sold?: string | number;
        total_investor_profit?: string | number;
        paid_profit?: string | number;
        pending_profit?: string | number;
        total_withdrawals?: string | number;
        current_balance?: string | number;
        total_capital?: string | number;
        total_unallocated?: string | number;
    };
    kpi_sources?: Record<string, string>;
    currencySections?: Record<string, CurrencySectionMetric> | null;
    exchangeValuation?: ExchangeValuation | null;
    limitations?: string[];
    legacy?: {
        profit_afn_without_currency?: string | number;
        withdrawals_afn_without_currency?: string | number;
        purchases_without_currency?: number;
        note?: string;
    } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investors', href: '/investors' },
];

export default function InvestorsIndex({
    investors,
    filters,
    stats,
    kpi_sources,
    currencySections = null,
    exchangeValuation = null,
    limitations = [],
    legacy = null,
}: Props) {
    const { toast } = useToast();
    const { currency } = usePage().props as { currency?: SharedCurrencyPayload };
    const money = (value: string | number | null | undefined) => formatLedgerMoney(value, currency);
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [investorToDelete, setInvestorToDelete] = useState<Investor | null>(null);

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Investors" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Users className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Investors</h1>
                        <Badge variant="outline">{investors.total} Investors</Badge>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/investors/trash">
                            <Button variant="outline" className="gap-2">
                                <Trash2 className="h-4 w-4" /> Trash
                            </Button>
                        </Link>
                        <Link href="/users/create">
                            <Button className="gap-2">
                                <Plus className="h-4 w-4" /> New Investor User
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Original-currency portfolio summaries — never cross-add AFN+USD+AED */}
                <CurrencySectionCards
                    currencySections={currencySections}
                    exchangeValuation={exchangeValuation}
                    limitations={limitations}
                    legacy={legacy}
                />

                {/* AFN ledger KPIs (wallet / allocation ledger). Separate from original-currency sections above. */}
                <div>
                    <h2 className="mb-2 text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">
                        AFN ledger portfolio totals
                    </h2>
                    <p className="text-muted-foreground mb-3 text-xs">
                        Ledger figures in AFN only. Foreign originals are shown in the currency sections above — not converted into these cards.
                    </p>
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
                        {[
                            { label: 'Cash Capital (AFN ledger)', value: stats.cash_capital, tip: kpi_sources?.cash_capital },
                            { label: 'Goods Contribution (AFN ledger)', value: stats.goods_contribution, tip: kpi_sources?.goods_contribution },
                            { label: 'Goods Held (AFN ledger)', value: stats.goods_held, tip: kpi_sources?.goods_held },
                            { label: 'Goods Sold (AFN ledger)', value: stats.goods_sold, tip: kpi_sources?.goods_sold },
                            { label: 'Total Investor Profit (AFN)', value: stats.total_investor_profit, tip: kpi_sources?.total_investor_profit },
                            { label: 'Paid Profit (AFN)', value: stats.paid_profit, tip: kpi_sources?.paid_profit },
                            { label: 'Pending Profit (AFN)', value: stats.pending_profit, tip: kpi_sources?.pending_profit },
                            { label: 'Total Withdrawals (AFN)', value: stats.total_withdrawals, tip: kpi_sources?.total_withdrawals },
                            { label: 'Current Balance (AFN)', value: stats.current_balance, tip: kpi_sources?.current_balance },
                            { label: 'Active Investors', value: stats.active_investors, tip: 'users.role=investor status=active', raw: true },
                        ].map((card) => (
                            <div key={card.label} className="rounded-lg border bg-white p-4 shadow-sm" title={card.tip}>
                                <p className="text-muted-foreground text-xs">{card.label}</p>
                                <p className="mt-1 text-xl font-bold">
                                    {'raw' in card && card.raw ? card.value : money(card.value ?? 0)}
                                </p>
                            </div>
                        ))}
                    </div>
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
                                <TableHead className="text-right">Cash Capital</TableHead>
                                <TableHead className="text-right">Goods Contrib.</TableHead>
                                <TableHead className="text-right">Goods Held</TableHead>
                                <TableHead className="text-right">Goods Sold</TableHead>
                                <TableHead className="text-right">Profit</TableHead>
                                <TableHead className="text-right">Paid</TableHead>
                                <TableHead className="text-right">Pending</TableHead>
                                <TableHead className="text-right">Withdrawals</TableHead>
                                <TableHead className="text-right">Balance</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {investors.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={13} className="text-muted-foreground h-24 text-center">
                                        No investors found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                investors.data.map((investor, index) => (
                                    <TableRow key={investor.id}>
                                        <TableCell>{investors.from ? investors.from + index : index + 1}</TableCell>
                                        <TableCell>
                                            <div className="font-medium">{investor.full_name || investor.name}</div>
                                            {investor.phone && (
                                                <div className="text-muted-foreground text-xs" dir="ltr">
                                                    {investor.phone}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-sm">{money(investor.cash_capital_afn || 0)}</TableCell>
                                        <TableCell className="text-right font-mono text-sm">{money(investor.goods_contribution_afn || 0)}</TableCell>
                                        <TableCell className="text-right font-mono text-sm">{money(investor.goods_held_afn || 0)}</TableCell>
                                        <TableCell className="text-right font-mono text-sm">{money(investor.goods_sold_afn || 0)}</TableCell>
                                        <TableCell className="text-right font-mono text-sm">{money(investor.total_investor_profit_afn || 0)}</TableCell>
                                        <TableCell className="text-right font-mono text-sm">{money(investor.paid_profit_afn || 0)}</TableCell>
                                        <TableCell className="text-right font-mono text-sm">{money(investor.pending_profit_afn || 0)}</TableCell>
                                        <TableCell className="text-right font-mono text-sm">{money(investor.total_withdrawals_afn || 0)}</TableCell>
                                        <TableCell className="text-right font-mono text-sm font-semibold">{money(investor.current_balance_afn || 0)}</TableCell>
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
                                ))
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
