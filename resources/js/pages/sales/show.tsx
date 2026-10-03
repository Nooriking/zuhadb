// resources/js/pages/sales/show.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText, Package, Printer, TrendingUp, User } from 'lucide-react';

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

interface Payment {
    id: number;
    sale_id: number;
    customer_id: number | null;
    amount_afn: string;
    remaining_due_afn: string;
    payment_method: string;
    payment_method_label?: string;
    reference_number: string | null;
    notes: string | null;
    paid_at: string;
    received_by: number | null;
    receiver: { id: number; name: string } | null;
    customer: { id: number; name: string; phone: string | null } | null;
}

interface SaleItem {
    id: number;
    variant_id: number;
    quantity: number;
    cost_price_afn: string;
    sale_price_afn: string;
    discount_afn: string;
    line_total_afn: string;
    store_profit_afn: string;
    investor_profit_afn: string;
    product_name_snapshot: string | null;
    barcode_snapshot: string | null;
    variant?: {
        id: number;
        name: string | null;
        volume_ml: number | null;
        concentration: string | null;
        product: { id: number; name: string; brand: { id: number; name: string } | null } | null;
    };
    batch?: { id: number; batch_code: string; investor_id: number | null; investor?: { id: number; full_name: string } | null } | null;
}

interface Sale {
    id: number;
    invoice_number: string;
    receipt_no: string | null;
    sale_date: string;
    status: 'draft' | 'completed' | 'cancelled';
    payment_status: 'unpaid' | 'partial' | 'paid';
    payment_method: string;
    payment_method_label?: string;

    subtotal_afn: string;
    discount_afn: string;
    total_afn: string;

    previous_due_afn: string;
    new_sale_amount_afn: string;
    grand_total_due_afn: string;
    received_now_afn: string;
    remaining_due_afn: string;

    paid_afn: string;
    due_afn: string;

    total_cost_afn: string;
    total_profit_afn: string;
    total_investor_profit_afn: string;
    store_profit_afn: string;

    printed_count: number;
    last_printed_at: string | null;

    notes: string | null;
    created_at: string;

    branch: { id: number; name: string; code: string } | null;
    customer: Customer | null;
    creator: { id: number; name: string; email: string } | null;
    items: SaleItem[];
    payments: Payment[];
    receipts?: any[];
}

interface Props {
    sale: Sale;
    canEdit?: boolean;
    canEditCompleted?: boolean;
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

const getPaymentBadge = (status: string) => {
    const map: Record<string, { variant: 'destructive' | 'secondary' | 'success'; label: string }> = {
        unpaid: { variant: 'destructive', label: 'Unpaid' },
        partial: { variant: 'secondary', label: 'Partial' },
        paid: { variant: 'success', label: 'Paid' },
    };
    const item = map[status] || map.unpaid;
    return <Badge variant={item.variant}>{item.label}</Badge>;
};

const getStatusBadge = (status: string) => {
    const map: Record<string, { variant: 'outline' | 'success' | 'destructive'; label: string }> = {
        draft: { variant: 'outline', label: 'Draft' },
        completed: { variant: 'success', label: 'Completed' },
        cancelled: { variant: 'destructive', label: 'Cancelled' },
    };
    const item = map[status] || map.draft;
    return <Badge variant={item.variant}>{item.label}</Badge>;
};

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function SalesShow({ sale }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Sales', href: '/sales' },
        { title: sale.invoice_number, href: `/sales/${sale.id}` },
    ];

    const previousDue = parseFloat(sale.previous_due_afn || '0');
    const newSale = parseFloat(sale.new_sale_amount_afn || sale.total_afn || '0');
    const grandTotal = parseFloat(sale.grand_total_due_afn || sale.total_afn || '0');
    const paidAfn = parseFloat(sale.paid_afn || '0');
    const remaining = parseFloat(sale.remaining_due_afn || '0');

    /* ═══════════════════════════════════════════════════════
     |  Handlers
     ═══════════════════════════════════════════════════════ */

    const handlePrint = () => {
        window.open(`/sales/${sale.id}/receipt`, '_blank', 'noopener,noreferrer');
    };

