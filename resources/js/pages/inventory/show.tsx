// resources/js/pages/inventory/show.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowDownCircle, ArrowLeft, ArrowUpCircle, RefreshCw, TrendingDown, TrendingUp, Warehouse, X } from 'lucide-react';
import { useState } from 'react';

interface Inventory {
    id: number;
    quantity: number;
    reserved_quantity: number;
    available_quantity: number;
    average_cost_afn: string;
    last_movement_at: string | null;
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
            category: { id: number; name: string } | null;
        } | null;
    } | null;
}

interface Transaction {
    id: number;
    type: string;
    quantity: number;
    before_quantity: number;
    after_quantity: number;
    notes: string | null;
    created_at: string;
    batch: { id: number; batch_code: string } | null;
    creator: { id: number; name: string } | null;
}

interface Props {
    inventory: Inventory;
    transactions: Transaction[];
    stats: {
        total_purchased: number;
        total_sold: number;
        total_returned: number;
    };
}

const breadcrumbs = (inventory: Inventory): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventory', href: '/inventory' },
    { title: inventory.variant?.product?.name || 'Item', href: `/inventory/${inventory.id}` },
];

const TYPE_LABELS: Record<string, string> = {
    purchase: 'Purchase',
    sale: 'Sale',
    return: 'Return',
    damage: 'Damage',
    transfer_in: 'Transfer In',
    transfer_out: 'Transfer Out',
    adjustment: 'Adjustment',
    opening: 'Opening Stock',
};

