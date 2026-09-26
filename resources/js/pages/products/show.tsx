// resources/js/pages/products/show.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Barcode as BarcodeIcon, Calendar, DollarSign, Edit, Package, Receipt, TrendingUp, User } from 'lucide-react';

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
    purchase_price_afn: string;
    sale_price_afn: string;
    wholesale_price_afn: string;
    wholesale_min_qty: number;
    minimum_stock: number;
    profit_per_unit: string;
    status: 'active' | 'inactive';
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
    line_total: string;
    line_profit: string;
    purchase_price_afn: string;
    purchase_price_usd: string;
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

interface Props {
    product: Product;
    purchases?: Purchase[];
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
    paid: 'bg-green-100 text-green-800',
    unpaid: 'bg-red-100 text-red-800',
    partial: 'bg-yellow-100 text-yellow-800',
    credit: 'bg-amber-100 text-amber-800',
};

const paymentStatusLabels: Record<string, string> = {
    paid: 'Paid',
    unpaid: 'Unpaid',
    partial: 'Partial',
    credit: 'Credit',
};

const LOW_STOCK_THRESHOLD = 5;

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const formatPrice = (value: string | number | null | undefined) => {
    const num = typeof value === 'string' ? parseFloat(value) : Number(value ?? 0);
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(num || 0);
};

