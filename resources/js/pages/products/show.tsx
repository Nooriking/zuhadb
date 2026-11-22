// resources/js/pages/products/show.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatCatalogAmount, LOW_STOCK_THRESHOLD } from '@/lib/inventory';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Barcode as BarcodeIcon,
    Calendar,
    CircleDollarSign,
    DollarSign,
    Edit,
    Package,
    Receipt,
    Store,
    TrendingUp,
    User,
    Wallet,
} from 'lucide-react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Barcode {
    id: number;
    barcode: string;
    barcode_type: string;
    is_primary: boolean;
    status: string;
    current_stock: number;
}

interface ProductVariant {
    id: number;
    product_id: number;
    image: string | null;
    volume_ml: number | null;
    concentration: string | null;
    stock_quantity: number;
    catalog_currency_id?: number | null;
    purchase_amount?: string | null;
    purchase_price_afn: string;
    purchase_amount_usd?: string | null;
    purchase_amount_aed?: string | null;
    purchase_currency_id?: number | null;
    sale_amount?: string | null;
    sale_price_afn: string;
    sale_amount_usd?: string | null;
    sale_amount_aed?: string | null;
    sale_currency_id?: number | null;
    wholesale_amount?: string | null;
    wholesale_price_afn: string;
    wholesale_amount_usd?: string | null;
    wholesale_amount_aed?: string | null;
    wholesale_currency_id?: number | null;
    wholesale_min_qty: number;
    profit_per_unit: string;
    status: 'active' | 'inactive';
    catalog_currency?: { id: number; code: string; symbol?: string | null; name?: string } | null;
    purchase_currency?: { id: number; code: string; symbol?: string | null; name?: string } | null;
    sale_currency?: { id: number; code: string; symbol?: string | null; name?: string } | null;
    wholesale_currency?: { id: number; code: string; symbol?: string | null; name?: string } | null;
    barcodes: Barcode[];
}

interface Product {
    id: number;
    name: string | null;
    gender: 'male' | 'female' | 'unisex';
    status: 'active' | 'inactive';
    description: string | null;
    brand: { id: number; name: string } | null;
    creator: { id: number; name: string; email: string } | null;
    variants: ProductVariant[];
    created_at: string;
    updated_at: string;
}

interface Purchase {
    id: number;
    variant_id: number;
    investor_id: number | null;
    supplier_id: number | null;
    quantity: number;
    remaining_quantity: number;
    invoice_number: string | null;
    purchase_date: string;
    payment_status: string | null;
    exchange_rate: string | null;
    profit_per_unit: string;
    profit_per_unit_original?: string | number | null;
    profit_currency_code?: string | null;
    line_total: string;
    line_profit: string;

    // ✅ جدید
    store_profit_per_unit?: string | null;
    line_store_profit?: string | null;

    purchase_price_afn: string;
    purchase_price_usd: string;
    purchase_currency_code?: string | null;
    purchase_unit_amount?: string | null;
    purchase_line_amount?: string | null;
    unit_amount_usd?: string | null;
    unit_amount_aed?: string | null;
    line_amount_usd?: string | null;
    line_amount_aed?: string | null;
    usd_to_afn_rate?: string | null;
    aed_to_afn_rate?: string | null;
    usd_to_aed_rate?: string | null;
    rate_effective_at?: string | null;
    rate_source?: string | null;
    notes: string | null;
    bill_image: string | null;
    variant?: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
    };
    investor?: { id: number; full_name: string } | null;
    supplier?: { id: number; name: string } | null;
    invoice_id?: number | null;
    invoice_paid_afn?: number;
    invoice_due_afn?: number;
}

type InventoryValuation = {
    basis: string;
    basis_label: string;
    total_afn_remaining: string | number;
    by_currency: Record<
        string,
        {
            code: string;
            original_amount: string | number;
            historical_afn: string | number;
            units: number;
            batches: number;
            weighted_average_fx_to_afn: string | number | null;
            kind: string;
            available: boolean;
        }
    >;
};

interface Props {
    product: Product;
    purchases?: Purchase[];
    inventoryValuation?: InventoryValuation | null;
}

/* ═══════════════════════════════════════════════════════
 |  Constants
 ═══════════════════════════════════════════════════════ */

const genderLabels: Record<string, string> = {
    male: 'Men',
    female: 'Women',
    unisex: 'Unisex',
};

const concentrationLabels: Record<string, string> = {
    parfum: 'Parfum',
    edp: 'EDP',
    edt: 'EDT',
    edc: 'EDC',
    edt_legere: 'EDT Légère',
    body_mist: 'Body Mist',
    attar: 'Attar',
    oud: 'Oud',
    other: 'Other',
};

