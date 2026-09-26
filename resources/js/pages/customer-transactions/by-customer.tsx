// resources/js/pages/customer-transactions/by-customer.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, BookOpen, Plus, TrendingDown, TrendingUp, User, Wallet } from 'lucide-react';
import { useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Customer {
    id: number;
    name: string;
    phone: string | null;
    address: string | null;
    current_due_afn: string;
    credit_limit_afn: string;
}

interface Transaction {
    id: number;
    type: 'sale' | 'payment' | 'return' | 'adjustment';
    amount_afn: string;
    balance_after: string;
    reference_type: string | null;
    reference_id: number | null;
    notes: string | null;
    created_at: string;
    creator: { id: number; name: string } | null;
}

interface Stats {
    current_due_afn: string;
    credit_limit_afn: string;
    available_credit: number;
    total_sales: string;
    total_payments: string;
    total_returns: string;
    total_adjustments: string;
    transaction_count: number;
}

interface Props {
    customer: Customer;
    transactions: {
        data: Transaction[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { type: string | null };
    stats: Stats;
}

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const formatPrice = (value: string | number, digits: number = 2) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(num || 0);
};

const formatDateTime = (value: string) => {
    return new Date(value).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const TYPE_BADGE: Record<string, { label: string; variant: 'success' | 'destructive' | 'secondary' | 'outline' }> = {
    sale: { label: 'Sale', variant: 'destructive' },
    payment: { label: 'Payment', variant: 'success' },
    return: { label: 'Return', variant: 'secondary' },
    adjustment: { label: 'Adjustment', variant: 'outline' },
};

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function CustomerTransactionsByCustomer({ customer, transactions, filters, stats }: Props) {
    const { toast } = useToast();
    const [typeFilter, setTypeFilter] = useState(filters.type || 'all');
    const [showAdjustmentModal, setShowAdjustmentModal] = useState(false);
    const [adjustmentForm, setAdjustmentForm] = useState({ amount_afn: '', notes: '' });
    const [adjustmentLoading, setAdjustmentLoading] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Customers', href: '/customers' },
        { title: customer.name, href: `/customers/${customer.id}` },
        { title: 'Transactions', href: `/customers/${customer.id}/transactions` },
    ];

    const handleTypeChange = (v: string) => {
        setTypeFilter(v);
        router.get(`/customers/${customer.id}/transactions`, { type: v === 'all' ? undefined : v }, { preserveState: true, preserveScroll: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const handleAdjustmentSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setAdjustmentLoading(true);

        router.post(
            `/customers/${customer.id}/transactions/adjustment`,
            {
                amount_afn: parseFloat(adjustmentForm.amount_afn) || 0,
                notes: adjustmentForm.notes,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast({ title: 'Success', description: 'Adjustment saved.' });
                    setShowAdjustmentModal(false);
                    setAdjustmentForm({ amount_afn: '', notes: '' });
                    setAdjustmentLoading(false);
                },
                onError: () => {
                    toast({ title: 'Error', description: 'Could not save the adjustment.', variant: 'destructive' });
                    setAdjustmentLoading(false);
                },
            },
        );
    };

    const currentDue = parseFloat(stats.current_due_afn || '0');
    const creditLimit = parseFloat(stats.credit_limit_afn || '0');
    const exceeded = creditLimit > 0 && currentDue > creditLimit;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${customer.name} - Transactions`} />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 flex h-12 w-12 items-center justify-center rounded-full">
                            <User className="text-primary h-6 w-6" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{customer.name}</h1>
                            <p className="text-muted-foreground text-sm">{customer.phone || 'No phone'} · Ledger</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setShowAdjustmentModal(true)} className="gap-2">
                            <Plus className="h-4 w-4" /> Add Adjustment
                        </Button>
                        <Link href={`/customers/${customer.id}`}>
                            <Button variant="outline" className="gap-2">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* ═══════════ Stats ═══════════ */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Card className={exceeded ? 'border-2 border-red-400 bg-red-50' : 'border-2 border-amber-300 bg-amber-50'}>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Current Due</p>
                                <Wallet className="h-4 w-4" />
                            </div>
                            <p className={`mt-1 text-2xl font-bold ${exceeded ? 'text-red-700' : 'text-amber-900'}`}>
                                {formatPrice(currentDue)} <span className="text-sm">AFN</span>
                            </p>
                            {exceeded && (
                                <div className="mt-1 flex items-center gap-1 text-xs text-red-700">
                                    <AlertTriangle className="h-3 w-3" /> Exceeded credit limit!
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Credit Limit</p>
                                <Wallet className="h-4 w-4 text-gray-500" />
                            </div>
                            <p className="mt-1 text-2xl font-bold">{creditLimit > 0 ? `${formatPrice(creditLimit)} AFN` : '—'}</p>
                            {creditLimit > 0 && (
                                <p className="text-muted-foreground mt-1 text-xs">Available: {formatPrice(stats.available_credit)} AFN</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Sales</p>
                                <TrendingUp className="h-4 w-4 text-red-500" />
                            </div>
                            <p className="mt-1 text-xl font-bold text-red-600">{formatPrice(stats.total_sales)} AFN</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-xs">Total Payments</p>
                                <TrendingDown className="h-4 w-4 text-green-500" />
                            </div>
                            <p className="mt-1 text-xl font-bold text-green-600">{formatPrice(stats.total_payments)} AFN</p>
                        </CardContent>
                    </Card>
                </div>

                {/* ═══════════ Filter ═══════════ */}
                <Card>
                    <CardContent className="flex flex-wrap items-center gap-3 p-4">
                        <div className="flex items-center gap-2">
                            <BookOpen className="text-muted-foreground h-4 w-4" />
                            <span className="text-sm font-medium">Filter by type:</span>
                        </div>
                        <Select value={typeFilter} onValueChange={handleTypeChange}>
                            <SelectTrigger className="w-[200px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Types</SelectItem>
                                <SelectItem value="sale">Sale</SelectItem>
                                <SelectItem value="payment">Payment</SelectItem>
                                <SelectItem value="return">Return</SelectItem>
                                <SelectItem value="adjustment">Adjustment</SelectItem>
                            </SelectContent>
                        </Select>
                        <Badge variant="outline" className="ml-auto">
                            {transactions.total} Transactions
                        </Badge>
                    </CardContent>
                </Card>

                {/* ═══════════ Table ═══════════ */}
                <div className="overflow-x-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead className="text-right">Amount (AFN)</TableHead>
                                <TableHead className="text-right">Balance After</TableHead>
                                <TableHead>Reference</TableHead>
                                <TableHead>Notes</TableHead>
                                <TableHead>By</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {transactions.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={8} className="text-muted-foreground h-24 text-center">
                                        No transactions found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                transactions.data.map((txn, index) => {
                                    const amount = parseFloat(txn.amount_afn || '0');
                                    const balance = parseFloat(txn.balance_after || '0');
                                    const badge = TYPE_BADGE[txn.type] || TYPE_BADGE.adjustment;

                                    return (
                                        <TableRow key={txn.id}>
                                            <TableCell>{transactions.from ? transactions.from + index : index + 1}</TableCell>
                                            <TableCell className="text-xs">{formatDateTime(txn.created_at)}</TableCell>
                                            <TableCell>
                                                <Badge variant={badge.variant}>{badge.label}</Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <span className={`font-mono text-sm font-semibold ${amount > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                                    {amount > 0 ? '+' : ''}
                                                    {formatPrice(amount)}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <span
                                                    className={`font-mono text-xs font-medium ${balance > 0 ? 'text-amber-700' : 'text-green-600'}`}
                                                >
                                                    {formatPrice(balance)} AFN
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {txn.reference_type ? (
                                                    <span className="text-muted-foreground font-mono text-[10px]">
                                                        {txn.reference_type.split('\\').pop()} #{txn.reference_id}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <span className="text-muted-foreground line-clamp-1 max-w-[200px] text-xs">{txn.notes || '—'}</span>
                                            </TableCell>
                                            <TableCell className="text-xs">{txn.creator?.name || '—'}</TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* ═══════════ Pagination ═══════════ */}
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

            {/* ═══════════ Adjustment Modal ═══════════ */}
            {showAdjustmentModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowAdjustmentModal(false)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <form onSubmit={handleAdjustmentSubmit}>
                            <div className="border-b p-4">
                                <h3 className="flex items-center gap-2 text-lg font-semibold">
                                    <Plus className="h-5 w-5" />
                                    Add Manual Adjustment
                                </h3>
                                <p className="text-muted-foreground mt-1 text-xs">Positive increases the due. Negative decreases it.</p>
                            </div>

                            <div className="space-y-4 p-4">
                                <div className="space-y-2">
                                    <Label>
                                        Amount (AFN) <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={adjustmentForm.amount_afn}
                                        onChange={(e) => setAdjustmentForm({ ...adjustmentForm, amount_afn: e.target.value })}
                                        placeholder="e.g. 5000 or -2000"
                                        className="font-mono"
                                    />
                                    <p className="text-muted-foreground text-xs">Example: 5000 to increase, -2000 to decrease</p>
                                </div>

                                <div className="space-y-2">
                                    <Label>
                                        Notes <span className="text-destructive">*</span>
                                    </Label>
                                    <Textarea
                                        value={adjustmentForm.notes}
                                        onChange={(e) => setAdjustmentForm({ ...adjustmentForm, notes: e.target.value })}
                                        placeholder="Reason for the adjustment..."
                                        rows={3}
                                    />
                                </div>

                                {adjustmentForm.amount_afn && (
                                    <div className="rounded-lg border bg-blue-50 p-3 text-sm">
                                        <div className="grid grid-cols-2 gap-1">
                                            <span className="text-muted-foreground">Current Due:</span>
                                            <span className="text-right font-mono">{formatPrice(currentDue)} AFN</span>

                                            <span className="text-muted-foreground">Adjustment:</span>
                                            <span
                                                className={`text-right font-mono ${
                                                    parseFloat(adjustmentForm.amount_afn) > 0 ? 'text-red-600' : 'text-green-600'
                                                }`}
                                            >
                                                {parseFloat(adjustmentForm.amount_afn) > 0 ? '+' : ''}
                                                {formatPrice(parseFloat(adjustmentForm.amount_afn) || 0)} AFN
                                            </span>

                                            <span className="col-span-2 border-t pt-1 font-semibold">New Due:</span>
                                            <span className="col-span-2 text-right font-mono font-bold">
                                                {formatPrice(currentDue + (parseFloat(adjustmentForm.amount_afn) || 0))} AFN
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="flex justify-end gap-2 border-t p-4">
                                <Button type="button" variant="outline" onClick={() => setShowAdjustmentModal(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={adjustmentLoading || !adjustmentForm.amount_afn || !adjustmentForm.notes}>
                                    {adjustmentLoading ? 'Saving...' : 'Save Adjustment'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
