// resources/js/pages/sale-returns/show.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CheckCircle2, FileText, Package, RotateCcw, User, Wallet } from 'lucide-react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface ReturnItem {
    id: number;
    quantity: number;
    unit_price_afn: string;
    total_afn: string;
    expense_amount_afn: string;
    expense_bearer: 'shop' | 'customer';
    condition: 'good' | 'damaged' | 'expired' | 'other' | 'used';
    reason: string | null;
    expense: { id: number; expense_number: string; title: string; amount_afn: string } | null;
    batch: { id: number; batch_code: string } | null;
    variant: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
        product: { id: number; name: string; brand: { id: number; name: string } | null } | null;
    } | null;
}

interface SaleReturn {
    id: number;
    return_number: string;
    return_date: string;
    subtotal_afn: string;
    total_afn: string;
    previous_due_afn: string;
    new_due_afn: string;
    refund_amount_afn: string;
    refund_method: string | null;
    reason: string | null;
    status: 'draft' | 'completed' | 'cancelled';
    sale: { id: number; invoice_number: string } | null;
    branch: { id: number; name: string; code: string } | null;
    customer: { id: number; name: string; phone: string | null; current_due_afn: string; credit_limit_afn: string } | null;
    creator: { id: number; name: string; email: string } | null;
    items: ReturnItem[];
    created_at: string;
}

interface UsedExpense {
    id: number;
    expense_number: string;
    title: string;
    amount_afn: string;
    expense_date: string;
}

interface Props {
    saleReturn: SaleReturn;
    expenses: UsedExpense[];
}

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const formatPrice = (value: string | number, digits: number = 2) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(num || 0);
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

