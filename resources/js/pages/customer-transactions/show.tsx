// resources/js/pages/customer-transactions/show.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowDownLeft, ArrowLeft, ArrowUpRight, BookOpen, Calendar, FileText, Hash, RefreshCw, TrendingDown, User } from 'lucide-react';

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
    customer_id: number;
    type: 'sale' | 'payment' | 'return' | 'adjustment';
    amount_afn: string;
    balance_after: string;
    reference_type: string | null;
    reference_id: number | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
    customer: Customer | null;
    creator: { id: number; name: string; email?: string } | null;
    reference?: any;
}

interface Props {
    transaction: Transaction;
    beforeTransactions: Transaction[];
    afterTransactions: Transaction[];
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

const TYPE_META: Record<
    string,
    {
        label: string;
        variant: 'success' | 'destructive' | 'secondary' | 'outline';
        icon: any;
        color: string;
        bg: string;
        border: string;
        description: string;
    }
> = {
    sale: {
        label: 'Sale',
        variant: 'destructive',
        icon: ArrowUpRight,
        color: 'text-red-600',
        bg: 'bg-red-50',
        border: 'border-red-300',
        description: 'New sale — customer due increased',
    },
    payment: {
        label: 'Payment',
        variant: 'success',
        icon: ArrowDownLeft,
        color: 'text-green-600',
        bg: 'bg-green-50',
        border: 'border-green-300',
        description: 'Customer payment — due decreased',
    },
    return: {
        label: 'Return',
        variant: 'secondary',
        icon: TrendingDown,
        color: 'text-amber-600',
        bg: 'bg-amber-50',
        border: 'border-amber-300',
        description: 'Return — due decreased',
    },
    adjustment: {
        label: 'Adjustment',
        variant: 'outline',
        icon: RefreshCw,
        color: 'text-blue-600',
        bg: 'bg-blue-50',
        border: 'border-blue-300',
        description: 'Manual adjustment by an admin',
    },
};

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function CustomerTransactionShow({ transaction, beforeTransactions, afterTransactions }: Props) {
    const meta = TYPE_META[transaction.type] || TYPE_META.adjustment;
    const Icon = meta.icon;
    const amount = parseFloat(transaction.amount_afn || '0');
    const balance = parseFloat(transaction.balance_after || '0');
    const customer = transaction.customer;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Customer Transactions', href: '/customer-transactions' },
        { title: `#${transaction.id}`, href: `/customer-transactions/${transaction.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Transaction #${transaction.id}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-3">
                        <div className={`flex h-12 w-12 items-center justify-center rounded-full ${meta.bg} ${meta.border} border-2`}>
                            <Icon className={`h-6 w-6 ${meta.color}`} />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Transaction #{transaction.id}</h1>
                            <p className="text-muted-foreground text-sm">{meta.description}</p>
                        </div>
                    </div>
                    <Link href="/customer-transactions">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Button>
                    </Link>
                </div>

                {/* ═══════════ Hero Amount Card ═══════════ */}
                <Card className={`border-2 ${meta.border} ${meta.bg}`}>
                    <CardContent className="p-6">
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                            <div>
                                <p className="text-muted-foreground text-xs uppercase">Type</p>
                                <div className="mt-2 flex items-center gap-2">
                                    <Icon className={`h-5 w-5 ${meta.color}`} />
                                    <Badge variant={meta.variant}>{meta.label}</Badge>
                                </div>
                            </div>

                            <div>
                                <p className="text-muted-foreground text-xs uppercase">Amount</p>
                                <p className={`mt-2 font-mono text-3xl font-bold ${amount > 0 ? 'text-red-700' : 'text-green-700'}`}>
                                    {amount > 0 ? '+' : ''}
                                    {formatPrice(amount)} <span className="text-base">AFN</span>
                                </p>
                            </div>

                            <div>
                                <p className="text-muted-foreground text-xs uppercase">Balance After</p>
                                <p className={`mt-2 font-mono text-3xl font-bold ${balance > 0 ? 'text-amber-700' : 'text-green-700'}`}>
                                    {formatPrice(balance)} <span className="text-base">AFN</span>
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ Customer + Details ═══════════ */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Customer */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <User className="h-5 w-5" />
                                Customer
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {customer ? (
                                <div className="space-y-3">
                                    <div>
                                        <Link href={`/customers/${customer.id}/transactions`} className="hover:text-primary text-lg font-semibold">
                                            {customer.name}
                                        </Link>
                                    </div>
                                    {customer.phone && (
                                        <div className="text-muted-foreground text-sm" dir="ltr">
                                            {customer.phone}
                                        </div>
                                    )}
                                    {customer.address && <div className="text-muted-foreground text-sm">{customer.address}</div>}
                                    <Separator />
                                    <div className="grid grid-cols-2 gap-2 text-sm">
                                        <span className="text-muted-foreground">Current Due:</span>
                                        <span className="text-right font-mono font-bold text-amber-700">
                                            {formatPrice(customer.current_due_afn)} AFN
                                        </span>

                                        <span className="text-muted-foreground">Credit Limit:</span>
                                        <span className="text-right font-mono">
                                            {parseFloat(customer.credit_limit_afn) > 0 ? `${formatPrice(customer.credit_limit_afn)} AFN` : '—'}
                                        </span>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">No customer linked.</p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="grid grid-cols-2 gap-2 text-sm">
                                <span className="text-muted-foreground flex items-center gap-1">
                                    <Calendar className="h-3.5 w-3.5" /> Created:
                                </span>
                                <span className="text-right font-mono text-xs">{formatDateTime(transaction.created_at)}</span>

                                {transaction.creator && (
                                    <>
                                        <span className="text-muted-foreground">Created By:</span>
                                        <span className="text-right text-xs">{transaction.creator.name}</span>
                                    </>
                                )}

                                {transaction.reference_type && (
                                    <>
                                        <span className="text-muted-foreground flex items-center gap-1">
                                            <Hash className="h-3.5 w-3.5" /> Reference:
                                        </span>
                                        <span className="text-right font-mono text-xs">
                                            {transaction.reference_type.split('\\').pop()} #{transaction.reference_id}
                                        </span>
                                    </>
                                )}
                            </div>

                            {transaction.reference_type && transaction.reference_id && (
                                <>
                                    <Separator />
                                    <Button variant="outline" className="w-full gap-2" asChild>
                                        <Link
                                            href={
                                                transaction.reference_type.includes('Sale') && !transaction.reference_type.includes('Payment')
                                                    ? `/sales/${transaction.reference_id}`
                                                    : '#'
                                            }
                                        >
                                            <FileText className="h-4 w-4" />
                                            View {transaction.reference_type.split('\\').pop()}
                                        </Link>
                                    </Button>
                                </>
                            )}

                            {transaction.notes && (
                                <>
                                    <Separator />
                                    <div>
                                        <p className="text-muted-foreground mb-1 text-xs">Notes:</p>
                                        <p className="text-sm">{transaction.notes}</p>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* ═══════════ Timeline: Before + After ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <BookOpen className="h-5 w-5" />
                            Ledger Timeline
                        </CardTitle>
                        <CardDescription>Transactions before and after this one for the same customer</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {/* Before */}
                            {beforeTransactions.length > 0 && (
                                <div>
                                    <p className="text-muted-foreground mb-2 text-xs font-semibold uppercase">Before</p>
                                    <div className="space-y-2">
                                        {beforeTransactions.reverse().map((t) => {
                                            const tMeta = TYPE_META[t.type] || TYPE_META.adjustment;
                                            const tAmount = parseFloat(t.amount_afn || '0');
                                            return (
                                                <Link
                                                    key={t.id}
                                                    href={`/customer-transactions/${t.id}`}
                                                    className="hover:bg-muted/50 flex items-center justify-between rounded-lg border p-2 transition"
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant={tMeta.variant} className="text-[10px]">
                                                            {tMeta.label}
                                                        </Badge>
                                                        <span className="text-muted-foreground text-xs">{formatDateTime(t.created_at)}</span>
                                                    </div>
                                                    <span className={`font-mono text-xs ${tAmount > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                                        {tAmount > 0 ? '+' : ''}
                                                        {formatPrice(tAmount)}
                                                    </span>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* Current */}
                            <div className="border-primary rounded-lg border-2 bg-blue-50 p-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Badge variant={meta.variant}>● Current</Badge>
                                        <span className="text-muted-foreground text-xs">{formatDateTime(transaction.created_at)}</span>
                                    </div>
                                    <span className={`font-mono text-sm font-bold ${amount > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                        {amount > 0 ? '+' : ''}
                                        {formatPrice(amount)}
                                    </span>
                                </div>
                            </div>

                            {/* After */}
                            {afterTransactions.length > 0 && (
                                <div>
                                    <p className="text-muted-foreground mb-2 text-xs font-semibold uppercase">After</p>
                                    <div className="space-y-2">
                                        {afterTransactions.map((t) => {
                                            const tMeta = TYPE_META[t.type] || TYPE_META.adjustment;
                                            const tAmount = parseFloat(t.amount_afn || '0');
                                            return (
                                                <Link
                                                    key={t.id}
                                                    href={`/customer-transactions/${t.id}`}
                                                    className="hover:bg-muted/50 flex items-center justify-between rounded-lg border p-2 transition"
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant={tMeta.variant} className="text-[10px]">
                                                            {tMeta.label}
                                                        </Badge>
                                                        <span className="text-muted-foreground text-xs">{formatDateTime(t.created_at)}</span>
                                                    </div>
                                                    <span className={`font-mono text-xs ${tAmount > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                                        {tAmount > 0 ? '+' : ''}
                                                        {formatPrice(tAmount)}
                                                    </span>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