    /* ═══════════════════════════════════════════════════════
     |  Render
     ═══════════════════════════════════════════════════════ */

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Sale ${sale.invoice_number}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col items-start justify-between gap-3 lg:flex-row lg:items-center">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 flex h-12 w-12 items-center justify-center rounded-full">
                            <Package className="text-primary h-6 w-6" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{sale.invoice_number}</h1>
                            <div className="mt-1 flex items-center gap-2">
                                {getStatusBadge(sale.status)}
                                {getPaymentBadge(sale.payment_status)}
                                {sale.printed_count > 0 && (
                                    <Badge variant="outline" className="text-[10px]">
                                        🖨️ Printed {sale.printed_count}×
                                    </Badge>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={handlePrint} className="gap-2">
                            <Printer className="h-4 w-4" /> Print
                        </Button>

                        {/* ✅ دکمه Add Payment حذف شد */}

                        <Link href="/sales">
                            <Button variant="outline" className="gap-2">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* ═══════════ Due Flow Hero ═══════════ */}
                <Card>
                    <CardContent className="p-6">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                            {previousDue > 0 && (
                                <div>
                                    <p className="text-muted-foreground text-xs uppercase">Previous Due</p>
                                    <p className="text-foreground mt-1 font-mono text-2xl font-bold">
                                        {formatPrice(previousDue)} <span className="text-sm">AFN</span>
                                    </p>
                                </div>
                            )}
                            <div>
                                <p className="text-muted-foreground text-xs uppercase">This Sale</p>
                                <p className="mt-1 font-mono text-2xl font-bold">
                                    {formatPrice(newSale)} <span className="text-sm">AFN</span>
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs uppercase">Grand Total</p>
                                <p className="mt-1 font-mono text-2xl font-bold">
                                    {formatPrice(grandTotal)} <span className="text-sm">AFN</span>
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs uppercase">Paid</p>
                                <p className="text-foreground mt-1 font-mono text-2xl font-bold">
                                    {formatPrice(paidAfn)} <span className="text-sm">AFN</span>
                                </p>
                            </div>
                            <div className="col-span-2 lg:col-span-4">
                                <Separator />
                                <div className="mt-3 flex items-center justify-between">
                                    <span className="text-sm font-semibold">Remaining Due:</span>
                                    <span className="font-mono text-3xl font-bold">{formatPrice(remaining)} AFN</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ Info Cards ═══════════ */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {/* Customer */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <User className="h-4 w-4" /> Customer
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {sale.customer ? (
                                <>
                                    <Link href={`/customers/${sale.customer.id}`} className="hover:text-primary text-lg font-semibold">
                                        {sale.customer.name}
                                    </Link>
                                    {sale.customer.phone && (
                                        <div className="text-muted-foreground" dir="ltr">
                                            {sale.customer.phone}
                                        </div>
                                    )}
                                    {sale.customer.address && <div className="text-muted-foreground text-xs">{sale.customer.address}</div>}
                                    <Separator />
                                    <div className="grid grid-cols-2 gap-1 text-xs">
                                        <span className="text-muted-foreground">Current Due:</span>
                                        <span className="text-foreground text-right font-mono font-semibold">
                                            {formatPrice(sale.customer.current_due_afn)} AFN
                                        </span>
                                        {parseFloat(sale.customer.credit_limit_afn) > 0 && (
                                            <>
                                                <span className="text-muted-foreground">Credit Limit:</span>
                                                <span className="text-right font-mono">{formatPrice(sale.customer.credit_limit_afn)} AFN</span>
                                            </>
                                        )}
                                    </div>
                                    <div className="pt-2">
                                        <Link href={`/customers/${sale.customer.id}`}>
                                            <Button variant="outline" size="sm" className="w-full">
                                                View Customer & Pay Dues
                                            </Button>
                                        </Link>
                                    </div>
                                </>
                            ) : (
                                <p className="text-muted-foreground">Walk-in Customer</p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Sale Info */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <FileText className="h-4 w-4" /> Sale Info
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="grid grid-cols-2 gap-1">
                                <span className="text-muted-foreground">Branch:</span>
                                <span className="text-right">
                                    {sale.branch?.name} ({sale.branch?.code})
                                </span>

                                <span className="text-muted-foreground">Date:</span>
                                <span className="text-right">{new Date(sale.sale_date).toLocaleDateString('en-US')}</span>

                                <span className="text-muted-foreground">Method:</span>
                                <span className="text-right">{sale.payment_method_label || sale.payment_method}</span>

                                {sale.receipt_no && (
                                    <>
                                        <span className="text-muted-foreground">Receipt No:</span>
                                        <span className="text-right font-mono text-xs">{sale.receipt_no}</span>
                                    </>
                                )}

                                {sale.creator && (
                                    <>
                                        <span className="text-muted-foreground">Created By:</span>
                                        <span className="text-right">{sale.creator.name}</span>
                                    </>
                                )}
                            </div>

                            {sale.notes && (
                                <>
                                    <Separator />
                                    <div>
                                        <p className="text-muted-foreground mb-1 text-xs">Notes:</p>
                                        <p className="text-xs">{sale.notes}</p>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Profit */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <TrendingUp className="h-4 w-4" /> Profit
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="grid grid-cols-2 gap-1">
                                <span className="text-muted-foreground">Total Cost:</span>
                                <span className="text-right font-mono text-xs">{formatPrice(sale.total_cost_afn)} AFN</span>

                                <span className="text-muted-foreground">Total Profit:</span>
                                <span className="text-foreground text-right font-mono font-semibold">{formatPrice(sale.total_profit_afn)} AFN</span>

                                <span className="text-muted-foreground">Investor Share:</span>
                                <span className="text-muted-foreground text-right font-mono text-xs">
                                    {formatPrice(sale.total_investor_profit_afn)} AFN
                                </span>

                                <span className="text-muted-foreground">Store Share:</span>
                                <span className="text-muted-foreground text-right font-mono text-xs">{formatPrice(sale.store_profit_afn)} AFN</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* ═══════════ Items Table ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="h-5 w-5" /> Items
                            <Badge variant="secondary">{sale.items.length}</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">#</TableHead>
                                        <TableHead>Product</TableHead>
                                        <TableHead>Barcode</TableHead>
                                        <TableHead className="text-center">Qty</TableHead>
                                        <TableHead className="text-right">Cost</TableHead>
                                        <TableHead className="text-right">Price</TableHead>
                                        <TableHead className="text-right">Discount</TableHead>
                                        <TableHead className="text-right">Total</TableHead>
                                        <TableHead className="text-right">Profit</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sale.items.map((item, idx) => (
                                        <TableRow key={item.id}>
                                            <TableCell>{idx + 1}</TableCell>
                                            <TableCell>
                                                <div className="text-sm font-medium">
                                                    {item.product_name_snapshot || item.variant?.product?.name || '—'}
                                                </div>
                                                <div className="text-muted-foreground text-xs">
                                                    {[
                                                        item.variant?.volume_ml ? `${item.variant.volume_ml}ml` : null,
                                                        item.variant?.concentration?.toUpperCase() || null,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ') || ''}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {item.barcode_snapshot ? (
                                                    <span className="font-mono text-xs">{item.barcode_snapshot}</span>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-center font-mono">{item.quantity}</TableCell>
                                            <TableCell className="text-right font-mono text-xs">{formatPrice(item.cost_price_afn)}</TableCell>
                                            <TableCell className="text-right font-mono text-xs">{formatPrice(item.sale_price_afn)}</TableCell>
                                            <TableCell className="text-foreground text-right font-mono text-xs">
                                                {parseFloat(item.discount_afn) > 0 ? `-${formatPrice(item.discount_afn)}` : '—'}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-sm font-semibold">
                                                {formatPrice(item.line_total_afn)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="text-foreground font-mono text-xs font-semibold">
                                                    {formatPrice(item.store_profit_afn)}
                                                </div>
                                                {parseFloat(item.investor_profit_afn) > 0 && (
                                                    <div className="text-muted-foreground font-mono text-[10px]">
                                                        Inv: {formatPrice(item.investor_profit_afn)}
                                                    </div>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Totals */}
                        <div className="bg-muted/30 mt-4 ml-auto max-w-sm rounded-lg border p-4">
                            <div className="grid grid-cols-2 gap-y-2 text-sm">
                                <span className="text-muted-foreground">Subtotal:</span>
                                <span className="text-right font-mono">{formatPrice(sale.subtotal_afn)} AFN</span>

                                <span className="text-muted-foreground">Discount:</span>
                                <span className="text-foreground text-right font-mono">-{formatPrice(sale.discount_afn)} AFN</span>

                                <span className="col-span-2 my-1 border-t" />

                                <span className="font-semibold">Total:</span>
                                <span className="text-right font-mono font-semibold">{formatPrice(sale.total_afn)} AFN</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ Payments Table — نمایش فقط (بدون دکمه Add) ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="h-5 w-5" /> Payment History
                            <Badge variant="secondary">{sale.payments.length}</Badge>
                        </CardTitle>
                        <CardDescription>All payments recorded for this sale. To record a new payment, go to the customer page.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">#</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Method</TableHead>
                                        <TableHead>Reference</TableHead>
                                        <TableHead className="text-right">Amount (AFN)</TableHead>
                                        <TableHead className="text-right">Remaining Due</TableHead>
                                        <TableHead>Received By</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sale.payments.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={8} className="text-muted-foreground h-20 text-center">
                                                No payments recorded yet for this sale.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        sale.payments.map((p, idx) => {
                                            const pRemaining = parseFloat(p.remaining_due_afn || '0');
                                            return (
                                                <TableRow key={p.id}>
                                                    <TableCell>{idx + 1}</TableCell>
                                                    <TableCell className="text-xs">{formatDateTime(p.paid_at)}</TableCell>
                                                    <TableCell>
                                                        {p.customer ? (
                                                            <Link
                                                                href={`/customers/${p.customer.id}`}
                                                                className="hover:text-primary text-sm font-medium"
                                                            >
                                                                {p.customer.name}
                                                            </Link>
                                                        ) : (
                                                            <span className="text-muted-foreground text-xs">—</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant="outline">{p.payment_method_label || p.payment_method}</Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        {p.reference_number ? (
                                                            <span className="font-mono text-xs">{p.reference_number}</span>
                                                        ) : (
                                                            <span className="text-muted-foreground">—</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-foreground text-right font-mono text-sm font-semibold">
                                                        {formatPrice(p.amount_afn)}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <span className="font-mono text-sm font-bold">{formatPrice(pRemaining)} AFN</span>
                                                    </TableCell>
                                                    <TableCell className="text-xs">{p.receiver?.name || '—'}</TableCell>
                                                </TableRow>
                                            );
                                        })
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Payment Summary */}
                        <div className="mt-4 rounded-lg border-2 border-emerald-300 bg-emerald-50 p-4">
                            <div className="grid grid-cols-2 gap-y-2 text-sm">
                                {previousDue > 0 && (
                                    <>
                                        <span className="text-gray-600">Previous Due:</span>
                                        <span className="text-foreground text-right font-mono font-semibold">{formatPrice(previousDue)} AFN</span>

                                        <span className="text-gray-600">This Sale:</span>
                                        <span className="text-right font-mono font-semibold">{formatPrice(newSale)} AFN</span>

                                        <span className="col-span-2 my-1 border-t border-emerald-300" />
                                    </>
                                )}

                                <span className="font-bold text-gray-700">Grand Total:</span>
                                <span className="text-right font-mono font-bold">{formatPrice(grandTotal)} AFN</span>

                                <span className="text-gray-600">Total Paid:</span>
                                <span className="text-foreground text-right font-mono font-semibold">{formatPrice(paidAfn)} AFN</span>

                                <span className="col-span-2 my-1 border-t border-emerald-300" />

                                <span className="font-bold text-gray-700">Remaining Due:</span>
                                <span className="text-right font-mono font-bold">{formatPrice(remaining)} AFN</span>
                            </div>

                            {/* ✅ راهنما برای پرداخت */}
                            {remaining > 0.01 && sale.customer && (
                                <div className="mt-4 rounded-lg border border-blue-300 bg-blue-50 p-3 text-sm text-blue-800">
                                    <p className="font-semibold">💡 To record a payment for this due:</p>
                                    <p className="mt-1">
                                        Go to{' '}
                                        <Link href={`/customers/${sale.customer.id}`} className="font-semibold underline">
                                            {sale.customer.name}'s page
                                        </Link>{' '}
                                        → Add Payment
                                    </p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* ✅ Modal پرداخت و دکمه Add Payment کاملاً حذف شدند */}
        </AppLayout>
    );
}
