import { BackButton } from '@/components/form-page';
// resources/js/pages/sale-returns/create.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, ArrowLeft, Package, RotateCcw, Save, ScanBarcode } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface SaleOption {
    id: number;
    invoice_number: string;
    total_afn: string;
    sale_date: string;
    customer_id: number | null;
    branch_id: number;
}

interface SaleItemInfo {
    id: number;
    variant_id: number;
    variant_name: string | null;
    brand_name: string | null;
    product_name: string | null;
    volume_ml: number | null;
    concentration: string | null;
    batch_code: string | null;
    batch_id: number | null;
    barcode_snapshot: string | null;
    quantity_sold: number;
    already_returned: number;
    max_returnable: number;
    sale_price_afn: number;
}

interface SelectedSaleInfo {
    id: number;
    invoice_number: string;
    total_afn: string;
    remaining_due_afn: string;
    customer: { id: number; name: string; phone: string | null; current_due_afn: string } | null;
    branch: { id: number; name: string; code: string } | null;
}

interface Props {
    sales: SaleOption[];
    selectedSale: SelectedSaleInfo | null;
    saleItems: SaleItemInfo[];
    scanBarcode?: string | null;
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sale Returns', href: '/sale-returns' },
    { title: 'Create Return', href: '/sale-returns/create' },
];

const formatPrice = (value: number | string) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(num || 0);
};

