import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Banknote, Edit, Printer, Truck } from 'lucide-react';

interface Debt {
    id: number;
    invoice_no: string;
    purchase_date: string | null;
    exchange_rate: number;
    total_afn: number;
    paid_afn: number;
    due_afn: number;
    notes: string | null;
    bill_image: string | null;
}

interface PaymentRow {
    id: number;
    receipt_number: string;
    amount_afn: number;
    balance_after: number;
    payment_date: string | null;
    payment_method: string;
    notes: string | null;
    user: string | null;
}

interface Props {
    supplier: {
        id: number;
        name: string;
        phone: string | null;
        contact_person: string | null;
        status: 'active' | 'inactive';
    };
    account: {
        opening_balance: number;
        opening_remaining: number;
        purchase_remaining: number;
        balance: number;
        total_purchases: number;
        total_paid: number;
    };
    debts: Debt[];
    payments: PaymentRow[];
}

const money = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(value || 0);

export default function SuppliersShow({ supplier, account, debts, payments }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Suppliers', href: '/suppliers' },
        { title: supplier.name, href: `/suppliers/${supplier.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${supplier.name} account`} />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Truck className="h-7 w-7" />
                        <div>
                            <h1 className="text-2xl font-bold">{supplier.name}</h1>
                            <p className="text-muted-foreground text-sm">
                                {supplier.contact_person || 'Supplier'}
                                {supplier.phone ? ` · ${supplier.phone}` : ''}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href={`/suppliers/transactions?supplier_id=${supplier.id}`}>
                            <Button variant="outline">Transactions</Button>
                        </Link>
                        <Link href={`/suppliers/${supplier.id}/payments/create`}>
                            <Button className="gap-2">
                                <Banknote className="h-4 w-4" /> Record payment
                            </Button>
                        </Link>
                        <Link href={`/suppliers/${supplier.id}/edit`}>
                            <Button variant="outline" className="gap-2">
                                <Edit className="h-4 w-4" /> Edit
                            </Button>
                        </Link>
                        <Link href="/suppliers">
                            <Button variant="outline" className="gap-2">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </Button>
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card className="border-amber-300 bg-amber-50">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-amber-900">Balance due</CardTitle>
                        </CardHeader>
                        <CardContent className="font-mono text-2xl font-bold text-amber-950">{money(account.balance)} AFN</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">Open bills</CardTitle>
                        </CardHeader>
                        <CardContent className="font-mono text-xl font-semibold">{money(account.purchase_remaining)} AFN</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">Paid</CardTitle>
                        </CardHeader>
                        <CardContent className="font-mono text-xl font-semibold text-emerald-700">{money(account.total_paid)} AFN</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">Purchases</CardTitle>
                        </CardHeader>
                        <CardContent className="text-xl font-semibold">{account.total_purchases}</CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Bills</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Invoice</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead className="text-right">USD rate</TableHead>
                                    <TableHead className="text-right">Total</TableHead>
                                    <TableHead className="text-right">Paid</TableHead>
                                    <TableHead className="text-right">Due</TableHead>
                                    <TableHead>Bill image</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {debts.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={8} className="text-muted-foreground h-20 text-center">
                                            No open bill for this supplier.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    debts.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="font-mono">{row.invoice_no}</TableCell>
                                            <TableCell>{row.purchase_date || '—'}</TableCell>
                                            <TableCell className="text-right font-mono">{row.exchange_rate || '—'}</TableCell>
                                            <TableCell className="text-right font-mono">{money(row.total_afn)}</TableCell>
                                            <TableCell className="text-right font-mono">{money(row.paid_afn)}</TableCell>
                                            <TableCell className="text-right font-mono font-semibold">{money(row.due_afn)}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {row.bill_image && (
                                                        <a href={`/storage/${row.bill_image}`} target="_blank" rel="noreferrer">
                                                            <img loading="lazy" decoding="async" src={`/storage/${row.bill_image}`} alt="Bill" className="h-10 w-10 rounded border object-cover" />
                                                        </a>
                                                    )}
                                                    <Input
                                                        type="file"
                                                        accept="image/*"
                                                        className="h-8 max-w-[140px] text-xs"
                                                        onChange={(e) => {
                                                            const file = e.target.files?.[0];
                                                            if (!file) return;
                                                            const data = new FormData();
                                                            data.append('bill_image', file);
                                                            router.post(`/suppliers/${supplier.id}/invoices/${row.id}/bill`, data, { forceFormData: true, preserveScroll: true });
                                                        }}
                                                    />
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Link href={`/suppliers/${supplier.id}/invoices/${row.id}/receipt`}>
                                                    <Button variant="ghost" size="sm" className="gap-1">
                                                        <Printer className="h-4 w-4" /> Receipt
                                                    </Button>
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Payments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Receipt</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Method</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                    <TableHead className="text-right">Balance after</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payments.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-muted-foreground h-20 text-center">
                                            No payment recorded yet.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    payments.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="font-mono">{row.receipt_number}</TableCell>
                                            <TableCell>{row.payment_date}</TableCell>
                                            <TableCell>{row.payment_method}</TableCell>
                                            <TableCell className="text-right font-mono">{money(row.amount_afn)}</TableCell>
                                            <TableCell className="text-right font-mono">{money(row.balance_after)}</TableCell>
                                            <TableCell className="text-right">
                                                <Link href={`/suppliers/${supplier.id}/payments/${row.id}/receipt`}>
                                                    <Button variant="ghost" size="sm" className="gap-1">
                                                        <Printer className="h-4 w-4" /> Receipt
                                                    </Button>
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
