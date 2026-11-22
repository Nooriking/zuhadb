import { BackButton, FormActions } from '@/components/form-page';
import {
    TransactionExchangeRateSection,
    threeCurrencyEquivalents,
    type FxBoardContext,
} from '@/components/transaction-exchange-rate-section';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useExchange } from '@/hooks/useExchange';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AlertCircle, Building2, CircleDollarSign, Landmark, TrendingUp } from 'lucide-react';
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

interface CurrencyOption {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
}

interface InvestorOption {
    id: number;
    name: string;
}

interface InvestmentOption {
    id: number;
    investor_id: number;
    investment_number: string | null;
    source_type: string;
    currency_code: string | null;
    available_amount_afn: number;
    label: string;
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
    currencies?: CurrencyOption[];
    fxBoard?: FxBoardContext;
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Products', href: '/products' },
    { title: 'Add stock', href: '/products/receive' },
];

const money = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 4 }).format(value || 0);

export default function ProductReceive({
    variants,
    investors,
    investments = [],
    suppliers,
    currencies = [],
    fxBoard,
    errors = {},
}: Props) {
    const { usdToAfn, aedToAfn } = useExchange();

    const ledgerId = useMemo(() => {
        const afn = currencies.find((c) => c.code === 'AFN');
        return afn ? String(afn.id) : currencies[0] ? String(currencies[0].id) : '';
    }, [currencies]);

    const [form, setForm] = useState({
        variant_id: variants[0] ? String(variants[0].id) : '',
        quantity: '1',
        purchase_currency_id: ledgerId,
        purchase_unit_amount: variants[0] ? String(variants[0].purchase_price_afn || '') : '',
        sale_price_afn: variants[0] ? String(variants[0].sale_price_afn || '') : '',
        funding_source: 'office' as 'office' | 'investor',
        investor_id: '',
        investment_id: '',
        profit_per_unit: '',
        profit_currency_id: ledgerId,
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
    const selectedCurrency = useMemo(
        () => currencies.find((c) => String(c.id) === form.purchase_currency_id),
        [currencies, form.purchase_currency_id],
    );
    const profitCurrency = useMemo(
        () => currencies.find((c) => String(c.id) === form.profit_currency_id),
        [currencies, form.profit_currency_id],
    );
    const isInvestor = form.funding_source === 'investor';
    const investorInvestments = useMemo(
        () => investments.filter((inv) => String(inv.investor_id) === form.investor_id),
        [investments, form.investor_id],
    );
    const qty = parseInt(form.quantity, 10) || 0;
    const unitOriginal = parseFloat(form.purchase_unit_amount) || 0;
    const usdRate = usdToAfn || 0;
    const aedRate = aedToAfn || 0;
    const eq = threeCurrencyEquivalents(unitOriginal, selectedCurrency?.code || 'AFN', usdRate, aedRate);
    const lineEq = threeCurrencyEquivalents(unitOriginal * qty, selectedCurrency?.code || 'AFN', usdRate, aedRate);
    const purchasePriceAfn = eq.afn;
    const profitOriginal = parseFloat(form.profit_per_unit) || 0;
    const profitEq = threeCurrencyEquivalents(
        profitOriginal,
        selectedCurrency?.code || profitCurrency?.code || 'AFN',
        usdRate,
        aedRate,
    );
    const profitAfn = profitEq.afn;
    const totalCostAfn = lineEq.afn;
    const totalProfitAfn = profitAfn * qty;
    const salePrice = parseFloat(form.sale_price_afn) || 0;
    const companyPreviewPerUnit = Math.max(0, salePrice - purchasePriceAfn - profitAfn);

    const liveProblems = useMemo(() => {
        const problems: string[] = [];
        if (!form.variant_id) problems.push('Select a product');
        if (qty <= 0) problems.push('Quantity must be at least 1');
        if (!form.purchase_currency_id) problems.push('Select purchase currency');
        if (unitOriginal < 0) problems.push('Purchase price cannot be negative');
        if (salePrice < purchasePriceAfn) problems.push('Sale price (AFN) cannot be less than purchase cost (AFN)');
        if (usdRate <= 0 || aedRate <= 0) problems.push('Exchange rates must be greater than zero');
        if (isInvestor && !form.investor_id) problems.push('Select an investor');
        return problems;
    }, [form.variant_id, form.purchase_currency_id, form.investor_id, qty, unitOriginal, salePrice, purchasePriceAfn, usdRate, aedRate, isInvestor]);

    const pickVariant = (id: string) => {
        const variant = list.find((v) => String(v.id) === id);
        setForm((prev) => ({
            ...prev,
            variant_id: id,
            purchase_unit_amount: variant ? String(variant.purchase_price_afn || '') : prev.purchase_unit_amount,
            sale_price_afn: variant ? String(variant.sale_price_afn || '') : prev.sale_price_afn,
        }));
    };

    const applyVariant = (variant: VariantOption) => {
        setList((prev) => (prev.some((item) => item.id === variant.id) ? prev : [variant, ...prev]));
        setForm((prev) => ({
            ...prev,
            variant_id: String(variant.id),
            purchase_unit_amount: String(variant.purchase_price_afn || ''),
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

    const setInvestor = (investorId: string) =>
        setForm((prev) => ({
            ...prev,
            investor_id: String(investorId),
            investment_id: '',
        }));

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
        setScanMessage('');
        if (liveProblems.length > 0) {
            setScanMessage(`❌ ${liveProblems[0]}`);
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }
        setLoading(true);
        const payload: Record<string, unknown> = {
            ...form,
            investor_id: isInvestor ? form.investor_id : null,
            investment_id: isInvestor && form.investment_id ? form.investment_id : null,
            profit_per_unit: isInvestor ? form.profit_per_unit : '0',
            profit_currency_id: isInvestor
                ? form.purchase_currency_id || form.profit_currency_id || ledgerId
                : ledgerId,
        };
        delete payload.usd_to_afn_rate;
        delete payload.aed_to_afn_rate;
        delete payload.rate_source;
        router.post('/products/receive', payload, {
            onFinish: () => setLoading(false),
            onError: (errs) => {
                const firstError = Object.values(errs)[0] as string;
                setScanMessage(`❌ ${firstError || 'Could not save. Please check the form.'}`);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add stock" />
            <form onSubmit={submit} className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/products" />
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Add stock</h1>
                        <p className="text-muted-foreground text-sm">Board rates are applied by the server; receive inventory in the purchase currency</p>
                    </div>
                </div>

                {liveProblems.length > 0 && (
                    <div className="rounded-lg border-2 border-amber-400 bg-amber-50 p-4">
                        <div className="mb-2 flex items-center gap-2">
                            <AlertCircle className="h-5 w-5 text-amber-700" />
                            <p className="font-bold text-amber-800">
                                Cannot submit yet ({liveProblems.length} {liveProblems.length === 1 ? 'issue' : 'issues'})
                            </p>
                        </div>
                        <ul className="list-inside list-disc space-y-1 text-sm text-amber-700">
                            {liveProblems.map((p, i) => (
                                <li key={i}>{p}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <TransactionExchangeRateSection context={fxBoard} />

                <div className="rounded-lg border p-4">
                    <h2 className="mb-3 text-sm font-semibold">Purchase date & supplier</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div className="space-y-2">
                            <Label>Date</Label>
                            <Input
                                type="date"
                                value={form.purchase_date}
                                onChange={(e) => setForm((prev) => ({ ...prev, purchase_date: e.target.value }))}
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Supplier</Label>
                            <Select
                                value={form.supplier_id || 'none'}
                                onValueChange={(v) => setForm((prev) => ({ ...prev, supplier_id: v === 'none' ? '' : v }))}
                            >
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
                            <Label>Invoice number</Label>
                            <Input value={form.invoice_number} onChange={(e) => setForm((prev) => ({ ...prev, invoice_number: e.target.value }))} />
                        </div>
                    </div>
                </div>

                <div className="border-primary bg-primary/5 rounded-xl border-2 p-5">
                    <h2 className="text-lg font-semibold">Scan barcode</h2>
                    <div className="mt-3 flex gap-2">
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
                            className="bg-background h-12 font-mono text-lg"
                        />
                        <Button type="button" className="h-12 px-6" onClick={() => void scanBarcode()} disabled={scanning}>
                            {scanning ? 'Looking up…' : 'Find'}
                        </Button>
                    </div>
                    {scanMessage && (
                        <div
                            className={`mt-3 flex items-center gap-2 rounded-lg border-2 p-3 text-sm font-semibold ${
                                scanMessage.startsWith('❌')
                                    ? 'border-red-400 bg-red-50 text-red-700'
                                    : 'border-emerald-400 bg-emerald-50 text-emerald-700'
                            }`}
                        >
                            <AlertCircle className="h-5 w-5 shrink-0" />
                            <span>{scanMessage}</span>
                        </div>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Landmark className="h-5 w-5" /> Funding source
                        </CardTitle>
                        <CardDescription>Investor goods keep the original purchase currency and never increase the cash wallet.</CardDescription>
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
                            </button>
                            <button
                                type="button"
                                onClick={() => setFundingSource('investor')}
                                className={`rounded-xl border-2 p-4 text-left transition ${
                                    isInvestor ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'
                                }`}
                            >
                                <div className="mb-2 flex items-center gap-2 font-semibold">
                                    <CircleDollarSign className="h-5 w-5" /> Investor goods
                                </div>
                            </button>
                        </div>
                        {isInvestor && (
                            <div className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>
                                            Investor <span className="text-destructive">*</span>
                                        </Label>
                                        <Select value={form.investor_id || ''} onValueChange={setInvestor}>
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
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Cash / goods capital (optional)</Label>
                                        <Select
                                            value={form.investment_id || 'new_goods'}
                                            onValueChange={(v) =>
                                                setForm((prev) => ({
                                                    ...prev,
                                                    investment_id: v === 'new_goods' ? '' : v,
                                                }))
                                            }
                                            disabled={!form.investor_id}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="New goods contribution" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="new_goods">New goods contribution (no cash draw)</SelectItem>
                                                {investorInvestments.map((inv) => (
                                                    <SelectItem key={inv.id} value={String(inv.id)}>
                                                        {inv.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <p className="text-muted-foreground text-xs">
                                            Leave as new goods to record in-kind stock. Select cash capital to allocate existing funds.
                                        </p>
                                        {errors.investment_id ? (
                                            <p className="text-destructive text-xs">{errors.investment_id}</p>
                                        ) : null}
                                    </div>
                                </div>
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label>Profit currency</Label>
                                        <Input
                                            value={selectedCurrency?.code || profitCurrency?.code || 'AFN'}
                                            readOnly
                                            tabIndex={-1}
                                        />
                                        <p className="text-muted-foreground text-xs">Follows purchase currency.</p>
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <Label className="flex items-center gap-2">
                                            <TrendingUp className="h-4 w-4" /> Fixed investor profit / unit (
                                            {selectedCurrency?.code || profitCurrency?.code || 'AFN'})
                                        </Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={form.profit_per_unit}
                                            onChange={(e) => setForm((prev) => ({ ...prev, profit_per_unit: e.target.value }))}
                                        />
                                    </div>
                                </div>
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <div className="rounded-lg border p-3 text-sm">
                                        <p className="text-muted-foreground text-xs">Original PPU</p>
                                        <p className="font-semibold">
                                            {money(profitOriginal)} {profitCurrency?.code || 'AFN'}
                                        </p>
                                    </div>
                                    <div className="rounded-lg border p-3 text-sm">
                                        <p className="text-muted-foreground text-xs">AFN / USD / AED PPU</p>
                                        <p className="font-semibold">
                                            {money(profitEq.afn)} AFN · {money(profitEq.usd)} USD · {money(profitEq.aed)} AED
                                        </p>
                                    </div>
                                    <div className="rounded-lg border p-3 text-sm">
                                        <p className="text-muted-foreground text-xs">Company preview / unit</p>
                                        <p className="font-semibold">{money(companyPreviewPerUnit)} AFN</p>
                                        <p className="text-muted-foreground text-xs">sale − cost − investor (preview)</p>
                                    </div>
                                </div>
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
                        </div>
                        {selected && (
                            <div className="border-primary/40 bg-primary/5 grid grid-cols-2 gap-3 rounded-lg border p-4 md:grid-cols-4">
                                <div>
                                    <p className="text-muted-foreground text-xs">Name</p>
                                    <p className="text-sm font-medium">{selected.name}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs">Current stock</p>
                                    <p className="text-sm font-medium">
                                        {selected.stock_quantity}
                                        <span className="text-muted-foreground"> → {selected.stock_quantity + qty}</span>
                                    </p>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="rounded-lg border p-4">
                    <h2 className="mb-3 text-sm font-semibold">Quantity, purchase currency & prices</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div className="space-y-2">
                            <Label>Quantity</Label>
                            <Input
                                type="number"
                                min="1"
                                value={form.quantity}
                                onChange={(e) => setForm((prev) => ({ ...prev, quantity: e.target.value }))}
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Purchase currency</Label>
                            <Select
                                value={form.purchase_currency_id}
                                onValueChange={(v) =>
                                    setForm((prev) => ({
                                        ...prev,
                                        purchase_currency_id: v,
                                        profit_currency_id: v,
                                    }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Currency" />
                                </SelectTrigger>
                                <SelectContent>
                                    {currencies
                                        .filter((c) => ['AFN', 'USD', 'AED'].includes(c.code))
                                        .map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.code} {c.symbol ? `(${c.symbol})` : ''}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Unit purchase price ({selectedCurrency?.code || '—'})</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.purchase_unit_amount}
                                onChange={(e) => setForm((prev) => ({ ...prev, purchase_unit_amount: e.target.value }))}
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Sale price (AFN catalog)</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.sale_price_afn}
                                onChange={(e) => setForm((prev) => ({ ...prev, sale_price_afn: e.target.value }))}
                                required
                            />
                        </div>
                    </div>

                    <div className="mt-4 space-y-2">
                        <h3 className="text-sm font-semibold">Final currency equivalents</h3>
                        <p className="text-muted-foreground text-xs">
                            Converted with Central Exchange Board rates
                            {selectedCurrency?.code ? ` · original in ${selectedCurrency.code}` : ''}.
                        </p>
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
                            <div className="rounded-lg border p-3 text-sm">
                                <p className="text-muted-foreground text-xs">Original</p>
                                <p className="font-semibold">
                                    {money(unitOriginal * qty)} {selectedCurrency?.code || '—'}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {money(unitOriginal)} / unit
                                </p>
                            </div>
                            <div className="rounded-lg border p-3 text-sm">
                                <p className="text-muted-foreground text-xs">AFN</p>
                                <p className="font-semibold">{money(lineEq.afn)} AFN</p>
                                <p className="text-muted-foreground text-xs">{money(eq.afn)} / unit</p>
                            </div>
                            <div className="rounded-lg border p-3 text-sm">
                                <p className="text-muted-foreground text-xs">USD</p>
                                <p className="font-semibold">{money(lineEq.usd)} USD</p>
                                <p className="text-muted-foreground text-xs">{money(eq.usd)} / unit</p>
                            </div>
                            <div className="rounded-lg border p-3 text-sm">
                                <p className="text-muted-foreground text-xs">AED</p>
                                <p className="font-semibold">{money(lineEq.aed)} AED</p>
                                <p className="text-muted-foreground text-xs">{money(eq.aed)} / unit</p>
                            </div>
                        </div>
                    </div>
                    {isInvestor && qty > 0 ? (
                        <p className="text-muted-foreground mt-3 text-sm">
                            Goods contribution {money(totalCostAfn)} AFN historical · investor sale profit {money(totalProfitAfn)} AFN
                        </p>
                    ) : null}
                </div>

                <div className="rounded-lg border p-4">
                    <Label>Notes</Label>
                    <Textarea className="mt-2" value={form.notes} onChange={(e) => setForm((prev) => ({ ...prev, notes: e.target.value }))} rows={2} />
                </div>

                <FormActions backHref="/products" submitLabel="Add stock" loading={loading} />
            </form>
        </AppLayout>
    );
}
