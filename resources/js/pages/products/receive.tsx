import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { BackButton, FormActions } from '@/components/form-page';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface VariantOption {
    id: number;
    name: string;
    volume_ml: number | null;
    concentration: string | null;
    purchase_price_afn: string;
    sale_price_afn: string;
    stock_quantity: number;
    barcodes: string[];
}

interface InvestorOption {
    id: number;
    name: string;
}

interface SupplierOption {
    id: number;
    name: string;
}

interface Props {
    variants: VariantOption[];
    investors: InvestorOption[];
    suppliers: SupplierOption[];
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Products', href: '/products' },
    { title: 'Add stock', href: '/products/receive' },
];

export default function ProductReceive({ variants, investors, suppliers, errors = {} }: Props) {
    const [form, setForm] = useState({
        variant_id: variants[0] ? String(variants[0].id) : '',
        quantity: '1',
        purchase_price_afn: variants[0] ? String(variants[0].purchase_price_afn || '') : '',
        sale_price_afn: variants[0] ? String(variants[0].sale_price_afn || '') : '',
        investor_id: 'store',
        exchange_rate: '',
        profit_per_unit: '',
        purchase_date: new Date().toISOString().slice(0, 10),
        invoice_number: '',
        notes: '',
        supplier_id: '',
        paid_now: '',
    });
    const [list, setList] = useState(variants);
    const [scan, setScan] = useState('');
    const [scanMessage, setScanMessage] = useState('');
    const [scanning, setScanning] = useState(false);
    const [loading, setLoading] = useState(false);

    const selected = useMemo(() => list.find((v) => String(v.id) === form.variant_id), [list, form.variant_id]);
    const rate = parseFloat(form.exchange_rate) || 0;
    const profitAfn = parseFloat(form.profit_per_unit) || 0;
    const profitUsd = form.investor_id !== 'store' && rate > 0 ? profitAfn / rate : 0;

    const pickVariant = (id: string) => {
        const variant = list.find((v) => String(v.id) === id);
        setForm((prev) => ({
            ...prev,
            variant_id: id,
            purchase_price_afn: variant ? String(variant.purchase_price_afn || '') : prev.purchase_price_afn,
            sale_price_afn: variant ? String(variant.sale_price_afn || '') : prev.sale_price_afn,
        }));
    };

    const applyVariant = (variant: VariantOption) => {
        setList((prev) => (prev.some((item) => item.id === variant.id) ? prev : [variant, ...prev]));
        setForm((prev) => ({
            ...prev,
            variant_id: String(variant.id),
            purchase_price_afn: String(variant.purchase_price_afn || ''),
            sale_price_afn: String(variant.sale_price_afn || ''),
        }));
        setScanMessage(`${variant.name} loaded from the database.`);
        setScan('');
    };

    const scanBarcode = async () => {
        const code = scan.trim();
        if (!code || scanning) return;
        setScanning(true);
        setScanMessage('');
        try {
            const response = await fetch(`/products/receive/lookup-barcode?barcode=${encodeURIComponent(code)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!data.found || !data.variant) {
                setScanMessage(data.message || 'No product matches this barcode.');
                return;
            }
            applyVariant(data.variant);
        } catch {
            setScanMessage('Could not look up this barcode.');
        } finally {
            setScanning(false);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const purchasePrice = parseFloat(form.purchase_price_afn) || 0;
        const salePrice = parseFloat(form.sale_price_afn) || 0;
        if (purchasePrice < 0 || salePrice < 0 || profitAfn < 0) {
            setScanMessage('Prices cannot be negative.');
            return;
        }
        if (salePrice < purchasePrice) {
            setScanMessage('Sale price cannot be less than the purchase price.');
            return;
        }
        if (form.investor_id !== 'store' && profitAfn > 0 && rate <= 0) {
            setScanMessage('Enter the USD to AFN rate so investor profit is saved in dollars.');
            return;
        }
        setLoading(true);
        router.post('/products/receive', form, {
            onFinish: () => setLoading(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add stock" />
            <form onSubmit={submit} className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/products" />
                    <h1 className="text-2xl font-bold tracking-tight">Add stock</h1>
                </div>

                <div className="rounded-xl border-2 border-primary bg-primary/5 p-5">
                    <h2 className="text-lg font-semibold">Scan barcode</h2>
                    <p className="text-muted-foreground mb-3 text-sm">Looks up the product in the database.</p>
                    <div className="flex gap-2">
                        <Input
                            value={scan}
                            onChange={(e) => setScan(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    scanBarcode();
                                }
                            }}
                            placeholder="Scan or type, then Enter"
                            className="h-12 bg-background font-mono text-lg"
                            autoFocus
                        />
                        <Button type="button" className="h-12 px-6" onClick={scanBarcode} disabled={scanning}>
                            {scanning ? 'Looking up…' : 'Find'}
                        </Button>
                    </div>
                    {scanMessage && <p className="mt-2 text-sm font-medium">{scanMessage}</p>}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Product</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-2">
                            <Label>Product</Label>
                            <Select value={form.variant_id} onValueChange={pickVariant}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select product" />
                                </SelectTrigger>
                                <SelectContent>
                                    {list.map((variant) => (
                                        <SelectItem key={variant.id} value={String(variant.id)}>
                                            {variant.name}
                                            {variant.volume_ml ? ` · ${variant.volume_ml}ml` : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.variant_id && <p className="text-destructive text-xs">{errors.variant_id}</p>}
                        </div>

                        {selected && (
                            <div className="grid grid-cols-2 gap-3 rounded-lg border border-primary/40 bg-primary/5 p-4 md:grid-cols-4">
                                <div>
                                    <p className="text-muted-foreground text-xs">Name</p>
                                    <p className="text-sm font-medium">{selected.name}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs">Volume</p>
                                    <p className="text-sm font-medium">{selected.volume_ml ? `${selected.volume_ml} ml` : '—'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs">Concentration</p>
                                    <p className="text-sm font-medium">{selected.concentration ? selected.concentration.toUpperCase() : '—'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs">Current stock</p>
                                    <p className="text-sm font-medium">
                                        {selected.stock_quantity}
                                        <span className="text-muted-foreground"> → {selected.stock_quantity + (parseInt(form.quantity, 10) || 0)}</span>
                                    </p>
                                </div>
                                {selected.barcodes.length > 0 && (
                                    <div className="col-span-2 md:col-span-4">
                                        <p className="text-muted-foreground text-xs">Barcodes</p>
                                        <p className="font-mono text-sm">{selected.barcodes.join(' · ')}</p>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="rounded-lg border p-4">
                    <h2 className="mb-3 text-sm font-semibold">Quantity</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Quantity</Label>
                            <Input type="number" min="1" value={form.quantity} onChange={(e) => setForm({ ...form, quantity: e.target.value })} required />
                        </div>
                        <div className="space-y-2">
                            <Label>Owner</Label>
                            <Select value={form.investor_id} onValueChange={(v) => setForm({ ...form, investor_id: v })}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="store">Shop</SelectItem>
                                    {investors.map((inv) => (
                                        <SelectItem key={inv.id} value={String(inv.id)}>{inv.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                <div className="rounded-lg border p-4">
                    <h2 className="mb-3 text-sm font-semibold">Prices</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Purchase price (AFN)</Label>
                            <Input type="number" min="0" step="0.01" value={form.purchase_price_afn} onChange={(e) => setForm({ ...form, purchase_price_afn: e.target.value })} required />
                        </div>
                        <div className="space-y-2">
                            <Label>Sale price (AFN)</Label>
                            <Input type="number" min="0" step="0.01" value={form.sale_price_afn} onChange={(e) => setForm({ ...form, sale_price_afn: e.target.value })} required />
                        </div>
                        {form.investor_id !== 'store' && (
                            <div className="space-y-2">
                                <Label>Investor profit / unit (AFN)</Label>
                                <Input type="number" min="0" step="0.01" value={form.profit_per_unit} onChange={(e) => setForm({ ...form, profit_per_unit: e.target.value })} />
                                <p className="text-muted-foreground text-xs">
                                    {profitUsd > 0 ? `${profitUsd.toFixed(2)} USD will be saved on the investor account.` : 'Enter the rate to convert this profit to dollars.'}
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                <div className="rounded-lg border p-4">
                    <h2 className="mb-3 text-sm font-semibold">Bill</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Invoice number</Label>
                            <Input value={form.invoice_number} onChange={(e) => setForm({ ...form, invoice_number: e.target.value })} />
                        </div>
                        <div className="space-y-2">
                            <Label>Date</Label>
                            <Input type="date" value={form.purchase_date} onChange={(e) => setForm({ ...form, purchase_date: e.target.value })} required />
                        </div>
                        <div className="space-y-2">
                            <Label>Supplier</Label>
                            <Select value={form.supplier_id || 'none'} onValueChange={(v) => setForm({ ...form, supplier_id: v === 'none' ? '' : v })}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Supplier" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">No supplier</SelectItem>
                                    {suppliers.map((supplier) => (
                                        <SelectItem key={supplier.id} value={String(supplier.id)}>{supplier.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Notes</Label>
                            <Textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} rows={2} />
                        </div>
                    </div>
                </div>

                <div className="rounded-lg border p-4">
                    <h2 className="mb-3 text-sm font-semibold">USD to AFN</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Exchange rate (USD to AFN)</Label>
                            <Input type="number" min="0" step="0.01" value={form.exchange_rate} onChange={(e) => setForm({ ...form, exchange_rate: e.target.value })} placeholder="How many AFN for 1 USD" />
                        </div>
                        {form.investor_id !== 'store' && (
                            <div className="space-y-2">
                                <Label>Saved on the investor account</Label>
                                <p className="text-sm font-medium">
                                    {profitUsd > 0 ? `${profitUsd.toFixed(2)} USD per unit` : 'Enter the rate to convert investor profit to dollars.'}
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                <FormActions backHref="/products" submitLabel="Add stock" loading={loading} />
            </form>
        </AppLayout>
    );
}