const paymentStatusColors: Record<string, string> = {
    paid: 'bg-green-100 text-green-800 border-green-300',
    unpaid: 'bg-red-100 text-red-800 border-red-300',
    partial: 'bg-yellow-100 text-yellow-800 border-yellow-300',
    credit: 'bg-amber-100 text-amber-800 border-amber-300',
};

const paymentStatusLabels: Record<string, string> = {
    paid: 'Paid',
    unpaid: 'Unpaid',
    partial: 'Partial',
    credit: 'Credit',
};

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const toNum = (value: string | number | null | undefined): number => {
    const n = parseFloat(String(value ?? ''));
    return Number.isFinite(n) ? n : 0;
};

const formatPrice = (value: string | number | null | undefined, currencyCode: string = 'AFN') =>
    formatCatalogAmount(value, currencyCode);

const formatRate = (value: string | number | null | undefined) => {
    // No thousand separators — exchange rates must not look scaled (e.g. 56,000 vs 56000).
    const n = toNum(value);
    if (!Number.isFinite(n) || n <= 0) return '—';
    const fixed = n.toFixed(8).replace(/\.?0+$/, '');
    return fixed || '0';
};

const formatDate = (dateStr: string | null | undefined) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const getVariantDisplayName = (v: ProductVariant | undefined | null): string => {
    if (!v) return '—';
    const parts: string[] = [];
    if (v.volume_ml) parts.push(`${v.volume_ml}ml`);
    if (v.concentration) parts.push(concentrationLabels[v.concentration] || v.concentration);
    return parts.join(' · ') || `Variant #${v.id}`;
};

