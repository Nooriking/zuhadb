// resources/js/pages/investors/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
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
import { AlertTriangle, Banknote, Plus, ToggleLeft, Trash2, TrendingUp, Users, Wallet, X } from 'lucide-react';
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
    full_name: string;
    phone: string | null;
    email: string | null;
    status: 'active' | 'inactive';
    investments_count: number;
    withdrawals_count: number;
    purchase_rows_count?: number;
    purchase_items_count?: number;
    batches_count: number;
    unallocated_capital_afn?: string | number | null;
    wallet: Wallet | null;
    user: { id: number; name: string } | null;
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
        total_capital: string;
        total_profit_paid: string;
        total_withdrawn: string;
        total_unallocated?: string | number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investors', href: '/investors' },
];

export default function InvestorsIndex({ investors, filters, stats }: Props) {
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
                        <Link href="/investors/create">
                            <Button className="gap-2">
                                <Plus className="h-4 w-4" /> New Investor
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Stats Cards — ledger AFN totals from investor_wallets / investments */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-6">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Total Investors</p>
                            <Users className="h-4 w-4 text-blue-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold">{stats.total_investors}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Active</p>
                            <ToggleLeft className="h-4 w-4 text-green-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-green-600">{stats.active_investors}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Capital (AFN ledger)</p>
                            <Wallet className="h-4 w-4 text-blue-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-blue-600">{money(stats.total_capital)}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Unallocated (AFN)</p>
                            <Wallet className="h-4 w-4 text-sky-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-sky-600">{money(stats.total_unallocated ?? 0)}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Total Profit Earned</p>
                            <TrendingUp className="h-4 w-4 text-purple-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-purple-600">{money(stats.total_profit_paid)}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Total Withdrawn</p>
                            <Banknote className="h-4 w-4 text-orange-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-orange-600">{money(stats.total_withdrawn)}</p>
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
                                <TableHead>Phone</TableHead>
                                <TableHead className="text-right">Capital</TableHead>
                                <TableHead className="text-right">Unallocated</TableHead>
                                <TableHead className="text-center">Investments</TableHead>
                                <TableHead className="text-center">Purchases</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {investors.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={9} className="text-muted-foreground h-24 text-center">
                                        No investors found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                investors.data.map((investor, index) => (
                                    <TableRow key={investor.id}>
                                        <TableCell>{investors.from ? investors.from + index : index + 1}</TableCell>
                                        <TableCell>
                                            <div className="font-medium">{investor.full_name}</div>
                                            {investor.user && <div className="text-muted-foreground text-xs">@{investor.user.name}</div>}
                                        </TableCell>
                                        <TableCell className="text-sm" dir="ltr">
                                            {investor.phone || '—'}
                                        </TableCell>
                                        <TableCell className="text-right font-mono font-semibold">
                                            {money(investor.wallet?.capital_balance_afn || 0)}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-sm text-sky-700">
                                            {money(investor.unallocated_capital_afn || 0)}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <Link href={`/investments?investor_id=${investor.id}`} className="hover:underline">
                                                <Badge variant="secondary">{investor.investments_count}</Badge>
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <Badge variant="outline">
                                                {investor.purchase_rows_count ?? investor.purchase_items_count ?? 0}
                                            </Badge>
                                        </TableCell>
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
                                                <EditAction href={`/investors/${investor.id}/edit`} />
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
                                    Investor <strong>{investorToDelete.full_name}</strong> will be moved to trash.
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
