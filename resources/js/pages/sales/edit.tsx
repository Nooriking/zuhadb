// resources/js/pages/sales/edit.tsx

import { CustomerCombobox } from '@/components/customer-combobox';
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
import { AlertTriangle, ArrowLeft, Plus, Save, ShoppingBag, Trash2, UserPlus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Interfaces
 ═══════════════════════════════════════════════════════ */

interface Branch {
    id: number;
    name: string;
    code: string;
}
interface Customer {
    id: number;
    name: string;
    phone: string | null;
    address?: string | null;
}
interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
}
interface Variant {
    id: number;
    product_id: number;
    name: string | null;
    volume_ml: number | null;
    concentration: string | null;
    sale_price_afn: string;
    wholesale_price_afn: string;
    wholesale_min_qty: number;
    purchase_price_afn: string;
    product: {
        id: number;
        name: string;
        brand: { id: number; name: string } | null;
    } | null;
}

interface LineItem {
    _key: string;
    variant_id: string;
    quantity: string;
    sale_price_currency: string;
    discount_currency: string;
}

interface Sale {
    id: number;
    invoice_number: string;
    branch_id: number;
    customer_id: number | null;
    currency_id: number;
    exchange_rate: string;
    sale_date: string;
    payment_status: 'unpaid' | 'partial' | 'paid';
    status: 'draft' | 'completed' | 'cancelled';
    notes: string | null;
    discount_currency: string;
    tax_currency: string;
    customer_name_snapshot: string | null;
    customer_phone_snapshot: string | null;
    items: {
        id: number;
        variant_id: number;
        quantity: number;
        sale_price_currency: string;
        discount_currency: string;
    }[];
}

interface Props {
    sale: Sale;
    branches: Branch[];
    customers: Customer[];
    currencies: Currency[];
    variants: Variant[];
    errors?: Record<string, string>;
}

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const newLine = (): LineItem => ({
    _key: Math.random().toString(36).slice(2),
    variant_id: '',
    quantity: '1',
    sale_price_currency: '',
    discount_currency: '0',
});

const breadcrumbs = (sale: Sale): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales', href: '/sales' },
    { title: sale.invoice_number, href: `/sales/${sale.id}` },
    { title: 'Edit', href: `/sales/${sale.id}/edit` },
];

/**
 * Build a clean display label for a variant
 */
function variantLabel(v: Variant): string {
    const parts: string[] = [];

    if (v.product?.brand?.name) parts.push(v.product.brand.name);
    if (v.product?.name) parts.push(v.product.name);

    let spec = '';
    if (v.volume_ml) spec += ` · ${v.volume_ml}ml`;
    if (v.concentration) spec += ` · ${v.concentration.toUpperCase()}`;

    return (parts.join(' — ') || `Variant #${v.id}`) + spec;
}

/* ═══════════════════════════════════════════════════════
 |  Main Component
 ═══════════════════════════════════════════════════════ */

