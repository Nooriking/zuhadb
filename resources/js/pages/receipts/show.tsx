// resources/js/pages/receipts/show.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Calendar, DollarSign, FileText, Printer, Receipt as ReceiptIcon, User, Wallet } from 'lucide-react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Receipt {
    id: number;
    receipt_number: string;
    receipt_type: 'sale_invoice' | 'payment_receipt';
    amount_afn: string;
    remaining_due_afn: string;
    notes: string | null;
    created_at: string;
    customer: {
        id: number;
        name: string;
        phone: string | null;
        address: string | null;
        current_due_afn: string;
        credit_limit_afn: string;
    } | null;
    sale: {
        id: number;
        invoice_number: string;
        sale_date: string;
        total_afn: string;
        paid_afn: string;
        remaining_due_afn: string;
        payment_status: string;
    } | null;
    sale_payment: {
        id: number;
        amount_afn: string;
        remaining_due_afn: string;
        payment_method: string;
        paid_at: string;
    } | null;
    creator: { id: number; name: string; email: string } | null;
}

interface Props {
    receipt: Receipt;
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

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function ReceiptShow({ receipt }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Receipts', href: '/receipts' },
        { title: receipt.receipt_number, href: `/receipts/${receipt.id}` },
    ];

    const isInvoice = receipt.receipt_type === 'sale_invoice';
    const amount = parseFloat(receipt.amount_afn || '0');
    const remaining = parseFloat(receipt.remaining_due_afn || '0');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Receipt ${receipt.receipt_number}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-3">
                        <div className={`flex h-12 w-12 items-center justify-center rounded-full ${isInvoice ? 'bg-indigo-100' : 'bg-green-100'}`}>
                            <ReceiptIcon className="text-foreground h-6 w-6" />
                        </div>
                        <div>
                            <h1 className="font-mono text-2xl font-bold tracking-tight">{receipt.receipt_number}</h1>
                            <p className="text-muted-foreground text-sm">{isInvoice ? 'Sale Invoice' : 'Payment Receipt'}</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button onClick={() => window.open(`/receipts/${receipt.id}/print`, '_blank', 'noopener,noreferrer')} className="gap-2">
                            <Printer className="h-4 w-4" /> Print
                        </Button>
                        <Link href="/receipts">
                            <Button variant="outline" className="gap-2">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* ═══════════ Amount Hero ═══════════ */}
                <Card className={`border-2 ${isInvoice ? 'border-indigo-300 bg-indigo-50' : 'border-green-300 bg-green-50'}`}>
                    <CardContent className="p-6">
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                            <div>
                                <p className="text-muted-foreground text-xs uppercase">Type</p>
                                <div className="mt-2">
                                    <Badge variant={isInvoice ? 'default' : 'success'}>{isInvoice ? 'Sale Invoice' : 'Payment Receipt'}</Badge>
                                </div>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs uppercase">Amount</p>
                                <p className="mt-2 font-mono text-3xl font-bold text-foreground">
                                    {formatPrice(amount)} <span className="text-base">AFN</span>
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs uppercase">Remaining After</p>
                                <p className="mt-2 font-mono text-3xl font-bold">
                                    {formatPrice(remaining)} <span className="text-base">AFN</span>
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ Details Grid ═══════════ */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Customer */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <User className="h-4 w-4" /> Customer
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {receipt.customer ? (
                                <>
                                    <Link href={`/customers/${receipt.customer.id}`} className="hover:text-primary text-lg font-semibold">
                                        {receipt.customer.name}
                                    </Link>
                                    {receipt.customer.phone && (
                                        <div className="text-muted-foreground" dir="ltr">
                                            {receipt.customer.phone}
                                        </div>
                                    )}
                                    {receipt.customer.address && <div className="text-muted-foreground text-xs">{receipt.customer.address}</div>}
                                    <Separator />
                                    <div className="grid grid-cols-2 gap-1 text-xs">
                                        <span className="text-muted-foreground">Current Due:</span>
                                        <span className="text-right font-mono font-semibold text-foreground">
                                            {formatPrice(receipt.customer.current_due_afn)} AFN
                                        </span>
                                        {parseFloat(receipt.customer.credit_limit_afn) > 0 && (
                                            <>
                                                <span className="text-muted-foreground">Credit Limit:</span>
                                                <span className="text-right font-mono">{formatPrice(receipt.customer.credit_limit_afn)} AFN</span>
                                            </>
                                        )}
                                    </div>
                                </>
                            ) : (
                                <p className="text-muted-foreground">Walk-in Customer</p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Receipt Info */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <FileText className="h-4 w-4" /> Receipt Info
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="grid grid-cols-2 gap-1">
                                <span className="text-muted-foreground">Receipt #:</span>
                                <span className="text-right font-mono text-xs">{receipt.receipt_number}</span>

                                <span className="text-muted-foreground flex items-center gap-1">
                                    <Calendar className="h-3.5 w-3.5" /> Date:
                                </span>
                                <span className="text-right text-xs">{formatDateTime(receipt.created_at)}</span>

                                {receipt.creator && (
                                    <>
                                        <span className="text-muted-foreground">Created By:</span>
                                        <span className="text-right text-xs">{receipt.creator.name}</span>
                                    </>
                                )}
                            </div>

                            {receipt.notes && (
                                <>
                                    <Separator />
                                    <div>
                                        <p className="text-muted-foreground mb-1 text-xs">Notes:</p>
                                        <p className="text-xs">{receipt.notes}</p>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* ═══════════ Related Sale / Payment ═══════════ */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {receipt.sale && (
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <DollarSign className="h-4 w-4" /> Related Sale
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <div className="grid grid-cols-2 gap-1">
                                    <span className="text-muted-foreground">Invoice #:</span>
                                    <Link href={`/sales/${receipt.sale.id}`} className="hover:text-primary text-right font-mono text-xs">
                                        {receipt.sale.invoice_number}
                                    </Link>

                                    <span className="text-muted-foreground">Sale Date:</span>
                                    <span className="text-right text-xs">{new Date(receipt.sale.sale_date).toLocaleDateString('en-US')}</span>

                                    <span className="text-muted-foreground">Total:</span>
                                    <span className="text-right font-mono text-xs">{formatPrice(receipt.sale.total_afn)} AFN</span>

                                    <span className="text-muted-foreground">Paid:</span>
                                    <span className="text-right font-mono text-xs text-foreground">{formatPrice(receipt.sale.paid_afn)} AFN</span>

                                    <span className="text-muted-foreground">Remaining:</span>
                                    <span
                                        className="text-right font-mono text-xs font-semibold"
                                    >
                                        {formatPrice(receipt.sale.remaining_due_afn)} AFN
                                    </span>
                                </div>

                                <Separator />

                                <Link href={`/sales/${receipt.sale.id}`}>
                                    <Button variant="outline" className="w-full gap-2" size="sm">
                                        <FileText className="h-4 w-4" /> View Sale
                                    </Button>
                                </Link>
                            </CardContent>
                        </Card>
                    )}

                    {receipt.sale_payment && (
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Wallet className="h-4 w-4" /> Payment Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <div className="grid grid-cols-2 gap-1">
                                    <span className="text-muted-foreground">Amount:</span>
                                    <span className="text-right font-mono font-semibold text-foreground">
                                        {formatPrice(receipt.sale_payment.amount_afn)} AFN
                                    </span>

                                    <span className="text-muted-foreground">Method:</span>
                                    <span className="text-right">
                                        <Badge variant="outline">{receipt.sale_payment.payment_method}</Badge>
                                    </span>

                                    <span className="text-muted-foreground">Paid At:</span>
                                    <span className="text-right text-xs">{formatDateTime(receipt.sale_payment.paid_at)}</span>

                                    <span className="text-muted-foreground">Remaining After:</span>
                                    <span className="text-right font-mono text-xs">{formatPrice(receipt.sale_payment.remaining_due_afn)} AFN</span>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