export default function InventoryShow({ inventory, transactions, stats }: Props) {
    const { toast } = useToast();
    const [showAdjustModal, setShowAdjustModal] = useState(false);
    const [adjustForm, setAdjustForm] = useState({
        new_quantity: String(inventory.quantity),
        notes: '',
    });
    const [loading, setLoading] = useState(false);

    const handleAdjust = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);

        router.post(`/inventory/${inventory.id}/adjust`, adjustForm, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Success', description: 'Stock adjusted successfully.' });
                setShowAdjustModal(false);
                setLoading(false);
            },
            onError: () => {
                toast({ title: 'Error', description: 'Failed to adjust stock.', variant: 'destructive' });
                setLoading(false);
            },
        });
    };

    const getStockStatus = () => {
        if (inventory.quantity <= 0) {
            return (
                <Badge variant="destructive" className="gap-1">
                    <AlertTriangle className="h-3 w-3" /> Out of Stock
                </Badge>
            );
        }
        if (inventory.variant && inventory.quantity <= inventory.variant.minimum_stock) {
            return (
                <Badge variant="warning" className="gap-1">
                    <TrendingDown className="h-3 w-3" /> Low Stock
                </Badge>
            );
        }
        return (
            <Badge variant="success" className="gap-1">
                <TrendingUp className="h-3 w-3" /> In Stock
            </Badge>
        );
    };

    const getTypeBadge = (type: string) => {
        const positive = ['purchase', 'return', 'transfer_in', 'opening'].includes(type);

        if (type === 'sale') {
            return (
                <Badge variant="destructive" className="gap-1">
                    <ArrowDownCircle className="h-3 w-3" /> Sale
                </Badge>
            );
        }
        if (type === 'purchase') {
            return (
                <Badge variant="success" className="gap-1">
                    <ArrowUpCircle className="h-3 w-3" /> Purchase
                </Badge>
            );
        }
        if (type === 'return') {
            return (
                <Badge variant="success" className="gap-1">
                    <ArrowUpCircle className="h-3 w-3" /> Return
                </Badge>
            );
        }
        if (type === 'adjustment') {
            return (
                <Badge variant="outline" className="gap-1">
                    <RefreshCw className="h-3 w-3" /> Adjustment
                </Badge>
            );
        }

        return <Badge variant={positive ? 'success' : 'destructive'}>{TYPE_LABELS[type] || type}</Badge>;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(inventory)}>
            <Head title={`Inventory — ${inventory.variant?.product?.name}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-14 w-14 items-center justify-center rounded-lg border bg-gray-100">
                            <Warehouse className="h-7 w-7 text-gray-600" />
                        </div>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-xl font-bold">
                                    {inventory.variant?.product?.brand?.name && `${inventory.variant.product.brand.name} — `}
                                    {inventory.variant?.product?.name}
                                </h1>
                                {getStockStatus()}
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {inventory.variant?.volume_ml ? `${inventory.variant.volume_ml}ml` : ''}
                                {inventory.variant?.concentration ? ` · ${inventory.variant.concentration.toUpperCase()}` : ''}
                                {inventory.branch && ` · ${inventory.branch.name}`}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button onClick={() => setShowAdjustModal(true)} variant="outline" className="gap-2">
                            <RefreshCw className="h-4 w-4" /> Adjust Stock
                        </Button>
                        <Link href="/inventory">
                            <Button variant="outline" className="gap-2">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Current Stock</p>
                        <p className="text-2xl font-bold">{inventory.quantity}</p>
                        <p className="text-muted-foreground text-xs">Available: {inventory.available_quantity}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Reserved</p>
                        <p className="text-2xl font-bold text-yellow-600">{inventory.reserved_quantity}</p>
                        <p className="text-muted-foreground text-xs">Min stock: {inventory.variant?.minimum_stock}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Total Purchased</p>
                        <p className="text-2xl font-bold text-green-600">{stats.total_purchased}</p>
                        <p className="text-muted-foreground text-xs">Since start</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Total Sold</p>
                        <p className="text-2xl font-bold text-red-600">{stats.total_sold}</p>
                        <p className="text-muted-foreground text-xs">Returned: {stats.total_returned}</p>
                    </div>
                </div>

                {/* Avg Cost */}
                <div className="rounded-lg border bg-white p-6">
                    <h3 className="mb-2 text-sm font-semibold">Average Cost</h3>
                    <p className="font-mono text-2xl font-bold">
                        {new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(parseFloat(inventory.average_cost_afn))} AFN
                    </p>
                    <p className="text-muted-foreground text-xs">Weighted average across all batches</p>
                </div>

                {/* Transactions */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Transaction History</h2>
                        <p className="text-muted-foreground text-sm">Last 100 movements</p>
                    </div>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Batch</TableHead>
                                    <TableHead className="text-center">Change</TableHead>
                                    <TableHead className="text-center">Before</TableHead>
                                    <TableHead className="text-center">After</TableHead>
                                    <TableHead>By</TableHead>
                                    <TableHead>Notes</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={8} className="text-muted-foreground h-24 text-center">
                                            No transactions yet.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    transactions.map((tx) => (
                                        <TableRow key={tx.id}>
                                            <TableCell className="text-xs">
                                                {new Date(tx.created_at).toLocaleString('en-US', {
                                                    year: 'numeric',
                                                    month: 'short',
                                                    day: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </TableCell>
                                            <TableCell>{getTypeBadge(tx.type)}</TableCell>
                                            <TableCell className="font-mono text-xs">{tx.batch?.batch_code || '—'}</TableCell>
                                            <TableCell className="text-center font-mono font-bold">
                                                <span className={tx.quantity > 0 ? 'text-green-600' : 'text-red-600'}>
                                                    {tx.quantity > 0 ? '+' : ''}
                                                    {tx.quantity}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-center font-mono text-sm text-gray-500">{tx.before_quantity}</TableCell>
                                            <TableCell className="text-center font-mono text-sm font-semibold">{tx.after_quantity}</TableCell>
                                            <TableCell className="text-sm">{tx.creator?.name || '—'}</TableCell>
                                            <TableCell className="text-muted-foreground max-w-xs truncate text-xs">{tx.notes || '—'}</TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>

            {/* Adjust Modal */}
            {showAdjustModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowAdjustModal(false)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <form onSubmit={handleAdjust}>
                            <div className="flex items-start justify-between p-6">
                                <div className="flex items-start gap-4">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100">
                                        <RefreshCw className="h-5 w-5 text-blue-600" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-semibold">Adjust Stock</h3>
                                        <p className="text-muted-foreground mt-1 text-sm">Set the current stock to a new value.</p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setShowAdjustModal(false)}
                                    className="text-muted-foreground hover:text-foreground"
                                >
                                    <X className="h-5 w-5" />
                                </button>
                            </div>

                            <div className="space-y-4 px-6">
                                <div className="space-y-2">
                                    <Label>Current Quantity</Label>
                                    <Input value={inventory.quantity} disabled className="font-mono" />
                                </div>
                                <div className="space-y-2">
                                    <Label>
                                        New Quantity <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        type="number"
                                        min="0"
                                        value={adjustForm.new_quantity}
                                        onChange={(e) => setAdjustForm({ ...adjustForm, new_quantity: e.target.value })}
                                        className="font-mono"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Reason / Notes</Label>
                                    <Textarea
                                        value={adjustForm.notes}
                                        onChange={(e) => setAdjustForm({ ...adjustForm, notes: e.target.value })}
                                        rows={2}
                                        placeholder="e.g. Physical count correction, damage, etc."
                                    />
                                </div>
                            </div>

                            <div className="mt-6 flex justify-end gap-2 border-t px-6 py-4">
                                <Button type="button" variant="outline" onClick={() => setShowAdjustModal(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={loading}>
                                    {loading ? 'Saving...' : 'Adjust Stock'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
