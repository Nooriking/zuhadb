// resources/js/pages/inventory/transactions.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowDownCircle, ArrowLeft, ArrowLeftRight, ArrowUpCircle, RefreshCw } from 'lucide-react';
import { useState } from 'react';

interface Transaction {
    id: number;
    type: string;
    quantity: number;
    before_quantity: number;
    after_quantity: number;
    notes: string | null;
    created_at: string;
    branch: { id: number; name: string; code: string } | null;
    variant: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
        product: { id: number; name: string; brand: { id: number; name: string } | null } | null;
    } | null;
    batch: { id: number; batch_code: string } | null;
    creator: { id: number; name: string } | null;
}

interface Props {
    transactions: {
        data: Transaction[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    branches: { id: number; name: string; code: string }[];
    types: Record<string, string>;
    filters: {
        branch_id: string | null;
        type: string | null;
        variant_id: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventory', href: '/inventory' },
    { title: 'Transactions', href: '/inventory/transactions' },
];

export default function Transactions({ transactions, branches, types, filters }: Props) {
    const [branchFilter, setBranchFilter] = useState(filters.branch_id || 'all');
    const [typeFilter, setTypeFilter] = useState(filters.type || 'all');

    const handleSearch = () => {
        router.get(
            '/inventory/transactions',
            {
                branch_id: branchFilter === 'all' ? undefined : branchFilter,
                type: typeFilter === 'all' ? undefined : typeFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const getTypeBadge = (type: string) => {
        const badgeMap: Record<string, JSX.Element> = {
            sale: (
                <Badge variant="destructive" className="gap-1">
                    <ArrowDownCircle className="h-3 w-3" /> Sale
                </Badge>
            ),
            purchase: (
                <Badge variant="success" className="gap-1">
                    <ArrowUpCircle className="h-3 w-3" /> Purchase
                </Badge>
            ),
            return: (
                <Badge variant="success" className="gap-1">
                    <ArrowUpCircle className="h-3 w-3" /> Return
                </Badge>
            ),
            damage: <Badge variant="destructive">Damage</Badge>,
            transfer_in: (
                <Badge variant="success">
                    <ArrowUpCircle className="h-3 w-3" /> Transfer In
                </Badge>
            ),
            transfer_out: (
                <Badge variant="destructive">
                    <ArrowDownCircle className="h-3 w-3" /> Transfer Out
                </Badge>
            ),
            adjustment: (
                <Badge variant="outline" className="gap-1">
                    <RefreshCw className="h-3 w-3" /> Adjust
                </Badge>
            ),
            opening: <Badge variant="outline">Opening</Badge>,
        };
        return badgeMap[type] || <Badge variant="secondary">{type}</Badge>;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inventory Transactions" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <ArrowLeftRight className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Inventory Transactions</h1>
                        <Badge variant="outline">{transactions.total} Transactions</Badge>
                    </div>
                    <Link href="/inventory">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back to Inventory
                        </Button>
                    </Link>
                </div>

                {/* Filters */}
                <div className="flex flex-wrap gap-2">
                    <Select
                        value={branchFilter}
                        onValueChange={(v) => {
                            setBranchFilter(v);
                            setTimeout(handleSearch, 0);
                        }}
                    >
                        <SelectTrigger className="w-[200px]">
                            <SelectValue placeholder="Branch" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Branches</SelectItem>
                            {branches.map((b) => (
                                <SelectItem key={b.id} value={String(b.id)}>
                                    {b.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={typeFilter}
                        onValueChange={(v) => {
                            setTypeFilter(v);
                            setTimeout(handleSearch, 0);
                        }}
                    >
                        <SelectTrigger className="w-[200px]">
                            <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            {Object.entries(types).map(([key, label]) => (
                                <SelectItem key={key} value={key}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Table */}
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Product</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Batch</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead className="text-center">Change</TableHead>
                                <TableHead className="text-center">Before → After</TableHead>
                                <TableHead>By</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {transactions.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={9} className="text-muted-foreground h-24 text-center">
                                        No transactions found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                transactions.data.map((tx, index) => (
                                    <TableRow key={tx.id}>
                                        <TableCell>{transactions.from ? transactions.from + index : index + 1}</TableCell>
                                        <TableCell className="text-xs">
                                            {new Date(tx.created_at).toLocaleString('en-US', {
                                                year: 'numeric',
                                                month: 'short',
                                                day: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </TableCell>
                                        <TableCell>
                                            <div className="text-sm font-medium">
                                                {tx.variant?.product?.brand?.name && (
                                                    <span className="text-muted-foreground">{tx.variant.product.brand.name} — </span>
                                                )}
                                                {tx.variant?.product?.name}
                                            </div>
                                            <div className="text-muted-foreground text-xs">
                                                {tx.variant?.volume_ml ? `${tx.variant.volume_ml}ml` : ''}
                                                {tx.variant?.concentration ? ` · ${tx.variant.concentration.toUpperCase()}` : ''}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline" className="text-xs">
                                                {tx.branch?.code}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">{tx.batch?.batch_code || '—'}</TableCell>
                                        <TableCell>{getTypeBadge(tx.type)}</TableCell>
                                        <TableCell className="text-center font-mono font-bold">
                                            <span className={tx.quantity > 0 ? 'text-green-600' : 'text-red-600'}>
                                                {tx.quantity > 0 ? '+' : ''}
                                                {tx.quantity}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-center font-mono text-xs text-gray-500">
                                            {tx.before_quantity} → {tx.after_quantity}
                                        </TableCell>
                                        <TableCell className="text-sm">{tx.creator?.name || '—'}</TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination */}
                {transactions.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {transactions.from || 0} to {transactions.to || 0} of {transactions.total} results
                        </div>
                        <div className="flex gap-1">
                            {transactions.links.map((link, i) => (
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
        </AppLayout>
    );
}