const getConditionBadge = (condition: string) => {
    const map: Record<string, { variant: 'success' | 'destructive' | 'secondary' | 'outline'; label: string }> = {
        good: { variant: 'success', label: 'Good — back to stock' },
        damaged: { variant: 'destructive', label: 'Damaged' },
        expired: { variant: 'secondary', label: 'Expired' },
        used: { variant: 'outline', label: 'Used — expense' },
        other: { variant: 'outline', label: 'Other' },
    };
    const item = map[condition] || map.other;
    return <Badge variant={item.variant}>{item.label}</Badge>;
};

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function SaleReturnsShow({ saleReturn, expenses = [] }: Props) {
    const { toast } = useToast();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Sale Returns', href: '/sale-returns' },
        { title: saleReturn.return_number, href: `/sale-returns/${saleReturn.id}` },
    ];

    const previousDue = parseFloat(saleReturn.previous_due_afn || '0');
    const newDue = parseFloat(saleReturn.new_due_afn || '0');
    const refund = parseFloat(saleReturn.refund_amount_afn || '0');
    const itemExpense = (item: ReturnItem) => {
        const line = parseFloat(item.total_afn || '0');
        return item.condition === 'used' ? line : Math.min(line, parseFloat(item.expense_amount_afn || '0'));
    };
    const shopExpenseAfn = saleReturn.items
        .filter((item) => item.expense_bearer === 'shop')
        .reduce((sum, item) => sum + itemExpense(item), 0);
    const customerExpenseAfn = saleReturn.items
        .filter((item) => item.expense_bearer !== 'shop')
        .reduce((sum, item) => sum + itemExpense(item), 0);

    const handleMarkCompleted = () => {
        if (!confirm('Mark as completed? Good items return to stock and the customer due is updated.')) return;

        router.patch(
            `/sale-returns/${saleReturn.id}/mark-as-completed`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Success', description: 'Return completed.' }),
                onError: (err) => toast({ title: 'Error', description: Object.values(err).join(' '), variant: 'destructive' }),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Return ${saleReturn.return_number}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                            <RotateCcw className="h-6 w-6 text-amber-600" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="font-mono text-2xl font-bold tracking-tight">{saleReturn.return_number}</h1>
                                {getStatusBadge(saleReturn.status)}
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {new Date(saleReturn.return_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {saleReturn.status === 'draft' && (
                            <Button onClick={handleMarkCompleted} className="gap-2 bg-green-600 hover:bg-green-700">
                                <CheckCircle2 className="h-4 w-4" /> Mark Completed
                            </Button>
                        )}
                        {saleReturn.status !== 'completed' && (
                            <Link href={`/sale-returns/${saleReturn.id}/edit`}>
                                <Button variant="outline">Edit</Button>
                            </Link>
                        )}
                        <Link href="/sale-returns">
                            <Button variant="outline" className="gap-2">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* ═══════════ Due Impact Hero ═══════════ */}
                <Card className="border-2 border-amber-300 bg-amber-50">
                    <CardContent className="p-6">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                            <div>
                                <p className="text-muted-foreground text-xs uppercase">Return Amount</p>
                                <p className="mt-1 font-mono text-2xl font-bold text-green-700">
                                    {formatPrice(saleReturn.total_afn)} <span className="text-sm">AFN</span>
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs uppercase">Previous Due</p>
                                <p className="mt-1 font-mono text-2xl font-bold text-amber-700">
                                    {formatPrice(previousDue)} <span className="text-sm">AFN</span>
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs uppercase">New Due</p>
                                <p className={`mt-1 font-mono text-2xl font-bold ${newDue > 0 ? 'text-red-700' : 'text-green-700'}`}>
                                    {formatPrice(newDue)} <span className="text-sm">AFN</span>
                                </p>
                            </div>
                            {refund > 0 && (
                                <div>
                                    <p className="text-muted-foreground text-xs uppercase">Refund</p>
                                    <p className="mt-1 font-mono text-2xl font-bold text-red-700">
                                        {formatPrice(refund)} <span className="text-sm">AFN</span>
                                    </p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ Info Grid ═══════════ */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <User className="h-4 w-4" /> Customer
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {saleReturn.customer ? (
                                <>
                                    <Link href={`/customers/${saleReturn.customer.id}`} className="hover:text-primary text-lg font-semibold">
                                        {saleReturn.customer.name}
                                    </Link>
                                    {saleReturn.customer.phone && (
                                        <div className="text-muted-foreground" dir="ltr">
                                            {saleReturn.customer.phone}
                                        </div>
                                    )}
                                    <Separator />
                                    <div className="grid grid-cols-2 gap-1 text-xs">
                                        <span className="text-muted-foreground">Current Due:</span>
                                        <span className="text-right font-mono font-semibold text-amber-700">
                                            {formatPrice(saleReturn.customer.current_due_afn)} AFN
                                        </span>
                                    </div>
                                </>
                            ) : (
                                <p className="text-muted-foreground">Walk-in Customer</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <FileText className="h-4 w-4" /> Sale Info
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="grid grid-cols-2 gap-1">
                                <span className="text-muted-foreground">Original Sale:</span>
                                <span className="text-right">
                                    {saleReturn.sale ? (
                                        <Link href={`/sales/${saleReturn.sale.id}`} className="hover:text-primary font-mono text-xs">
                                            {saleReturn.sale.invoice_number}
                                        </Link>
                                    ) : (
                                        '—'
                                    )}
                                </span>
                                <span className="text-muted-foreground">Branch:</span>
                                <span className="text-right text-xs">{saleReturn.branch?.name}</span>
                                <span className="text-muted-foreground">Created By:</span>
                                <span className="text-right text-xs">{saleReturn.creator?.name || 'System'}</span>
                            </div>

                            {saleReturn.reason && (
                                <>
                                    <Separator />
                                    <div>
                                        <p className="text-muted-foreground mb-1 text-xs">Reason:</p>
                                        <p className="text-xs">{saleReturn.reason}</p>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Wallet className="h-4 w-4" /> Settlement
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="grid grid-cols-2 gap-1">
                                <span className="text-muted-foreground">Prev Due:</span>
                                <span className="text-right font-mono text-xs text-amber-700">{formatPrice(previousDue)} AFN</span>
                                <span className="text-muted-foreground">Return:</span>
                                <span className="text-right font-mono text-xs text-green-700">-{formatPrice(saleReturn.total_afn)} AFN</span>
                                <span className="col-span-2 my-1 border-t" />
                                <span className="font-semibold">New Due:</span>
                                <span className="text-right font-mono font-bold">{formatPrice(newDue)} AFN</span>

                                {refund > 0 && (
                                    <>
                                        <span className="col-span-2 my-1 border-t" />
                                        <span className="text-destructive font-bold">Refund:</span>
                                        <span className="text-destructive text-right font-mono font-bold">{formatPrice(refund)} AFN</span>
                                        {saleReturn.refund_method && (
                                            <>
                                                <span className="text-muted-foreground text-xs">Method:</span>
                                                <span className="text-right text-xs">{saleReturn.refund_method}</span>
                                            </>
                                        )}
                                    </>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* ═══════════ Items ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="h-5 w-5" /> Returned Items
                            <Badge variant="secondary">{saleReturn.items.length}</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">#</TableHead>
                                        <TableHead>Product</TableHead>
                                        <TableHead>Batch</TableHead>
                                        <TableHead className="text-center">Qty</TableHead>
                                        <TableHead className="text-right">Unit Price</TableHead>
                                        <TableHead className="text-right">Total</TableHead>
                                        <TableHead>Condition</TableHead>
                                        <TableHead className="text-right">Item expense</TableHead>
                                        <TableHead>Reason</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {saleReturn.items.map((item, idx) => (
                                        <TableRow key={item.id}>
                                            <TableCell>{idx + 1}</TableCell>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {item.variant?.product?.brand?.name && (
                                                        <span className="text-muted-foreground">{item.variant.product.brand.name} — </span>
                                                    )}
                                                    {item.variant?.product?.name}
                                                </div>
                                                <div className="text-muted-foreground text-xs">
                                                    {[
                                                        item.variant?.volume_ml ? `${item.variant.volume_ml}ml` : null,
                                                        item.variant?.concentration?.toUpperCase(),
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">{item.batch?.batch_code || '—'}</TableCell>
                                            <TableCell className="text-center font-mono font-semibold">{item.quantity}</TableCell>
                                            <TableCell className="text-right font-mono text-xs">{formatPrice(item.unit_price_afn)}</TableCell>
                                            <TableCell className="text-right font-mono font-semibold">{formatPrice(item.total_afn)}</TableCell>
                                            <TableCell>{getConditionBadge(item.condition)}</TableCell>
                                            <TableCell className="text-right">
                                                {itemExpense(item) > 0 ? (
                                                    <div className="flex flex-col items-end gap-1">
                                                        <span className="font-mono text-xs font-semibold">{formatPrice(itemExpense(item))} AFN</span>
                                                        <Badge
                                                            variant="outline"
                                                            className={item.expense_bearer === 'shop' ? 'border-blue-300 text-blue-700' : 'border-orange-300 text-orange-700'}
                                                        >
                                                            {item.expense_bearer === 'shop' ? 'Shop (دوکان)' : 'Customer (مشتری)'}
                                                        </Badge>
                                                        {item.expense && (
                                                            <Link href={`/expenses/${item.expense.id}`} className="font-mono text-xs hover:underline">
                                                                {item.expense.expense_number}
                                                            </Link>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-xs">{item.reason || '—'}</TableCell>
                                            <TableCell className="text-right">
                                                {saleReturn.status === 'completed' && item.condition !== 'used' && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => router.post(`/sale-returns/${saleReturn.id}/items/${item.id}/mark-used`)}
                                                    >
                                                        Mark used
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Summary */}
                        <div className="mt-4 ml-auto max-w-sm rounded-lg border-2 border-emerald-300 bg-emerald-50 p-4">
                            <div className="grid grid-cols-2 gap-y-2 text-sm">
                                {customerExpenseAfn > 0 && (
                                    <>
                                        <span className="text-orange-700">Expense — customer (deducted):</span>
                                        <span className="text-right font-mono text-orange-700">-{formatPrice(customerExpenseAfn)} AFN</span>
                                    </>
                                )}
                                <span className="font-bold">Return credit:</span>
                                <span className="text-right font-mono font-bold">{formatPrice(saleReturn.total_afn)} AFN</span>
                                {shopExpenseAfn > 0 && (
                                    <>
                                        <span className="text-blue-700">Expense — shop (office expense):</span>
                                        <span className="text-right font-mono text-blue-700">{formatPrice(shopExpenseAfn)} AFN</span>
                                    </>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {expenses.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Shop expenses (office)</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {expenses.map((expense) => (
                                <div key={expense.id} className="flex items-center justify-between">
                                    <span>
                                        {expense.expense_number} · {expense.title}
                                    </span>
                                    <span className="font-mono font-semibold">{formatPrice(expense.amount_afn)} AFN</span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {/* ═══════════ Refund Warning ═══════════ */}
                {refund > 0 && (
                    <Card className="border-2 border-red-400 bg-red-50">
                        <CardContent className="flex items-center gap-3 p-4">
                            <AlertTriangle className="h-6 w-6 shrink-0 text-red-600" />
                            <div>
                                <p className="font-bold text-red-800">Refund Required</p>
                                <p className="text-sm text-red-700">
                                    The refund is larger than the customer due. Return {formatPrice(refund)} AFN to the customer.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