export default function SalesEdit({ sale, branches, customers, variants, errors: initialErrors }: Props) {
    const { toast } = useToast();

    /* ─────────────── Form State ─────────────── */
    const [form, setForm] = useState({
        branch_id: String(sale.branch_id),
        customer_id: sale.customer_id ? String(sale.customer_id) : 'none',
        exchange_rate: '1',
        sale_date: sale.sale_date,
        payment_status: sale.payment_status,
        status: sale.status,
        notes: sale.notes || '',
        discount_currency: sale.discount_currency || '0',
        tax_currency: sale.tax_currency || '0',
    });

    /* ─────────────── Customer Fields ─────────────── */
    const [customerData, setCustomerData] = useState({
        name: sale.customer_name_snapshot || '',
        phone: sale.customer_phone_snapshot || '',
        address: '',
        notes: '',
    });

    /* ─────────────── Items ─────────────── */
    const [items, setItems] = useState<LineItem[]>(
        sale.items.length > 0
            ? sale.items.map((it) => ({
                  _key: Math.random().toString(36).slice(2),
                  variant_id: String(it.variant_id),
                  quantity: String(it.quantity),
                  sale_price_currency: (it as { sale_price_afn?: string }).sale_price_afn || it.sale_price_currency || '0',
                  discount_currency: (it as { discount_afn?: string }).discount_afn || it.discount_currency || '0',
              }))
            : [newLine()],
    );

    /* ─────────────── States ─────────────── */
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const isNewCustomer = form.customer_id === 'new';
    const isCompleted = sale.status === 'completed';

    /* ─────────────── Effect: fill customer fields ─────────────── */
    useEffect(() => {
        if (form.customer_id === 'new' || form.customer_id === 'none') {
            return;
        }

        const selected = customers.find((c) => String(c.id) === form.customer_id);
        if (selected) {
            setCustomerData({
                name: selected.name || '',
                phone: selected.phone || '',
                address: selected.address || '',
                notes: '',
            });
        }
    }, [form.customer_id, customers]);

    /* ─────────────── Computed Totals ─────────────── */
    const totals = useMemo(() => {
        const exchangeRate = parseFloat(form.exchange_rate) || 0;
        let subtotalCurrency = 0;
        let itemCount = 0;
        let totalQty = 0;

        items.forEach((item) => {
            if (!item.variant_id) return;
            const qty = parseFloat(item.quantity) || 0;
            const price = parseFloat(item.sale_price_currency) || 0;
            const disc = parseFloat(item.discount_currency) || 0;
            subtotalCurrency += qty * price - disc;
            itemCount++;
            totalQty += qty;
        });

        const discount = parseFloat(form.discount_currency) || 0;
        const tax = parseFloat(form.tax_currency) || 0;
        const totalCurrency = subtotalCurrency - discount + tax;

        return {
            subtotalCurrency,
            discount,
            tax,
            totalCurrency,
            subtotalAfn: subtotalCurrency * exchangeRate,
            totalAfn: totalCurrency * exchangeRate,
            itemCount,
            totalQty,
        };
    }, [items, form]);

    /* ═══════════════════════════════════════════════════════
     |  Item Handlers
     ═══════════════════════════════════════════════════════ */

    const updateItem = (index: number, field: keyof LineItem, value: string) => {
        setItems((prev) => {
            const copy = [...prev];
            copy[index] = { ...copy[index], [field]: value };

            if (field === 'variant_id' && value) {
                const variant = variants.find((v) => v.id === parseInt(value));
                if (variant) {
                    copy[index].sale_price_currency = variant.sale_price_afn || '0';
                }
            }

            return copy;
        });
    };

    const addItem = () => setItems((prev) => [...prev, newLine()]);

    const removeItem = (index: number) => {
        if (items.length === 1) {
            setItems([newLine()]);
            return;
        }
        setItems((prev) => prev.filter((_, i) => i !== index));
    };

    /* ═══════════════════════════════════════════════════════
     |  Submit
     ═══════════════════════════════════════════════════════ */

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const underCost = items.find((item) => {
            const variant = variants.find((row) => String(row.id) === item.variant_id);
            const price = parseFloat(item.sale_price_currency) || 0;
            const cost = parseFloat(variant?.purchase_price_afn || '0') || 0;
            return item.variant_id && (price < 0 || price < cost);
        });
        if (underCost) {
            setErrors({ items: 'Sale price cannot be negative or less than the purchase price.' });
            toast({ title: 'Price', description: 'Sale price cannot be negative or less than the purchase price.', variant: 'destructive' });
            setLoading(false);
            return;
        }

        if (items.every((it) => !it.variant_id)) {
            setErrors({ items: 'At least one product must be added.' });
            toast({ title: 'Error', description: 'Add at least one product.', variant: 'destructive' });
            setLoading(false);
            return;
        }

        const payload = {
            ...form,
            customer_id: form.customer_id === 'none' || form.customer_id === 'new' ? '' : form.customer_id,
            discount_afn: parseFloat(form.discount_currency) || 0,
            items: items
                .filter((it) => it.variant_id)
                .map((item) => ({
                    variant_id: parseInt(item.variant_id),
                    quantity: parseInt(item.quantity) || 1,
                    sale_price_afn: parseFloat(item.sale_price_currency) || 0,
                    discount_afn: parseFloat(item.discount_currency) || 0,
                })),
        };

        router.put(`/sales/${sale.id}`, payload, {
            onSuccess: () => {
                toast({ title: 'Success', description: 'Sale updated successfully.' });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err as Record<string, string>);
                setLoading(false);
                toast({ title: 'Error', description: 'Please check the form fields.', variant: 'destructive' });
            },
        });
    };

    const formatPrice = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(value);

    /* ═══════════════════════════════════════════════════════
     |  Render
     ═══════════════════════════════════════════════════════ */

    return (
        <AppLayout breadcrumbs={breadcrumbs(sale)}>
            <Head title={`Edit ${sale.invoice_number}`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <ShoppingBag className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Edit Sale</h1>
                        <span className="text-muted-foreground font-mono text-sm">{sale.invoice_number}</span>
                    </div>
                    <Link href={`/sales/${sale.id}`}>
                        <Button variant="outline" type="button" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Button>
                    </Link>
                </div>

                {/* ═══════════ Completed Warning ═══════════ */}
                {isCompleted && (
                    <div className="flex items-start gap-3 rounded-lg border-2 border-yellow-400 bg-yellow-50 p-4">
                        <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-yellow-600" />
                        <div className="text-sm">
                            <p className="font-semibold text-yellow-800">This sale is already completed.</p>
                            <p className="mt-0.5 text-yellow-700">Editing is not allowed. Create a new sale if needed.</p>
                        </div>
                    </div>
                )}

                {/* ═══════════════════════════════════════════════════
                 |  📋 SALE INFORMATION
                 ═══════════════════════════════════════════════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle>Sale Information</CardTitle>
                        <CardDescription>Customer, branch, currency and date.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {branches.length > 1 && (
                            <div className="space-y-2">
                                <Label>
                                    Branch <span className="text-destructive">*</span>
                                </Label>
                                <Select value={form.branch_id} onValueChange={(v) => setForm({ ...form, branch_id: v })}>
                                    <SelectTrigger className={errors.branch_id ? 'border-destructive' : ''}>
                                        <SelectValue placeholder="Select branch" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {branches.map((b) => (
                                            <SelectItem key={b.id} value={String(b.id)}>
                                                {b.name} ({b.code})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.branch_id && <p className="text-destructive text-sm">{errors.branch_id}</p>}
                            </div>
                            )}

                            <div className="space-y-2 md:col-span-2">
                                <Label>Customer</Label>
                                <CustomerCombobox
                                    customers={customers}
                                    value={form.customer_id}
                                    onChange={(v) => setForm({ ...form, customer_id: v })}
                                    error={!!errors.customer_id}
                                />
                                {errors.customer_id && <p className="text-destructive text-sm">{errors.customer_id}</p>}
                            </div>
                        </div>

                        {form.customer_id !== 'none' && (
                            <div className="space-y-4 rounded-lg border bg-gray-50/50 p-4">
                                <div className="mb-2 flex items-center gap-2">
                                    <UserPlus className="text-primary h-4 w-4" />
                                    <h3 className="text-sm font-semibold">{isNewCustomer ? 'New Customer Information' : 'Customer Information'}</h3>
                                    {isNewCustomer && (
                                        <span className="bg-primary/10 text-primary ml-auto rounded px-2 py-0.5 text-xs">Will be created</span>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>
                                            Name <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            value={customerData.name}
                                            onChange={(e) => setCustomerData({ ...customerData, name: e.target.value })}
                                            placeholder="Customer name"
                                            disabled={!isNewCustomer}
                                            className={errors['customer.name'] ? 'border-destructive' : ''}
                                        />
                                        {errors['customer.name'] && <p className="text-destructive text-sm">{errors['customer.name']}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Phone</Label>
                                        <Input
                                            value={customerData.phone}
                                            onChange={(e) => setCustomerData({ ...customerData, phone: e.target.value })}
                                            placeholder="07XX XXX XXX"
                                            disabled={!isNewCustomer}
                                        />
                                    </div>

                                    <div className="space-y-2 md:col-span-2">
                                        <Label>Address</Label>
                                        <Input
                                            value={customerData.address}
                                            onChange={(e) => setCustomerData({ ...customerData, address: e.target.value })}
                                            placeholder="Street, City..."
                                            disabled={!isNewCustomer}
                                        />
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label>
                                    Sale Date <span className="text-destructive">*</span>
                                </Label>
                                <Input type="date" value={form.sale_date} onChange={(e) => setForm({ ...form, sale_date: e.target.value })} />
                                {errors.sale_date && <p className="text-destructive text-sm">{errors.sale_date}</p>}
                            </div>

                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                                <Label>Notes</Label>
                                <Textarea
                                    value={form.notes}
                                    onChange={(e) => setForm({ ...form, notes: e.target.value })}
                                    rows={1}
                                    placeholder="Optional notes..."
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════════════════════════════════════════════
                 |  📦 ITEMS
                 ═══════════════════════════════════════════════════ */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Items</CardTitle>
                            <CardDescription>Products in this sale.</CardDescription>
                        </div>
                        <Button type="button" onClick={addItem} className="gap-2">
                            <Plus className="h-4 w-4" /> Add Item
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {errors.items && <p className="text-destructive text-sm">{errors.items}</p>}

                        {items.map((item, index) => {
                            const qty = parseFloat(item.quantity) || 0;
                            const price = parseFloat(item.sale_price_currency) || 0;
                            const discount = parseFloat(item.discount_currency) || 0;
                            const lineTotal = qty * price - discount;

                            return (
                                <div key={item._key} className="rounded-lg border p-3">
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-12">
                                        <div className="space-y-2 md:col-span-5">
                                            <Label className="text-xs">Product Variant</Label>
                                            <Select value={item.variant_id} onValueChange={(v) => updateItem(index, 'variant_id', v)}>
                                                <SelectTrigger className={errors[`items.${index}.variant_id`] ? 'border-destructive' : ''}>
                                                    <SelectValue placeholder="Select variant">
                                                        {item.variant_id &&
                                                            (() => {
                                                                const v = variants.find((x) => String(x.id) === item.variant_id);
                                                                return v ? <span className="truncate">{variantLabel(v)}</span> : 'Select variant';
                                                            })()}
                                                    </SelectValue>
                                                </SelectTrigger>
                                                <SelectContent className="max-h-[320px]">
                                                    {variants.map((v) => (
                                                        <SelectItem key={v.id} value={String(v.id)}>
                                                            <div className="flex flex-col items-start gap-0.5">
                                                                <span className="font-medium">
                                                                    {v.product?.brand?.name ? `${v.product.brand.name} — ` : ''}
                                                                    {v.product?.name ?? '—'}
                                                                </span>
                                                                <span className="text-muted-foreground text-xs">
                                                                    {[
                                                                        v.volume_ml ? `${v.volume_ml}ml` : null,
                                                                        v.concentration ? v.concentration.toUpperCase() : null,
                                                                        v.name,
                                                                    ]
                                                                        .filter(Boolean)
                                                                        .join(' · ') || `Variant #${v.id}`}
                                                                </span>
                                                            </div>
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2 md:col-span-2">
                                            <Label className="text-xs">Qty</Label>
                                            <Input
                                                type="number"
                                                min="1"
                                                value={item.quantity}
                                                onChange={(e) => updateItem(index, 'quantity', e.target.value)}
                                                className="font-mono"
                                            />
                                        </div>

                                        <div className="space-y-2 md:col-span-2">
                                            <Label className="text-xs">Sale Price</Label>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                value={item.sale_price_currency}
                                                onChange={(e) => updateItem(index, 'sale_price_currency', e.target.value)}
                                                className="font-mono"
                                            />
                                        </div>

                                        <div className="space-y-2 md:col-span-2">
                                            <Label className="text-xs">Discount</Label>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={item.discount_currency}
                                                onChange={(e) => updateItem(index, 'discount_currency', e.target.value)}
                                                className="font-mono"
                                            />
                                        </div>

                                        <div className="flex items-end justify-end gap-1 md:col-span-1">
                                            <div className="flex-1 rounded border bg-gray-50 px-2 py-1.5 text-center font-mono text-xs font-semibold">
                                                {formatPrice(lineTotal)}
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removeItem(index)}
                                                className="text-destructive"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                {/* ═══════════════════════════════════════════════════
                 |  💰 TOTALS
                 ═══════════════════════════════════════════════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle>Totals</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* Quick summary bar */}
                        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                            <div className="rounded-lg border bg-gray-50 p-3 text-center">
                                <p className="text-muted-foreground text-xs font-medium uppercase">Unique Items</p>
                                <p className="text-2xl font-bold">{totals.itemCount}</p>
                            </div>
                            <div className="rounded-lg border bg-gray-50 p-3 text-center">
                                <p className="text-muted-foreground text-xs font-medium uppercase">Total Qty</p>
                                <p className="text-2xl font-bold">{totals.totalQty}</p>
                            </div>
                            <div className="rounded-lg border bg-gray-50 p-3 text-center md:col-span-2">
                                <p className="text-muted-foreground text-xs font-medium uppercase">Subtotal</p>
                                <p className="font-mono text-2xl font-bold">{formatPrice(totals.subtotalCurrency)}</p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label>Header Discount</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.discount_currency}
                                    onChange={(e) => setForm({ ...form, discount_currency: e.target.value })}
                                    className="font-mono"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Tax</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.tax_currency}
                                    onChange={(e) => setForm({ ...form, tax_currency: e.target.value })}
                                    className="font-mono"
                                />
                            </div>
                        </div>

                        <div className="rounded-lg border bg-gray-50 p-4">
                            <div className="grid grid-cols-2 gap-y-2 text-sm">
                                <span className="text-gray-500">Subtotal:</span>
                                <span className="text-right font-mono">{formatPrice(totals.subtotalCurrency)}</span>
                                <span className="text-gray-500">Discount:</span>
                                <span className="text-right font-mono text-red-600">-{formatPrice(totals.discount)}</span>
                                <span className="text-gray-500">Tax:</span>
                                <span className="text-right font-mono">+{formatPrice(totals.tax)}</span>
                                <span className="col-span-2 my-1 border-t" />
                                <span className="font-bold">Total:</span>
                                <span className="text-right font-mono text-base font-bold">{formatPrice(totals.totalCurrency)}</span>
                                <span className="col-span-2 my-2 border-t-2" />
                                <span className="text-gray-500">Total (AFN):</span>
                                <span className="text-right font-mono font-semibold">{formatPrice(totals.totalAfn)} AFN</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ Actions ═══════════ */}
                <div className="flex gap-2">
                    <Button type="submit" disabled={loading || isCompleted} className="gap-2">
                        <Save className="h-4 w-4" />
                        {loading ? 'Updating...' : 'Update Sale'}
                    </Button>
                    <Link href={`/sales/${sale.id}`}>
                        <Button variant="outline" type="button">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
