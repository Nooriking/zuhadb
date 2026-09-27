import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { BackButton, FormActions } from '@/components/form-page';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Building2, CircleDollarSign, Landmark, TrendingUp } from 'lucide-react';
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

interface InvestmentOption {
    id: number;
    investment_number: string;
    investor_id: number;
    investor_name: string | null;
    available_amount_afn: string;
    capital_amount_afn: string;
    status: string;
}

interface SupplierOption {
    id: number;
    name: string;
}

interface Props {
    variants: VariantOption[];
    investors: InvestorOption[];
    investments?: InvestmentOption[];
    suppliers: SupplierOption[];
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Products', href: '/products' },
    { title: 'Add stock', href: '/products/receive' },
];

const money = (value: number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(value || 0);

export default function ProductReceive({
    variants,
    investors,
    investments = [],
    suppliers,
    errors = {},
}: Props) {
    const [form, setForm] = useState({
        variant_id: variants[0] ? String(variants[0].id) : '',
        quantity: '1',
        purchase_price_afn: variants[0] ? String(variants[0].purchase_price_afn || '') : '',
        sale_price_afn: variants[0] ? String(variants[0].sale_price_afn || '') : '',
        funding_source: 'office' as 'office' | 'investor',
        investor_id: '',
        investment_id: '',
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
    const isInvestor = form.funding_source === 'investor';
    const rate = parseFloat(form.exchange_rate) || 0;
    const qty = parseInt(form.quantity, 10) || 0;
    const purchasePrice = parseFloat(form.purchase_price_afn) || 0;
    const profitAfn = parseFloat(form.profit_per_unit) || 0;
    const profitUsd = isInvestor && rate > 0 ? profitAfn / rate : 0;
    const totalCostAfn = purchasePrice * qty;
    const totalCostUsd = rate > 0 ? totalCostAfn / rate : 0;
    const totalProfitUsd = profitUsd * qty;

    const investorInvestments = useMemo(() => {
        if (!form.investor_id) return [];
        return investments.filter((row) => String(row.investor_id) === form.investor_id);
    }, [investments, form.investor_id]);

    const selectedInvestment = useMemo(
        () => investments.find((row) => String(row.id) === form.investment_id),
        [investments, form.investment_id],
    );

    const availableUsd = selectedInvestment ? parseFloat(selectedInvestment.available_amount_afn) || 0 : 0;
    const remainingAfter = availableUsd - totalCostUsd;

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

    const setFundingSource = (source: 'office' | 'investor') => {
        setForm((prev) => ({
            ...prev,
            funding_source: source,
            investor_id: source === 'office' ? '' : prev.investor_id,
            investment_id: source === 'office' ? '' : prev.investment_id,
            profit_per_unit: source === 'office' ? '' : prev.profit_per_unit,
        }));
    };

    const setInvestor = (investorId: string) => {
        const firstInvestment = investments.find((row) => String(row.investor_id) === investorId);
        setForm((prev) => ({
            ...prev,
            investor_id: investorId,
            investment_id: firstInvestment ? String(firstInvestment.id) : '',
        }));
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
        const salePrice = parseFloat(form.sale_price_afn) || 0;

        if (purchasePrice < 0 || salePrice < 0 || profitAfn < 0) {
            setScanMessage('Prices cannot be negative.');
            return;
        }
        if (salePrice < purchasePrice) {
            setScanMessage('Sale price cannot be less than the purchase price.');
            return;
        }
        if (isInvestor) {
            if (!form.investor_id) {
                setScanMessage('Select an investor.');
                return;
            }
            if (!form.investment_id) {
                setScanMessage('Select which investment capital pays for this stock.');
                return;
            }
            if (rate <= 0) {
                setScanMessage('Enter the USD to AFN rate for capital and investor profit.');
                return;
            }
            if (totalCostUsd > availableUsd + 0.01) {
                setScanMessage(`This stock costs ${money(totalCostUsd)} USD but only ${money(availableUsd)} USD is available.`);
                return;
            }
        }

        setLoading(true);
        router.post(
            '/products/receive',
            {
                ...form,
                investor_id: isInvestor ? form.investor_id : 'store',
                investment_id: isInvestor ? form.investment_id : '',
                profit_per_unit: isInvestor ? form.profit_per_unit : '0',
            },
            {
                onFinish: () => setLoading(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add stock" />
            <form onSubmit={submit} className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/products" />
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Add stock</h1>
                        <p className="text-muted-foreground text-sm">Choose funding source, then receive inventory</p>
                    </div>
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
                                    void scanBarcode();
                                }
                            }}
                            placeholder="Scan or type, then Enter"
                            className="h-12 bg-background font-mono text-lg"
                            autoFocus
                        />
                        <Button type="button" className="h-12 px-6" onClick={() => void scanBarcode()} disabled={scanning}>
                            {scanning ? 'Looking up…' : 'Find'}
                        </Button>
                    </div>
                    {scanMessage && <p className="mt-2 text-sm font-medium">{scanMessage}</p>}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Landmark className="h-5 w-5" /> Funding source
                        </CardTitle>
                        <CardDescription>
                            Office money stays with the shop. Investor capital is deducted from an active investment and
                            investor profit is tracked per unit.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <button
                                type="button"
                                onClick={() => setFundingSource('office')}
                                className={`rounded-xl border-2 p-4 text-left transition ${
                                    !isInvestor ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'
                                }`}
                            >
                                <div className="mb-2 flex items-center gap-2 font-semibold">
                                    <Building2 className="h-5 w-5" /> Office money
                                </div>
                                <p className="text-muted-foreground text-sm">
                                    Paid from shop / company cash. No investor capital or profit share.
                                </p>
                            </button>
                            <button
                                type="button"
                                onClick={() => setFundingSource('investor')}
                                className={`rounded-xl border-2 p-4 text-left transition ${
                                    isInvestor ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'
                                }`}
                            >
                                <div className="mb-2 flex items-center gap-2 font-semibold">
                                    <CircleDollarSign className="h-5 w-5" /> Investor capital
                                </div>
                                <p className="text-muted-foreground text-sm">
                                    Uses an investment pot. Capital is allocated and profit / unit is owed to the investor.
                                </p>
                            </button>
                        </div>
                        {errors.funding_source && <p className="text-destructive text-xs">{errors.funding_source}</p>}

                        {isInvestor && (
                            <div className="space-y-4 rounded-lg border border-emerald-300 bg-emerald-50/50 p-4 dark:bg-emerald-950/20">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>
                                            Investor <span className="text-destructive">*</span>
                                        </Label>
                                        <Select value={form.investor_id} onValueChange={setInvestor}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select investor" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {investors.map((inv) => (
                                                    <SelectItem key={inv.id} value={String(inv.id)}>
                                                        {inv.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.investor_id && <p className="text-destructive text-xs">{errors.investor_id}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>
                                            Investment (capital pot) <span className="text-destructive">*</span>
                                        </Label>
                                        <Select
                                            value={form.investment_id}
                                            onValueChange={(v) => setForm({ ...form, investment_id: v })}
                                            disabled={!form.investor_id}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder={form.investor_id ? 'Select investment' : 'Pick investor first'} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {investorInvestments.length === 0 ? (
                                                    <div className="text-muted-foreground px-3 py-4 text-center text-sm">
                                                        No available investment capital for this investor
                                                    </div>
                                                ) : (
                                                    investorInvestments.map((row) => (
                                                        <SelectItem key={row.id} value={String(row.id)}>
                                                            <div className="flex flex-col items-start py-0.5">
                                                                <span className="font-mono text-sm">{row.investment_number}</span>
                                                                <span className="text-muted-foreground text-xs">
                                                                    Available {money(parseFloat(row.available_amount_afn))} USD
                                                                </span>
                                                            </div>
                                                        </SelectItem>
                                                    ))
                                                )}
                                            </SelectContent>
                                        </Select>
                                        {errors.investment_id && <p className="text-destructive text-xs">{errors.investment_id}</p>}
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label className="flex items-center gap-2">
                                            <TrendingUp className="h-4 w-4" /> Investor profit / unit (AFN)
                                        </Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={form.profit_per_unit}
                                            onChange={(e) => setForm({ ...form, profit_per_unit: e.target.value })}
                                            placeholder="0"
                                        />
                                        <p className="text-muted-foreground text-xs">
                                            {profitUsd > 0
                                                ? `${money(profitUsd)} USD per unit will be owed when this stock sells.`
                                                : 'Enter profit in AFN; it is converted with the rate below.'}
                                        </p>
                                        {errors.profit_per_unit && <p className="text-destructive text-xs">{errors.profit_per_unit}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>
                                            Exchange rate (USD → AFN) <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={form.exchange_rate}
                                            onChange={(e) => setForm({ ...form, exchange_rate: e.target.value })}
                                            placeholder="How many AFN for 1 USD"
                                            required={isInvestor}
                                        />
                                        {errors.exchange_rate && <p className="text-destructive text-xs">{errors.exchange_rate}</p>}
                                    </div>
                                </div>

                                {selectedInvestment && rate > 0 && qty > 0 && (
                                    <div className="grid gap-3 rounded-lg border bg-background p-3 text-sm sm:grid-cols-4">
                                        <div>
                                            <p className="text-muted-foreground text-xs">Stock cost</p>
                                            <p className="font-mono font-semibold">{money(totalCostAfn)} AFN</p>
                                            <p className="text-muted-foreground font-mono text-xs">{money(totalCostUsd)} USD</p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-xs">Available now</p>
                                            <p className="font-mono font-semibold">{money(availableUsd)} USD</p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-xs">After this receive</p>
                                            <p className={`font-mono font-semibold ${remainingAfter < 0 ? 'text-destructive' : 'text-emerald-700'}`}>
                                                {money(remainingAfter)} USD
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-xs">Investor profit total</p>
                                            <p className="font-mono font-semibold text-emerald-700">
                                                {money(totalProfitUsd)} USD
                                            </p>
                                            <p className="text-muted-foreground text-xs">owed when sold</p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

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
                                        <span className="text-muted-foreground"> → {selected.stock_quantity + qty}</span>
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
                    <h2 className="mb-3 text-sm font-semibold">Quantity & prices</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div className="space-y-2">
                            <Label>Quantity</Label>
                            <Input type="number" min="1" value={form.quantity} onChange={(e) => setForm({ ...form, quantity: e.target.value })} required />
                        </div>
                        <div className="space-y-2">
                            <Label>Purchase price (AFN)</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.purchase_price_afn}
                                onChange={(e) => setForm({ ...form, purchase_price_afn: e.target.value })}
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Sale price (AFN)</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.sale_price_afn}
                                onChange={(e) => setForm({ ...form, sale_price_afn: e.target.value })}
                                required
                            />
                        </div>
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
                                        <SelectItem key={supplier.id} value={String(supplier.id)}>
                                            {supplier.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Notes</Label>
                            <Textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} rows={2} />
                        </div>
                        {!isInvestor && (
                            <div className="space-y-2">
                                <Label>Exchange rate (optional)</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={form.exchange_rate}
                                    onChange={(e) => setForm({ ...form, exchange_rate: e.target.value })}
                                    placeholder="USD to AFN"
                                />
                            </div>
                        )}
                    </div>
                </div>

                <FormActions backHref="/products" submitLabel="Add stock" loading={loading} />
            </form>
        </AppLayout>
    );
}