const isInvestorPurchase = (investorId: number | null | undefined): boolean => {
    return investorId !== null && investorId !== undefined && investorId > 0;
};

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function ProductShow({
    product,
    purchases = [],
    inventoryValuation = null,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Products', href: '/products' },
        { title: product.name || `Product #${product.id}`, href: `/products/${product.id}` },
    ];

    /* ═══════════ Derived totals ═══════════ */

    const totalStock = product.variants.reduce((sum, v) => sum + (v.stock_quantity ?? 0), 0);
    const totalBarcodes = product.variants.reduce((sum, v) => sum + (v.barcodes?.length ?? 0), 0);

    // ✅ مجموع هزینه خرید
    const totalCostAfn = purchases.reduce((sum, p) => sum + toNum(p.line_total), 0);

    // ✅ مجموع سود (investor + store)
    const totalProfitAfn = purchases.reduce((sum, p) => sum + toNum(p.line_profit), 0);

    // ✅ ✅ جدید: مجموع سود سرمایه‌گذار
    const totalInvestorProfitAfn = purchases.filter((p) => isInvestorPurchase(p.investor_id)).reduce((sum, p) => sum + toNum(p.line_profit), 0);

    // ✅ ✅ جدید: مجموع سود دوکان
    const totalStoreProfitAfn = purchases.reduce((sum, p) => sum + toNum(p.line_store_profit), 0);

    // ✅ مجموع تعداد خریداری‌شده
    const totalPurchasedQty = purchases.reduce((sum, p) => sum + (p.quantity ?? 0), 0);

    // ✅ مجموع باقی‌مانده
    const totalRemainingQty = purchases.reduce((sum, p) => sum + (p.remaining_quantity ?? 0), 0);

    // ✅ مجموع پرداختی و باقی‌مانده فاکتور
    const totalPaid = purchases.reduce((sum, p) => sum + toNum(p.invoice_paid_afn), 0);
    const totalDue = purchases.reduce((sum, p) => sum + toNum(p.invoice_due_afn), 0);

    // ✅ تعداد purchases با investor
    const investorPurchases = purchases.filter((p) => isInvestorPurchase(p.investor_id));
    const storePurchases = purchases.filter((p) => !isInvestorPurchase(p.investor_id));

    // ✅ محاسبه میانگین نرخ ارز
    const ratesWithValue = purchases.filter((p) => toNum(p.exchange_rate) > 0);
    const avgExchangeRate =
        ratesWithValue.length > 0 ? ratesWithValue.reduce((sum, p) => sum + toNum(p.exchange_rate), 0) / ratesWithValue.length : 0;

    // ✅ آخرین نرخ ارز
    const latestPurchase = purchases.length > 0 ? purchases[0] : null;
    const latestExchangeRate = latestPurchase ? toNum(latestPurchase.exchange_rate) : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={product.name || `Product #${product.id}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Package className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">{product.name || `Product #${product.id}`}</h1>
                        <Badge variant="outline">#{product.id}</Badge>
                        {product.status === 'active' ? (
                            <Badge className="bg-green-600 hover:bg-green-700">Active</Badge>
                        ) : (
                            <Badge variant="secondary">Inactive</Badge>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Link href="/products">
                            <Button variant="outline" className="gap-2">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </Button>
                        </Link>
                        <Link href={`/products/${product.id}/edit`}>
                            <Button className="gap-2">
                                <Edit className="h-4 w-4" /> Edit
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* ═══════════ Quick Stats ═══════════ */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-5">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Variants</CardDescription>
                            <CardTitle className="text-2xl">{product.variants.length}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Barcodes</CardDescription>
                            <CardTitle className="text-2xl">{totalBarcodes}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total Stock</CardDescription>
                            <CardTitle className="text-2xl">{totalStock > 0 ? totalStock : <span className="text-destructive">0</span>}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Purchases</CardDescription>
                            <CardTitle className="text-2xl">{purchases.length}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total Quantity</CardDescription>
                            <CardTitle className="text-2xl">{totalPurchasedQty}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                {/* ═══════════ Purchase snapshot rates (historical, not rewritten) ═══════════ */}
                {ratesWithValue.length > 0 && (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <Card className="border-l-4 border-l-blue-600 bg-gradient-to-br from-blue-50 to-blue-100/50">
                            <CardHeader className="pb-2">
                                <CardDescription className="flex items-center gap-1 text-blue-800">
                                    <CircleDollarSign className="h-4 w-4" /> Latest purchase snapshot
                                </CardDescription>
                                <CardTitle className="flex items-baseline gap-2 font-mono text-3xl text-blue-900">
                                    {formatRate(latestExchangeRate)}
                                    <span className="text-base font-normal text-blue-700">AFN / USD</span>
                                </CardTitle>
                                <p className="text-xs text-blue-700">
                                    {latestPurchase?.purchase_date && <>on {formatDate(latestPurchase.purchase_date)}</>}
                                </p>
                            </CardHeader>
                        </Card>

                        <Card className="border-l-4 border-l-indigo-500">
                            <CardHeader className="pb-2">
                                <CardDescription className="flex items-center gap-1">
                                    <TrendingUp className="h-3 w-3" /> Average Exchange Rate
                                </CardDescription>
                                <CardTitle className="flex items-baseline gap-2 font-mono text-3xl">
                                    {formatRate(avgExchangeRate)}
                                    <span className="text-muted-foreground text-base font-normal">AFN / USD</span>
                                </CardTitle>
                                <p className="text-muted-foreground text-xs">
                                    across {ratesWithValue.length} purchase{ratesWithValue.length !== 1 ? 's' : ''}
                                </p>
                            </CardHeader>
                        </Card>

                        <Card className="border-l-4 border-l-slate-500">
                            <CardHeader className="pb-2">
                                <CardDescription>Rate Range (Min – Max)</CardDescription>
                                <CardTitle className="flex items-baseline gap-2 font-mono text-3xl">
                                    {formatRate(Math.min(...ratesWithValue.map((p) => toNum(p.exchange_rate))))}
                                    <span className="text-muted-foreground text-base font-normal">–</span>
                                    {formatRate(Math.max(...ratesWithValue.map((p) => toNum(p.exchange_rate))))}
                                </CardTitle>
                                <p className="text-muted-foreground text-xs">AFN per 1 USD</p>
                            </CardHeader>
                        </Card>
                    </div>
                )}

                {inventoryValuation && (
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-base">Remaining inventory valuation</CardTitle>
                            <CardDescription>{inventoryValuation.basis_label}</CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-3 md:grid-cols-4">
                            <div className="rounded-lg border p-3">
                                <p className="text-muted-foreground text-xs">Total AFN remaining cost</p>
                                <p className="mt-1 font-mono font-semibold">{formatPrice(inventoryValuation.total_afn_remaining)} AFN</p>
                            </div>
                            {(['AFN', 'USD', 'AED'] as const).map((code) => {
                                const row = inventoryValuation.by_currency?.[code];
                                if (!row) return null;
                                return (
                                    <div key={code} className="rounded-lg border p-3 text-sm">
                                        <p className="font-semibold">{code}</p>
                                        <p className="mt-1 font-mono">
                                            {formatPrice(row.original_amount)} {code}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            Historical AFN {formatPrice(row.historical_afn)} · {row.units} units
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            FX avg:{' '}
                                            {row.weighted_average_fx_to_afn != null
                                                ? `${formatRate(row.weighted_average_fx_to_afn)} (${row.kind})`
                                                : 'Unavailable'}
                                        </p>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                )}

                {/* ═══════════ ✅ PROFIT SPLIT SUMMARY (NEW) ═══════════ */}
                {purchases.length > 0 && (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        {/* Investor Profit */}
                        <Card className="border-l-4 border-l-blue-500">
                            <CardHeader className="pb-2">
                                <CardDescription className="flex items-center gap-1 text-blue-800">
                                    <Wallet className="h-3.5 w-3.5" /> Investor Profit (Total)
                                </CardDescription>
                                <CardTitle className="font-mono text-2xl text-blue-800">{formatPrice(totalInvestorProfitAfn)}</CardTitle>
                                <p className="text-muted-foreground text-xs">
                                    From {investorPurchases.length} investor purchase
                                    {investorPurchases.length !== 1 ? 's' : ''}
                                </p>
                            </CardHeader>
                        </Card>

                        {/* Store Profit */}
                        <Card className="border-l-4 border-l-emerald-500">
                            <CardHeader className="pb-2">
                                <CardDescription className="flex items-center gap-1 text-emerald-800">
                                    <Store className="h-3.5 w-3.5" /> Store Profit (Total)
                                </CardDescription>
                                <CardTitle className="font-mono text-2xl text-emerald-700">{formatPrice(totalStoreProfitAfn)}</CardTitle>
                                <p className="text-muted-foreground text-xs">{storePurchases.length} store + rest from investor lines</p>
                            </CardHeader>
                        </Card>

                        {/* Combined */}
                        <Card className="border-l-4 border-l-violet-500">
                            <CardHeader className="pb-2">
                                <CardDescription className="flex items-center gap-1 text-violet-800">
                                    <TrendingUp className="h-3.5 w-3.5" /> Total Profit (Combined)
                                </CardDescription>
                                <CardTitle className="font-mono text-2xl text-violet-700">
                                    {formatPrice(totalProfitAfn + totalStoreProfitAfn)}
                                </CardTitle>
                                <p className="text-muted-foreground text-xs">Investor + Store</p>
                            </CardHeader>
                        </Card>
                    </div>
                )}

                {/* ═══════════ Financial Summary ═══════════ */}
                {purchases.length > 0 && (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <Card className="border-l-4 border-l-slate-500">
                            <CardHeader className="pb-2">
                                <CardDescription className="flex items-center gap-1">
                                    <DollarSign className="h-3 w-3" /> Total Cost
                                </CardDescription>
                                <CardTitle className="font-mono text-2xl">{formatPrice(totalCostAfn)}</CardTitle>
                                <p className="text-muted-foreground text-xs">AFN · sum of line totals</p>
                            </CardHeader>
                        </Card>
                        <Card className="border-l-4 border-l-emerald-500">
                            <CardHeader className="pb-2">
                                <CardDescription className="flex items-center gap-1">
                                    <TrendingUp className="h-3 w-3" /> Total Profit (Inv.)
                                </CardDescription>
                                <CardTitle className="font-mono text-2xl text-emerald-700">{formatPrice(totalProfitAfn)}</CardTitle>
                                <p className="text-muted-foreground text-xs">AFN · investor share only</p>
                            </CardHeader>
                        </Card>
                        <Card className="border-l-4 border-l-amber-500">
                            <CardHeader className="pb-2">
                                <CardDescription>Paid</CardDescription>
                                <CardTitle className="font-mono text-2xl text-amber-700">{formatPrice(totalPaid)}</CardTitle>
                                <p className="text-muted-foreground text-xs">AFN · invoice payments</p>
                            </CardHeader>
                        </Card>
                        <Card className="border-l-4 border-l-red-500">
                            <CardHeader className="pb-2">
                                <CardDescription>Due</CardDescription>
                                <CardTitle className="font-mono text-2xl text-red-700">{formatPrice(totalDue)}</CardTitle>
                                <p className="text-muted-foreground text-xs">AFN · remaining</p>
                            </CardHeader>
                        </Card>
                    </div>
                )}

                {/* ═══════════ Product Information ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle>Product Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <dt className="text-muted-foreground text-xs font-semibold uppercase">Name</dt>
                                <dd className="mt-1 text-sm font-medium">{product.name || '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs font-semibold uppercase">Brand</dt>
                                <dd className="mt-1 text-sm font-medium">
                                    {product.brand ? <Badge variant="outline">{product.brand.name}</Badge> : '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs font-semibold uppercase">Gender</dt>
                                <dd className="mt-1 text-sm font-medium">
                                    <Badge variant="secondary">{genderLabels[product.gender] || product.gender}</Badge>
                                </dd>
                            </div>
                            <div className="md:col-span-3">
                                <dt className="text-muted-foreground text-xs font-semibold uppercase">Description</dt>
                                <dd className="mt-1 text-sm">{product.description || '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs font-semibold uppercase">Created</dt>
                                <dd className="mt-1 flex items-center gap-1 text-sm">
                                    <Calendar className="h-3 w-3" />
                                    {formatDate(product.created_at)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs font-semibold uppercase">Updated</dt>
                                <dd className="mt-1 flex items-center gap-1 text-sm">
                                    <Calendar className="h-3 w-3" />
                                    {formatDate(product.updated_at)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs font-semibold uppercase">Created By</dt>
                                <dd className="mt-1 text-sm">{product.creator?.name || '—'}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {/* ═══════════ Variants ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="h-5 w-5" />
                            Variants ({product.variants.length})
                        </CardTitle>
                        <CardDescription>All variants with prices, stock, and barcodes.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {product.variants.length === 0 ? (
                            <div className="text-muted-foreground py-8 text-center text-sm">No variants yet.</div>
                        ) : (
                            <div className="space-y-4">
                                {product.variants.map((v, idx) => (
                                    <div key={v.id} className="rounded-lg border p-4">
                                        <div className="mb-3 flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <h3 className="font-semibold">Variant #{idx + 1}</h3>
                                                <Badge variant="outline">ID: {v.id}</Badge>
                                                <Badge variant="secondary">{getVariantDisplayName(v)}</Badge>
                                                {v.status === 'active' ? (
                                                    <Badge className="bg-green-600 text-xs">Active</Badge>
                                                ) : (
                                                    <Badge variant="secondary" className="text-xs">
                                                        Inactive
                                                    </Badge>
                                                )}
                                            </div>
                                            {v.stock_quantity <= 0 ? (
                                                <Badge variant="destructive" className="gap-1">
                                                    <AlertTriangle className="h-3 w-3" /> Out of Stock
                                                </Badge>
                                            ) : v.stock_quantity <= LOW_STOCK_THRESHOLD ? (
                                                <Badge variant="outline" className="gap-1 border-amber-500 text-amber-700">
                                                    <AlertTriangle className="h-3 w-3" /> Low: {v.stock_quantity}
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">Stock: {v.stock_quantity}</Badge>
                                            )}
                                        </div>

                                        <div className="mb-2">
                                            <p className="text-muted-foreground text-[10px]">
                                                Catalog reference prices with independent currencies — not historical purchase/sale FX.
                                            </p>
                                        </div>

                                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                                            {[
                                                {
                                                    label: 'Purchase (catalog)',
                                                    original: v.purchase_amount,
                                                    code: v.purchase_currency?.code || v.catalog_currency?.code || 'AFN',
                                                    afn: v.purchase_price_afn,
                                                    usd: v.purchase_amount_usd,
                                                    aed: v.purchase_amount_aed,
                                                },
                                                {
                                                    label: 'Sale (catalog)',
                                                    original: v.sale_amount,
                                                    code: v.sale_currency?.code || v.catalog_currency?.code || 'AFN',
                                                    afn: v.sale_price_afn,
                                                    usd: v.sale_amount_usd,
                                                    aed: v.sale_amount_aed,
                                                    emphasize: true,
                                                },
                                                {
                                                    label: 'Wholesale (catalog)',
                                                    original: v.wholesale_amount,
                                                    code: v.wholesale_currency?.code || v.catalog_currency?.code || 'AFN',
                                                    afn: v.wholesale_price_afn,
                                                    usd: v.wholesale_amount_usd,
                                                    aed: v.wholesale_amount_aed,
                                                    minQty: v.wholesale_min_qty,
                                                },
                                            ].map((row) => {
                                                return (
                                                    <div key={row.label} className="bg-muted/30 rounded-md p-2">
                                                        <div className="text-muted-foreground text-xs">{row.label}</div>
                                                        <div className={`font-mono text-sm font-semibold ${row.emphasize ? 'text-green-700' : ''}`}>
                                                            {formatPrice(row.original ?? row.afn, row.code)}{' '}
                                                            <span className="text-muted-foreground text-xs">{row.code} · entered</span>
                                                        </div>
                                                        <div className="text-muted-foreground mt-1 space-y-0.5 font-mono text-[10px]">
                                                            <div>AFN: {formatPrice(row.afn, 'AFN')}</div>
                                                            <div>USD: {formatPrice(row.usd ?? 0, 'USD')}</div>
                                                            <div>AED: {formatPrice(row.aed ?? 0, 'AED')}</div>
                                                        </div>
                                                        {row.minQty && row.minQty > 0 ? (
                                                            <div className="text-muted-foreground mt-1 text-[10px]">Min qty: {row.minQty}</div>
                                                        ) : null}
                                                    </div>
                                                );
                                            })}
                                            <div className="bg-muted/30 rounded-md p-2">
                                                <div className="text-muted-foreground text-xs">Ledger profit / unit</div>
                                                <div className="font-mono text-sm font-semibold text-emerald-700">
                                                    {formatPrice(toNum(v.sale_price_afn) - toNum(v.purchase_price_afn))}
                                                    <span className="text-muted-foreground ml-1 text-xs">AFN</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="mt-3">
                                            <div className="mb-2 flex items-center gap-2">
                                                <BarcodeIcon className="h-3 w-3 text-blue-600" />
                                                <span className="text-xs font-semibold">Barcodes ({v.barcodes?.length ?? 0})</span>
                                            </div>
                                            {v.barcodes && v.barcodes.length > 0 ? (
                                                <div className="flex flex-wrap gap-2">
                                                    {v.barcodes.map((b) => (
                                                        <div
                                                            key={b.id}
                                                            className={`flex items-center gap-2 rounded-md border px-2 py-1 font-mono text-xs ${
                                                                b.is_primary ? 'border-blue-300 bg-blue-50' : 'bg-muted/30'
                                                            }`}
                                                        >
                                                            <span>{b.barcode}</span>
                                                            <Badge variant="outline" className="text-[10px]">
                                                                {b.barcode_type}
                                                            </Badge>
                                                            {b.is_primary && <Badge className="bg-blue-600 text-[10px]">Primary</Badge>}
                                                            <span className="text-muted-foreground text-[10px]">Stock: {b.current_stock}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <div className="text-muted-foreground text-xs">No barcodes</div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ═══════════ Purchases ═══════════ */}
                {purchases.length > 0 && (
                    <Card>
                        <CardHeader>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <Receipt className="h-5 w-5" />
                                        Purchase History ({purchases.length})
                                    </CardTitle>
                                    <CardDescription>
                                        {investorPurchases.length} investor · {storePurchases.length} store-owned
                                    </CardDescription>
                                </div>
                                <div className="flex flex-wrap gap-3">
                                    <div className="text-right">
                                        <div className="text-muted-foreground text-xs">Total Cost</div>
                                        <div className="font-mono text-sm font-bold">{formatPrice(totalCostAfn)} AFN</div>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-muted-foreground text-xs">Inv. Profit</div>
                                        <div className="font-mono text-sm font-bold text-blue-700">{formatPrice(totalInvestorProfitAfn)} AFN</div>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-muted-foreground text-xs">Store Profit</div>
                                        <div className="font-mono text-sm font-bold text-emerald-700">{formatPrice(totalStoreProfitAfn)} AFN</div>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-muted-foreground text-xs">Remaining</div>
                                        <div className="font-mono text-sm font-bold">{totalRemainingQty}</div>
                                    </div>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>#</TableHead>
                                            <TableHead>Variant</TableHead>
                                            <TableHead>Owner</TableHead>
                                            <TableHead>Supplier</TableHead>
                                            <TableHead>Invoice</TableHead>
                                            <TableHead>Payment</TableHead>
                                            <TableHead className="bg-blue-50 text-center text-blue-800">
                                                <div className="flex items-center justify-center gap-1">
                                                    <CircleDollarSign className="h-3 w-3" />
                                                    Exchange Rate
                                                </div>
                                                <div className="text-[10px] font-normal text-blue-600">USD → AFN</div>
                                            </TableHead>
                                            <TableHead className="text-center">Qty</TableHead>
                                            <TableHead className="text-right">Unit</TableHead>
                                            <TableHead className="text-right">Line Total</TableHead>
                                            <TableHead className="bg-blue-50 text-right text-blue-800">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Wallet className="h-3 w-3" />
                                                    Inv. Profit
                                                </div>
                                            </TableHead>
                                            <TableHead className="bg-emerald-50 text-right text-emerald-800">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Store className="h-3 w-3" />
                                                    Store Profit
                                                </div>
                                            </TableHead>
                                            <TableHead className="text-right">Paid</TableHead>
                                            <TableHead className="text-right">Due</TableHead>
                                            <TableHead>Date</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {purchases.map((p, idx) => {
                                            const hasInvestor = isInvestorPurchase(p.investor_id);
                                            const status = (p.payment_status || 'unpaid') as keyof typeof paymentStatusLabels;
                                            const rate = toNum(p.exchange_rate);
                                            const investorProfit = toNum(p.line_profit);
                                            const storeProfit = toNum(p.line_store_profit);
                                            const profitCode = p.profit_currency_code || p.purchase_currency_code || 'AFN';
                                            const profitUnitOriginal = toNum(p.profit_per_unit_original ?? p.profit_per_unit);
                                            const profitLineOriginal = profitUnitOriginal * (p.quantity || 0);

                                            return (
                                                <TableRow key={p.id}>
                                                    <TableCell className="font-mono text-xs">{idx + 1}</TableCell>

                                                    <TableCell>
                                                        <div className="text-xs">{getVariantDisplayName(p.variant)}</div>
                                                    </TableCell>

                                                    <TableCell>
                                                        {hasInvestor && p.investor ? (
                                                            <Badge variant="outline" className="gap-1 border-blue-300 bg-blue-50">
                                                                <User className="h-3 w-3 text-blue-700" />
                                                                <span className="text-blue-800">{p.investor.full_name}</span>
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline" className="gap-1 border-emerald-300 bg-emerald-50">
                                                                <Store className="h-3 w-3 text-emerald-700" />
                                                                <span className="text-emerald-800">Store</span>
                                                            </Badge>
                                                        )}
                                                    </TableCell>

                                                    <TableCell>
                                                        {p.supplier ? (
                                                            <span className="text-xs">{p.supplier.name}</span>
                                                        ) : (
                                                            <span className="text-muted-foreground text-xs">—</span>
                                                        )}
                                                    </TableCell>

                                                    <TableCell className="font-mono text-xs">
                                                        {p.supplier_id && p.invoice_id ? (
                                                            <Link
                                                                href={`/suppliers/${p.supplier_id}/invoices/${p.invoice_id}/receipt`}
                                                                className="text-blue-700 hover:underline"
                                                            >
                                                                {p.invoice_number || `P-${p.id}`}
                                                            </Link>
                                                        ) : (
                                                            p.invoice_number || '—'
                                                        )}
                                                    </TableCell>

                                                    <TableCell>
                                                        <span
                                                            className={`inline-flex rounded border px-2 py-0.5 text-[10px] font-semibold ${
                                                                paymentStatusColors[status] || 'border-gray-300 bg-gray-100 text-gray-800'
                                                            }`}
                                                        >
                                                            {paymentStatusLabels[status] || status}
                                                        </span>
                                                    </TableCell>

                                                    <TableCell className="bg-blue-50 text-center">
                                                        {toNum(p.usd_to_afn_rate) > 0 || rate > 0 ? (
                                                            <div className="flex flex-col items-center gap-0.5">
                                                                <span className="font-mono text-sm font-bold text-blue-800">
                                                                    USD {formatRate(toNum(p.usd_to_afn_rate) || rate)}
                                                                </span>
                                                                {toNum(p.aed_to_afn_rate) > 0 ? (
                                                                    <span className="font-mono text-[10px] text-blue-700">
                                                                        AED {formatRate(p.aed_to_afn_rate)}
                                                                    </span>
                                                                ) : null}
                                                                <span className="text-[10px] text-blue-600">
                                                                    historical · {p.rate_source || 'snapshot'}
                                                                </span>
                                                            </div>
                                                        ) : (
                                                            <span className="text-muted-foreground text-xs">—</span>
                                                        )}
                                                    </TableCell>

                                                    <TableCell className="text-center font-mono text-xs">
                                                        {p.quantity}
                                                        {p.remaining_quantity > 0 && p.remaining_quantity !== p.quantity && (
                                                            <div className="text-muted-foreground text-[10px]">({p.remaining_quantity} left)</div>
                                                        )}
                                                    </TableCell>

                                                    <TableCell className="text-right font-mono text-xs">
                                                        {p.purchase_unit_amount != null ? (
                                                            <div className="space-y-0.5">
                                                                <div className="font-semibold">
                                                                    {formatPrice(p.purchase_unit_amount)}{' '}
                                                                    <span className="text-muted-foreground">{p.purchase_currency_code || '—'}</span>
                                                                </div>
                                                                <div className="text-muted-foreground text-[10px]">
                                                                    {formatPrice(toNum(p.unit_amount_usd))} USD · {formatPrice(toNum(p.unit_amount_aed))}{' '}
                                                                    AED
                                                                </div>
                                                                <div className="text-[10px] text-blue-700">
                                                                    {formatPrice(
                                                                        p.quantity > 0 ? toNum(p.line_total) / p.quantity : toNum(p.purchase_price_afn),
                                                                    )}{' '}
                                                                    AFN
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            formatPrice(p.purchase_price_afn)
                                                        )}
                                                    </TableCell>

                                                    <TableCell className="text-right font-mono text-xs font-semibold">
                                                        <div className="space-y-0.5">
                                                            <div>
                                                                {formatPrice(p.purchase_line_amount ?? p.line_total)}{' '}
                                                                <span className="text-muted-foreground font-normal">
                                                                    {p.purchase_currency_code || 'AFN'}
                                                                </span>
                                                            </div>
                                                            <div className="text-muted-foreground text-[10px] font-normal">
                                                                {formatPrice(p.line_total)} AFN
                                                            </div>
                                                            {(toNum(p.line_amount_usd) > 0 || toNum(p.line_amount_aed) > 0) && (
                                                                <div className="text-muted-foreground text-[10px] font-normal">
                                                                    {formatPrice(toNum(p.line_amount_usd))} USD ·{' '}
                                                                    {formatPrice(toNum(p.line_amount_aed))} AED
                                                                </div>
                                                            )}
                                                        </div>
                                                    </TableCell>

                                                    {/* ✅ Investor profit — original purchase/profit currency + AFN ledger */}
                                                    <TableCell className="bg-blue-50 text-right font-mono text-xs">
                                                        {investorProfit > 0 || profitUnitOriginal > 0 ? (
                                                            <div className="space-y-0.5">
                                                                <span className="font-semibold text-blue-700">
                                                                    {formatPrice(profitLineOriginal)} {profitCode}
                                                                </span>
                                                                <div className="text-[10px] text-blue-600">
                                                                    {formatPrice(profitUnitOriginal)} {profitCode} / unit
                                                                </div>
                                                                <div className="text-muted-foreground text-[10px]">
                                                                    {formatPrice(investorProfit)} AFN ledger
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <span className="text-muted-foreground">—</span>
                                                        )}
                                                    </TableCell>

                                                    {/* ✅ Store profit */}
                                                    <TableCell className="bg-emerald-50 text-right font-mono text-xs">
                                                        {storeProfit > 0 ? (
                                                            <span className="font-semibold text-emerald-700">{formatPrice(storeProfit)}</span>
                                                        ) : (
                                                            <span className="text-muted-foreground">—</span>
                                                        )}
                                                    </TableCell>

                                                    <TableCell className="text-right font-mono text-xs">
                                                        {formatPrice(p.invoice_paid_afn || 0)}
                                                    </TableCell>

                                                    <TableCell className="text-right font-mono text-xs font-semibold">
                                                        {toNum(p.invoice_due_afn) > 0 ? (
                                                            <span className="text-red-700">{formatPrice(p.invoice_due_afn)}</span>
                                                        ) : (
                                                            <span className="text-muted-foreground">0</span>
                                                        )}
                                                    </TableCell>

                                                    <TableCell className="text-xs">{formatDate(p.purchase_date)}</TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>

                            {/* Summary Row */}
                            <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                                <div className="bg-muted/30 rounded-lg border p-3">
                                    <div className="flex items-center gap-2">
                                        <DollarSign className="h-4 w-4 text-slate-600" />
                                        <span className="text-sm font-semibold">Total Cost</span>
                                    </div>
                                    <div className="mt-1 font-mono text-lg font-bold">{formatPrice(totalCostAfn)} AFN</div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">Unit price × quantity</p>
                                </div>
                                <div className="rounded-lg border bg-blue-50/40 p-3">
                                    <div className="flex items-center gap-2">
                                        <Wallet className="h-4 w-4 text-blue-600" />
                                        <span className="text-sm font-semibold text-blue-900">Investor Profit</span>
                                    </div>
                                    <div className="mt-1 font-mono text-lg font-bold text-blue-700">{formatPrice(totalInvestorProfitAfn)} AFN</div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                        {investorPurchases.length} investor line
                                        {investorPurchases.length !== 1 ? 's' : ''}
                                    </p>
                                </div>
                                <div className="rounded-lg border bg-emerald-50/40 p-3">
                                    <div className="flex items-center gap-2">
                                        <Store className="h-4 w-4 text-emerald-600" />
                                        <span className="text-sm font-semibold text-emerald-900">Store Profit</span>
                                    </div>
                                    <div className="mt-1 font-mono text-lg font-bold text-emerald-700">{formatPrice(totalStoreProfitAfn)} AFN</div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">{storePurchases.length} store + rest from investor</p>
                                </div>
                                <div className="bg-muted/30 rounded-lg border p-3">
                                    <div className="flex items-center gap-2">
                                        <Receipt className="h-4 w-4 text-amber-600" />
                                        <span className="text-sm font-semibold">Payments</span>
                                    </div>
                                    <div className="mt-1 font-mono text-sm font-bold">
                                        <span className="text-amber-700">{formatPrice(totalPaid)} paid</span>
                                    </div>
                                    <div className="font-mono text-sm font-bold">
                                        <span className={totalDue > 0 ? 'text-red-700' : 'text-muted-foreground'}>{formatPrice(totalDue)} due</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {purchases.length === 0 && (
                    <Card>
                        <CardContent className="py-8 text-center">
                            <Receipt className="text-muted-foreground mx-auto h-8 w-8" />
                            <p className="text-muted-foreground mt-2 text-sm">No purchases recorded for this product.</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
