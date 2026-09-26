// resources/js/pages/inventory/low-stock.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Eye, TrendingDown } from 'lucide-react';

interface Inventory {
    id: number;
    quantity: number;
    available_quantity: number;
    branch: { id: number; name: string; code: string } | null;
    variant: {
        id: number;
        name: string | null;
        volume_ml: number | null;
        concentration: string | null;
        minimum_stock: number;
        product: {
            id: number;
            name: string;
            brand: { id: number; name: string } | null;
        } | null;
    } | null;
}

interface Props {
    inventories: {
        data: Inventory[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventory', href: '/inventory' },
    { title: 'Low Stock', href: '/inventory/low-stock' },
];

export default function LowStock({ inventories }: Props) {
    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Low Stock Items" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <TrendingDown className="h-6 w-6 text-yellow-600" />
                        <h1 className="text-2xl font-bold tracking-tight">Low Stock Items</h1>
                        <Badge variant="warning">{inventories.total} Items</Badge>
                    </div>
                    <Link href="/inventory">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back to Inventory
                        </Button>
                    </Link>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Product</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead className="text-center">Quantity</TableHead>
                                <TableHead className="text-center">Min Stock</TableHead>
                                <TableHead className="text-center">Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {inventories.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground h-24 text-center">
                                        <div className="flex flex-col items-center gap-2">
                                            <AlertTriangle className="text-muted-foreground h-8 w-8" />
                                            <p>No low stock items. Everything is well stocked! 🎉</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                inventories.data.map((inv, index) => (
                                    <TableRow key={inv.id}>
                                        <TableCell>{inventories.from ? inventories.from + index : index + 1}</TableCell>
                                        <TableCell>
                                            <div className="font-medium">
                                                {inv.variant?.product?.brand?.name && (
                                                    <span className="text-muted-foreground">{inv.variant.product.brand.name} — </span>
                                                )}
                                                {inv.variant?.product?.name}
                                            </div>
                                            <div className="text-muted-foreground text-xs">
                                                {inv.variant?.volume_ml ? `${inv.variant.volume_ml}ml` : ''}
                                                {inv.variant?.concentration ? ` · ${inv.variant.concentration.toUpperCase()}` : ''}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline" className="text-xs">
                                                {inv.branch?.code}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-center font-mono font-bold text-yellow-600">{inv.quantity}</TableCell>
                                        <TableCell className="text-center font-mono text-sm text-gray-500">
                                            {inv.variant?.minimum_stock || 0}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {inv.quantity <= 0 ? <Badge variant="destructive">Out</Badge> : <Badge variant="warning">Low</Badge>}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-1">
                                                <Link href={`/inventory/${inv.id}`}>
                                                    <Button variant="ghost" size="icon" title="View">
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {inventories.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {inventories.from || 0} to {inventories.to || 0} of {inventories.total} results
                        </div>
                        <div className="flex gap-1">
                            {inventories.links.map((link, i) => (
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