const formatDate = (dateStr: string | null | undefined) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const getVariantDisplayName = (v: ProductVariant | undefined): string => {
    if (!v) return '—';
    const parts: string[] = [];
    if (v.volume_ml) parts.push(`${v.volume_ml}ml`);
    if (v.concentration) parts.push(concentrationLabels[v.concentration] || v.concentration);
    return parts.join(' · ') || `Variant #${v.id}`;
};

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function ProductShow({ product, purchases = [] }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Products', href: '/products' },
        { title: product.name || `Product #${product.id}`, href: `/products/${product.id}` },
    ];

    // ═══════════ Derived totals ═══════════
    const totalStock = product.variants.reduce((sum, v) => sum + (v.stock_quantity ?? 0), 0);
    const totalBarcodes = product.variants.reduce((sum, v) => sum + (v.barcodes?.length ?? 0), 0);
    const totalPurchaseAfn = purchases.reduce((sum, p) => sum + parseFloat(p.purchase_price_afn || '0'), 0);
    const totalProfitAfn = purchases.reduce((sum, p) => sum + parseFloat(p.line_profit || '0'), 0);
    const totalPurchasedQty = purchases.reduce((sum, p) => sum + (p.quantity ?? 0), 0);

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
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
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
                </div>

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
                                        {/* Variant Header */}
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

                                        {/* Prices Grid */}
                                        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                            <div className="bg-muted/30 rounded-md p-2">
                                                <div className="text-muted-foreground text-xs">Purchase Price</div>
                                                <div className="font-mono text-sm font-semibold">
                                                    {formatPrice(v.purchase_price_afn)}
                                                    <span className="text-muted-foreground ml-1 text-xs">AFN</span>
                                                </div>
                                            </div>
                                            <div className="bg-muted/30 rounded-md p-2">
                                                <div className="text-muted-foreground text-xs">Sale Price</div>
                                                <div className="font-mono text-sm font-semibold text-green-700">
                                                    {formatPrice(v.sale_price_afn)}
                                                    <span className="text-muted-foreground ml-1 text-xs">AFN</span>
                                                </div>
                                            </div>
                                            <div className="bg-muted/30 rounded-md p-2">
                                                <div className="text-muted-foreground text-xs">Wholesale Price</div>
                                                <div className="font-mono text-sm font-semibold">
                                                    {formatPrice(v.wholesale_price_afn)}
                                                    <span className="text-muted-foreground ml-1 text-xs">AFN</span>
                                                </div>
                                                {v.wholesale_min_qty > 0 && (
                                                    <div className="text-muted-foreground text-[10px]">Min qty: {v.wholesale_min_qty}</div>
                                                )}
                                            </div>
                                            <div className="bg-muted/30 rounded-md p-2">
                                                <div className="text-muted-foreground text-xs">Profit / Unit</div>
                                                <div className="font-mono text-sm font-semibold text-emerald-700">
                                                    {formatPrice(v.profit_per_unit)}
                                                    <span className="text-muted-foreground ml-1 text-xs">AFN</span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Barcodes */}
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
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <Receipt className="h-5 w-5" />
                                        Purchase History ({purchases.length})
                                    </CardTitle>
                                    <CardDescription>All purchases recorded for this product.</CardDescription>
                                </div>
                                <div className="flex gap-3">
                                    <div className="text-right">
                                        <div className="text-muted-foreground text-xs">Total Cost</div>
                                        <div className="font-mono text-sm font-bold">{formatPrice(totalPurchaseAfn)} AFN</div>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-muted-foreground text-xs">Total Profit</div>
                                        <div className="font-mono text-sm font-bold text-emerald-700">{formatPrice(totalProfitAfn)} AFN</div>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-muted-foreground text-xs">Total Qty</div>
                                        <div className="font-mono text-sm font-bold">{totalPurchasedQty}</div>
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
                                            <TableHead>Investor</TableHead>
                                            <TableHead>Supplier</TableHead>
                                            <TableHead>Invoice</TableHead>
                                            <TableHead className="text-right">Rate</TableHead>
                                            <TableHead className="text-center">Qty</TableHead>
                                            <TableHead className="text-right">Total</TableHead>
                                            <TableHead className="text-right">Paid</TableHead>
                                            <TableHead className="text-right">Due</TableHead>
                                            <TableHead>Date</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {purchases.map((p, idx) => (
                                            <TableRow key={p.id}>
                                                <TableCell className="font-mono text-xs">{idx + 1}</TableCell>
                                                <TableCell>
                                                    <div className="text-xs">{getVariantDisplayName(p.variant)}</div>
                                                </TableCell>
                                                <TableCell>
                                                    {p.investor ? (
                                                        <Badge variant="outline" className="gap-1">
                                                            <User className="h-3 w-3" />
                                                            {p.investor.full_name}
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-muted-foreground text-xs">Store</span>
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
                                                        <Link href={`/suppliers/${p.supplier_id}/invoices/${p.invoice_id}/receipt`} className="hover:underline">
                                                            {p.invoice_number || `P-${p.id}`}
                                                        </Link>
                                                    ) : (
                                                        p.invoice_number || '—'
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-xs">{p.exchange_rate || '—'}</TableCell>
                                                <TableCell className="text-center font-mono text-xs">{p.quantity}</TableCell>
                                                <TableCell className="text-right font-mono text-xs">{formatPrice(p.line_total)}</TableCell>
                                                <TableCell className="text-right font-mono text-xs">{formatPrice(p.invoice_paid_afn || 0)}</TableCell>
                                                <TableCell className="text-right font-mono text-xs font-semibold">{formatPrice(p.invoice_due_afn || 0)}</TableCell>
                                                <TableCell className="text-xs">{formatDate(p.purchase_date)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            {/* Summary Row */}
                            <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                <div className="rounded-lg border bg-emerald-50/40 p-3">
                                    <div className="flex items-center gap-2">
                                        <TrendingUp className="h-4 w-4 text-emerald-600" />
                                        <span className="text-sm font-semibold text-emerald-900">Total Profit</span>
                                    </div>
                                    <div className="mt-1 font-mono text-lg font-bold text-emerald-700">{formatPrice(totalProfitAfn)} AFN</div>
                                </div>
                                <div className="bg-muted/30 rounded-lg border p-3">
                                    <div className="flex items-center gap-2">
                                        <DollarSign className="h-4 w-4 text-blue-600" />
                                        <span className="text-sm font-semibold">Total Cost</span>
                                    </div>
                                    <div className="mt-1 font-mono text-lg font-bold">{formatPrice(totalPurchaseAfn)} AFN</div>
                                </div>
                                <div className="bg-muted/30 rounded-lg border p-3">
                                    <div className="flex items-center gap-2">
                                        <Package className="h-4 w-4 text-purple-600" />
                                        <span className="text-sm font-semibold">Total Quantity</span>
                                    </div>
                                    <div className="mt-1 font-mono text-lg font-bold">{totalPurchasedQty}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* No purchases message */}
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