const CONDITION_OPTIONS = [
    { value: 'good', label: 'Good — returned to stock', color: 'text-green-600' },
    { value: 'damaged', label: 'Damaged — not returned to stock', color: 'text-red-600' },
    { value: 'expired', label: 'Expired — not returned to stock', color: 'text-amber-600' },
    { value: 'used', label: 'Used — expense, amount reduced', color: 'text-orange-700' },
    { value: 'other', label: 'Other', color: 'text-gray-600' },
];

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function SaleReturnsCreate({ sales, selectedSale, saleItems, scanBarcode = null, errors: initialErrors }: Props) {
    const { toast } = useToast();
    const barcodeInputRef = useRef<HTMLInputElement>(null);

    const [form, setForm] = useState({
        sale_id: selectedSale ? String(selectedSale.id) : '',
        return_date: new Date().toISOString().slice(0, 10),
        reason: '',
        status: 'draft' as 'draft' | 'completed' | 'cancelled',
    });

    const [barcode, setBarcode] = useState(scanBarcode || '');
    const [barcodeLoading, setBarcodeLoading] = useState(false);
    const [barcodeError, setBarcodeError] = useState('');
    const [matchedSaleItemId, setMatchedSaleItemId] = useState<number | null>(null);

    const [items, setItems] = useState<Record<number, { quantity: string; condition: string; reason: string; expense_amount: string }>>({});

    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setForm((prev) => ({ ...prev, sale_id: selectedSale ? String(selectedSale.id) : prev.sale_id }));
    }, [selectedSale?.id]);

    useEffect(() => {
        const next: Record<number, { quantity: string; condition: string; reason: string; expense_amount: string }> = {};
        let matchedId: number | null = null;

        saleItems.forEach((item) => {
            const isExact =
                !!scanBarcode &&
                !!item.barcode_snapshot &&
                item.barcode_snapshot === scanBarcode &&
                item.max_returnable > 0;

            if (isExact && matchedId === null) {
                matchedId = item.id;
            }

            next[item.id] = {
                quantity: isExact ? '1' : '',
                condition: 'good',
                reason: '',
                expense_amount: '',
            };
        });

        if (matchedId === null && scanBarcode) {
            const fallback = saleItems.find((item) => item.max_returnable > 0);
            if (fallback) {
                matchedId = fallback.id;
                next[fallback.id] = { ...next[fallback.id], quantity: '1' };
            }
        }

        setItems(next);
        setMatchedSaleItemId(matchedId);
        if (scanBarcode) {
            setBarcode(scanBarcode);
        }
    }, [saleItems, scanBarcode]);

    const handleSaleChange = (saleId: string) => {
        setForm({ ...form, sale_id: saleId });
        setBarcodeError('');
        setMatchedSaleItemId(null);
        router.get('/sale-returns/create', { sale_id: saleId }, { preserveState: false, preserveScroll: true });
    };

    const lookupBarcode = async () => {
        const code = barcode.trim();
        if (!code || barcodeLoading) return;

        setBarcodeLoading(true);
        setBarcodeError('');

        try {
            const response = await fetch(`/sale-returns/lookup-barcode?barcode=${encodeURIComponent(code)}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();

            if (!data.found) {
                setBarcodeError(data.message || 'Barcode not found.');
                toast({ title: 'Not found', description: data.message || 'Barcode not found.', variant: 'destructive' });
                return;
            }

            toast({
                title: 'Sale found',
                description: `${data.invoice_number}${data.product ? ` · ${data.product}` : ''}`,
            });

            router.get(
                '/sale-returns/create',
                { sale_id: data.sale_id, barcode: data.barcode },
                { preserveState: false, preserveScroll: true },
            );
        } catch {
            setBarcodeError('Could not look up this barcode.');
            toast({ title: 'Error', description: 'Could not look up this barcode.', variant: 'destructive' });
        } finally {
            setBarcodeLoading(false);
            barcodeInputRef.current?.select();
        }
    };

    const handleBarcodeKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        void lookupBarcode();
    };

    const updateItem = (saleItemId: number, field: 'quantity' | 'condition' | 'reason' | 'expense_amount', value: string) => {
        setItems((prev) => ({
            ...prev,
            [saleItemId]: { ...(prev[saleItemId] || { quantity: '', condition: 'good', reason: '', expense_amount: '' }), [field]: value },
        }));
    };

    const totals = useMemo(() => {
        let subtotalAfn = 0;
        let expenseAfn = 0;
        let itemCount = 0;

        saleItems.forEach((item) => {
            const entry = items[item.id];
            if (!entry) return;
            const qty = parseFloat(entry.quantity || '0') || 0;
            if (qty > 0) {
                subtotalAfn += qty * item.sale_price_afn;
                expenseAfn += parseFloat(entry.expense_amount || '0') || 0;
                itemCount++;
            }
        });

        const previousDueAfn = selectedSale?.customer ? parseFloat(selectedSale.customer.current_due_afn || '0') : 0;

        const newDueAfn = Math.max(0, previousDueAfn - subtotalAfn);
        const refundAmountAfn = Math.max(0, subtotalAfn - previousDueAfn);

        return { subtotalAfn, expenseAfn, itemCount, previousDueAfn, newDueAfn, refundAmountAfn };
    }, [items, saleItems, selectedSale]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const itemsPayload = Object.entries(items)
            .filter(([_, entry]) => parseFloat(entry.quantity || '0') > 0)
            .map(([saleItemId, entry]) => ({
                sale_item_id: parseInt(saleItemId),
                quantity: parseInt(entry.quantity),
                condition: entry.condition,
                reason: entry.reason || null,
                expense_amount_afn: parseFloat(entry.expense_amount || '0') || 0,
            }));

        if (itemsPayload.length === 0) {
            toast({ title: 'Error', description: 'Select at least one item.', variant: 'destructive' });
            setLoading(false);
            return;
        }

        router.post(
            '/sale-returns',
            { ...form, items: itemsPayload },
            {
                onSuccess: () => {
                    toast({ title: 'Success', description: 'Return created successfully.' });
                    setLoading(false);
                },
                onError: (err) => {
                    setErrors(err as Record<string, string>);
                    setLoading(false);
                    toast({ title: 'Error', description: 'Please check the form.', variant: 'destructive' });
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Sale Return" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/sale-returns" label="Back" />
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 flex h-12 w-12 items-center justify-center rounded-full">
                            <RotateCcw className="text-primary h-6 w-6" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Create Sale Return</h1>
                            <p className="text-muted-foreground text-sm">Record a customer return</p>
                        </div>
                    </div>
                </div>

                {/* ═══════════ Step 1: Select Sale ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="h-5 w-5" /> Step 1: Select Sale
                        </CardTitle>
                        <CardDescription>Select the original sale.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-2 rounded-lg border border-primary/40 bg-primary/5 p-4">
                            <Label className="flex items-center gap-2 text-base font-semibold">
                                <ScanBarcode className="h-5 w-5" /> Scan barcode
                            </Label>
                            <p className="text-muted-foreground text-sm">
                                Scan or type the product barcode. The matching completed sale opens for return.
                            </p>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Input
                                    ref={barcodeInputRef}
                                    value={barcode}
                                    onChange={(e) => {
                                        setBarcode(e.target.value);
                                        setBarcodeError('');
                                    }}
                                    onKeyDown={handleBarcodeKeyDown}
                                    placeholder="Scan barcode..."
                                    autoFocus
                                    className="h-12 font-mono text-base"
                                />
                                <Button type="button" className="h-12 gap-2 sm:w-36" disabled={barcodeLoading || !barcode.trim()} onClick={() => void lookupBarcode()}>
                                    {barcodeLoading ? 'Looking...' : 'Find sale'}
                                </Button>
                            </div>
                            {barcodeError && <p className="text-destructive text-sm">{barcodeError}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label>
                                Sale <span className="text-destructive">*</span>
                            </Label>
                            <Select value={form.sale_id} onValueChange={handleSaleChange}>
                                <SelectTrigger className="max-w-md">
                                    <SelectValue placeholder="Select sale..." />
                                </SelectTrigger>
                                <SelectContent className="max-h-[300px]">
                                    {sales.map((sale) => (
                                        <SelectItem key={sale.id} value={String(sale.id)}>
                                            <div className="flex flex-col items-start">
                                                <span className="font-mono text-sm">{sale.invoice_number}</span>
                                                <span className="text-muted-foreground text-xs">
                                                    {formatPrice(sale.total_afn)} AFN · {new Date(sale.sale_date).toLocaleDateString('en-US')}
                                                </span>
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {selectedSale && (
                            <div className="bg-muted/30 grid grid-cols-1 gap-4 rounded-lg border p-4 md:grid-cols-3">
                                <div>
                                    <p className="text-muted-foreground text-xs">Customer</p>
                                    <p className="font-medium">{selectedSale.customer?.name || 'Walk-in'}</p>
                                    {selectedSale.customer?.phone && (
                                        <p className="text-muted-foreground text-xs" dir="ltr">
                                            {selectedSale.customer.phone}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs">Branch</p>
                                    <p className="font-medium">{selectedSale.branch?.name || '—'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs">Current Due</p>
                                    <p className="font-mono font-bold text-amber-700">
                                        {formatPrice(selectedSale.customer?.current_due_afn || 0)} AFN
                                    </p>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ═══════════ Step 2: Items ═══════════ */}
                {selectedSale && saleItems.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Step 2: Select Items & Quantities</CardTitle>
                            <CardDescription>Set the return quantity and item condition.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50">
                                        <tr>
                                            <th className="p-3 text-left text-xs font-semibold uppercase">Product</th>
                                            <th className="p-3 text-left text-xs font-semibold uppercase">Batch</th>
                                            <th className="p-3 text-center text-xs font-semibold uppercase">Sold</th>
                                            <th className="p-3 text-center text-xs font-semibold uppercase">Returned</th>
                                            <th className="p-3 text-center text-xs font-semibold uppercase">Returnable</th>
                                            <th className="p-3 text-center text-xs font-semibold uppercase">Return Qty</th>
                                            <th className="p-3 text-center text-xs font-semibold uppercase">Condition</th>
                                            <th className="p-3 text-right text-xs font-semibold uppercase">Item expense</th>
                                            <th className="p-3 text-right text-xs font-semibold uppercase">Line Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {saleItems.map((item) => {
                                            const entry = items[item.id] || { quantity: '', condition: 'good', reason: '', expense_amount: '' };
                                            const qty = parseFloat(entry.quantity || '0') || 0;
                                            const lineTotal = qty * item.sale_price_afn;
                                            const disabled = item.max_returnable <= 0;
                                            const isMatched = matchedSaleItemId === item.id;

                                            return (
                                                <tr
                                                    key={item.id}
                                                    className={`border-b ${disabled ? 'opacity-50' : ''} ${isMatched ? 'bg-primary/10' : ''}`}
                                                >
                                                    <td className="p-3">
                                                        <div className="font-medium">
                                                            {item.brand_name && <span className="text-muted-foreground">{item.brand_name} — </span>}
                                                            {item.product_name}
                                                        </div>
                                                        <div className="text-muted-foreground text-xs">
                                                            {[item.volume_ml ? `${item.volume_ml}ml` : null, item.concentration?.toUpperCase()]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        </div>
                                                        {item.barcode_snapshot && (
                                                            <div className="mt-0.5 font-mono text-[10px] text-muted-foreground">{item.barcode_snapshot}</div>
                                                        )}
                                                    </td>
                                                    <td className="p-3 font-mono text-xs">{item.batch_code || '—'}</td>
                                                    <td className="p-3 text-center font-mono">{item.quantity_sold}</td>
                                                    <td className="text-muted-foreground p-3 text-center font-mono">{item.already_returned}</td>
                                                    <td className="p-3 text-center">
                                                        <Badge variant="secondary">{item.max_returnable}</Badge>
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            max={item.max_returnable}
                                                            value={entry.quantity}
                                                            onChange={(e) => updateItem(item.id, 'quantity', e.target.value)}
                                                            disabled={disabled}
                                                            placeholder="0"
                                                            className="w-20 text-center font-mono"
                                                        />
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <Select
                                                            value={entry.condition}
                                                            onValueChange={(v) => updateItem(item.id, 'condition', v)}
                                                            disabled={disabled || qty <= 0}
                                                        >
                                                            <SelectTrigger className="w-[180px]">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {CONDITION_OPTIONS.map((opt) => (
                                                                    <SelectItem key={opt.value} value={opt.value}>
                                                                        <span className={opt.color}>{opt.label}</span>
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </td>
                                                    <td className="p-3 text-right">
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            value={entry.expense_amount}
                                                            onChange={(e) => updateItem(item.id, 'expense_amount', e.target.value)}
                                                            disabled={disabled || qty <= 0}
                                                            placeholder="0"
                                                            className="w-28 text-right font-mono"
                                                        />
                                                    </td>
                                                    <td className="p-3 text-right font-mono font-semibold">{formatPrice(lineTotal)}</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* ═══════════ Step 3: Info + Summary ═══════════ */}
                {selectedSale && (
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Step 3: Return Info</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label>
                                        Return Date <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        type="date"
                                        value={form.return_date}
                                        onChange={(e) => setForm({ ...form, return_date: e.target.value })}
                                        required
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label>Status</Label>
                                    <Select value={form.status} onValueChange={(v) => setForm({ ...form, status: v as any })}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="draft">Draft</SelectItem>
                                            <SelectItem value="completed">Completed</SelectItem>
                                            <SelectItem value="cancelled">Cancelled</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label>Reason for Return</Label>
                                    <Textarea
                                        value={form.reason}
                                        onChange={(e) => setForm({ ...form, reason: e.target.value })}
                                        rows={3}
                                        placeholder="Reason for the return..."
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-2 border-emerald-300">
                            <CardHeader>
                                <CardTitle>Summary</CardTitle>
                                <CardDescription>Due and refund calculation</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="bg-muted/30 rounded-lg border p-4">
                                    <div className="grid grid-cols-2 gap-y-2 text-sm">
                                        <span className="text-muted-foreground">Items Selected:</span>
                                        <span className="text-right font-mono font-semibold">{totals.itemCount}</span>

                                        <span className="text-muted-foreground">Return credit:</span>
                                        <span className="text-right font-mono font-semibold">{formatPrice(totals.subtotalAfn)} AFN</span>

                                        {totals.expenseAfn > 0 && (
                                            <>
                                                <span className="text-orange-700">Item expense:</span>
                                                <span className="text-right font-mono font-semibold text-orange-700">
                                                    {formatPrice(totals.expenseAfn)} AFN
                                                </span>
                                            </>
                                        )}

                                        <span className="col-span-2 my-1 border-t" />

                                        <span className="text-muted-foreground">Previous Due:</span>
                                        <span className="text-right font-mono font-semibold text-amber-700">
                                            {formatPrice(totals.previousDueAfn)} AFN
                                        </span>

                                        <span className="text-muted-foreground">After Return:</span>
                                        <span
                                            className={`text-right font-mono font-bold ${totals.newDueAfn > 0 ? 'text-red-600' : 'text-green-600'}`}
                                        >
                                            {formatPrice(totals.newDueAfn)} AFN
                                        </span>

                                        {totals.refundAmountAfn > 0 && (
                                            <>
                                                <span className="col-span-2 my-1 border-t" />
                                                <span className="font-bold text-red-600">Refund to Customer:</span>
                                                <span className="text-right font-mono font-bold text-red-600">
                                                    {formatPrice(totals.refundAmountAfn)} AFN
                                                </span>
                                            </>
                                        )}
                                    </div>
                                </div>

                                {totals.refundAmountAfn > 0 && (
                                    <div className="flex items-start gap-2 rounded border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800">
                                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                        <span>
                                            The refund is larger than the customer due. Return {formatPrice(totals.refundAmountAfn)} AFN to the customer.
                                        </span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* ═══════════ Errors ═══════════ */}
                {Object.keys(errors).length > 0 && (
                    <div className="rounded-lg border-2 border-red-400 bg-red-50 p-4">
                        <div className="mb-2 flex items-center gap-2">
                            <AlertCircle className="h-5 w-5 text-red-600" />
                            <p className="font-bold text-red-800">Errors</p>
                        </div>
                        <div className="space-y-1 text-xs">
                            {Object.entries(errors).map(([key, value]) => (
                                <div key={key} className="rounded bg-white/60 px-2 py-1">
                                    <span className="font-mono font-semibold text-red-700">{key}:</span> <span className="text-red-600">{value}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* ═══════════ Submit ═══════════ */}
                {selectedSale && (
                    <div className="flex gap-2">
                        <Button type="submit" disabled={loading} size="lg" className="gap-2">
                            <Save className="h-4 w-4" />
                            {loading ? 'Saving...' : 'Save Return'}
                        </Button>
                        <BackButton href="/sale-returns" label="Cancel" />
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
