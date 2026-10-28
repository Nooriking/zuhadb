import { FilterField, IndexFilters } from '@/components/index-filters';
import { EditAction, RowAction, RowActions, ViewAction } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { formatRate } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Coins, Plus, Star, ToggleLeft, ToggleRight, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    decimal_places: number;
    rate_to_default: string | number;
    is_base: boolean;
    status: 'active' | 'inactive';
    creator: { id: number; name: string } | null;
    created_at: string;
}

interface Props {
    currencies: {
        data: Currency[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        search: string | null;
        status: string | null;
    };
    rateDefinition: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Currencies', href: '/currencies' },
];

export default function CurrenciesIndex({ currencies, filters, rateDefinition }: Props) {
    const { toast } = useToast();
    const flash = (usePage().props as { flash?: { error?: string; success?: string } }).flash;
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [currencyToDelete, setCurrencyToDelete] = useState<Currency | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);

    const handleSearch = () => {
        router.get(
            '/currencies',
            {
                search: search || undefined,
                status: statusFilter === 'all' ? undefined : statusFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleToggleStatus = (currency: Currency) => {
        setBusyId(currency.id);
        router.patch(
            `/currencies/${currency.id}/toggle-status`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusyId(null),
                onSuccess: () => toast({ title: 'Status changed', description: `Currency ${currency.code} status updated.` }),
                onError: (errors) =>
                    toast({
                        title: 'Cannot change status',
                        description: (errors as { message?: string }).message || 'The default currency cannot be deactivated.',
                        variant: 'destructive',
                    }),
            },
        );
    };

    const handleSetAsDefault = (currency: Currency) => {
        setBusyId(currency.id);
        router.patch(
            `/currencies/${currency.id}/set-as-base`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusyId(null),
                onSuccess: () => toast({ title: 'Default currency', description: `${currency.code} is now the default currency.` }),
                onError: (errors) =>
                    toast({
                        title: 'Cannot set default',
                        description: Object.values(errors)[0] || 'Cannot set this currency as default.',
                        variant: 'destructive',
                    }),
            },
        );
    };

    const confirmDelete = () => {
        if (!currencyToDelete) return;

        router.delete(`/currencies/${currencyToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Moved to trash', description: `Currency ${currencyToDelete.code} moved to trash.` });
                setCurrencyToDelete(null);
            },
            onError: (errors) => {
                toast({
                    title: 'Delete failed',
                    description: errors.message || 'Cannot delete this currency.',
                    variant: 'destructive',
                });
                setCurrencyToDelete(null);
            },
        });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const getStatusBadge = (status: string) =>
        status === 'active' ? <Badge variant="success">Active</Badge> : <Badge variant="secondary">Inactive</Badge>;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Currencies" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Coins className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Currencies</h1>
                        <Badge variant="outline">{currencies.total} Currencies</Badge>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/currencies/trash">
                            <Button variant="outline" className="gap-2">
                                <Trash2 className="h-4 w-4" /> Trash
                            </Button>
                        </Link>
                        <Link href="/currencies/create">
                            <Button className="gap-2">
                                <Plus className="h-4 w-4" /> New Currency
                            </Button>
                        </Link>
                    </div>
                </div>

                <p className="text-muted-foreground text-sm">{rateDefinition} Sales and payments stay in their original ledger currency (AFN) and are not rewritten when the default changes.</p>

                {flash?.error && (
                    <div className="border-destructive/30 bg-destructive/10 text-destructive rounded-md border px-4 py-3 text-sm" role="alert">
                        {flash.error}
                    </div>
                )}

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search by code, name, symbol...">
                    <FilterField label="Status">
                        <Select
                            value={statusFilter}
                            onValueChange={(v) => {
                                setStatusFilter(v);
                                setTimeout(handleSearch, 0);
                            }}
                        >
                            <SelectTrigger className="w-full" aria-label="Filter by status">
                                <SelectValue placeholder="All Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>
                </IndexFilters>

                <div className="overflow-x-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Code</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Symbol</TableHead>
                                <TableHead className="text-right">Decimals</TableHead>
                                <TableHead className="text-right">Rate to default</TableHead>
                                <TableHead>Default</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {currencies.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={9} className="text-muted-foreground h-24 text-center">
                                        No currencies found. Add a currency to get started.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                currencies.data.map((currency, index) => (
                                    <TableRow key={currency.id}>
                                        <TableCell>{currencies.from ? currencies.from + index : index + 1}</TableCell>
                                        <TableCell>
                                            <div className="font-mono text-base font-semibold">{currency.code}</div>
                                        </TableCell>
                                        <TableCell>{currency.name}</TableCell>
                                        <TableCell>
                                            <span className="text-lg">{currency.symbol || '—'}</span>
                                        </TableCell>
                                        <TableCell className="text-right font-mono">{currency.decimal_places}</TableCell>
                                        <TableCell className="text-right font-mono">{formatRate(currency.rate_to_default)}</TableCell>
                                        <TableCell>
                                            {currency.is_base ? (
                                                <Badge variant="success" className="gap-1">
                                                    <Star className="h-3 w-3" />
                                                    Default
                                                </Badge>
                                            ) : (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={busyId === currency.id || currency.status !== 'active'}
                                                    onClick={() => handleSetAsDefault(currency)}
                                                    className="text-muted-foreground hover:text-primary gap-1 text-xs"
                                                >
                                                    <Star className="h-3 w-3" />
                                                    Set as default
                                                </Button>
                                            )}
                                        </TableCell>
                                        <TableCell>{getStatusBadge(currency.status)}</TableCell>
                                        <TableCell>
                                            <RowActions>
                                                <ViewAction href={`/currencies/${currency.id}`} />
                                                <EditAction href={`/currencies/${currency.id}/edit`} />
                                                <RowAction
                                                    title="Toggle status"
                                                    onClick={() => handleToggleStatus(currency)}
                                                    disabled={currency.is_base || busyId === currency.id}
                                                >
                                                    {currency.status === 'active' ? (
                                                        <ToggleRight className="h-4 w-4 text-green-600" />
                                                    ) : (
                                                        <ToggleLeft className="h-4 w-4 text-gray-400" />
                                                    )}
                                                </RowAction>
                                                <RowAction title="Delete" onClick={() => setCurrencyToDelete(currency)} destructive disabled={currency.is_base}>
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

                {currencies.last_page > 1 && (
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {currencies.from || 0} to {currencies.to || 0} of {currencies.total} results
                        </div>
                        <div className="flex flex-wrap gap-1">
                            {currencies.links.map((link, i) => (
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

            {currencyToDelete && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setCurrencyToDelete(null)}>
                    <div
                        role="dialog"
                        aria-labelledby="delete-currency-title"
                        className="bg-background w-full max-w-md rounded-lg border shadow-lg"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <AlertTriangle className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 id="delete-currency-title" className="text-lg font-semibold">
                                    Are you sure?
                                </h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Currency <strong>{currencyToDelete.code}</strong> will be moved to trash.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setCurrencyToDelete(null)}
                                className="text-muted-foreground hover:text-foreground transition-colors"
                                aria-label="Close"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setCurrencyToDelete(null)}>
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
