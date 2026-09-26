// resources/js/pages/currencies/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { EditAction, RowAction, RowActions, ViewAction } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Coins, Plus, Star, ToggleLeft, ToggleRight, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
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
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Currencies', href: '/currencies' },
];

export default function CurrenciesIndex({ currencies, filters }: Props) {
    const { toast } = useToast();
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [currencyToDelete, setCurrencyToDelete] = useState<Currency | null>(null);

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
        router.patch(
            `/currencies/${currency.id}/toggle-status`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Status Changed', description: `Currency ${currency.code} status updated.` }),
                onError: () => toast({ title: 'Error', description: 'Cannot change status.', variant: 'destructive' }),
            },
        );
    };

    const handleSetAsBase = (currency: Currency) => {
        router.patch(
            `/currencies/${currency.id}/set-as-base`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Base Currency', description: `${currency.code} is now the base currency.` }),
                onError: () => toast({ title: 'Error', description: 'Cannot set as base.', variant: 'destructive' }),
            },
        );
    };

    const confirmDelete = () => {
        if (!currencyToDelete) return;

        router.delete(`/currencies/${currencyToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Moved to Trash', description: `Currency ${currencyToDelete.code} moved to trash.` });
                setCurrencyToDelete(null);
            },
            onError: (errors) => {
                toast({
                    title: 'Delete Failed',
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
                {/* Header */}
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

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search by code, name, symbol...">
                    <FilterField label="Status">
                        <Select
                            value={statusFilter}
                            onValueChange={(v) => {
                                setStatusFilter(v);
                                setTimeout(handleSearch, 0);
                            }}
                        >
                            <SelectTrigger className="w-full">
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

                {/* Table */}
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Code</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Symbol</TableHead>
                                <TableHead>Base</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {currencies.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground h-24 text-center">
                                        No currencies found.
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
                                        <TableCell>
                                            {currency.is_base ? (
                                                <Badge variant="success" className="gap-1">
                                                    <Star className="h-3 w-3" />
                                                    Base
                                                </Badge>
                                            ) : (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleSetAsBase(currency)}
                                                    className="text-muted-foreground hover:text-primary gap-1 text-xs"
                                                >
                                                    <Star className="h-3 w-3" />
                                                    Set as base
                                                </Button>
                                            )}
                                        </TableCell>
                                        <TableCell>{getStatusBadge(currency.status)}</TableCell>
                                        <TableCell>
                                            <RowActions>
                                                <ViewAction href={`/currencies/${currency.id}`} />
                                                <EditAction href={`/currencies/${currency.id}/edit`} />
                                                <RowAction title="Toggle Status" onClick={() => handleToggleStatus(currency)} disabled={currency.is_base}>
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

                {/* Pagination */}
                {currencies.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {currencies.from || 0} to {currencies.to || 0} of {currencies.total} results
                        </div>
                        <div className="flex gap-1">
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

            {/* Delete Confirmation Modal */}
            {currencyToDelete && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setCurrencyToDelete(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <AlertTriangle className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Are you sure?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Currency <strong>{currencyToDelete.code}</strong> will be moved to trash.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setCurrencyToDelete(null)}
                                className="text-muted-foreground hover:text-foreground transition-colors"
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
