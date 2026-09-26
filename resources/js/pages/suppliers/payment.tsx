import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';

interface Debt {
    id: number;
    invoice_no: string;
    due_afn: number;
}

interface Props {
    supplier: { id: number; name: string; phone: string | null };
    balance: number;
    debts: Debt[];
}

const money = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(value || 0);

export default function SupplierPayment({ supplier, balance, debts }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Suppliers', href: '/suppliers' },
        { title: supplier.name, href: `/suppliers/${supplier.id}` },
        { title: 'Payment', href: `/suppliers/${supplier.id}/payments/create` },
    ];
    const form = useForm({
        amount_afn: '',
        invoice_id: '',
        payment_date: new Date().toISOString().slice(0, 10),
        exchange_rate: '',
        payment_method: 'cash',
        notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/suppliers/${supplier.id}/payments`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Payment ${supplier.name}`} />
            <div className="mx-auto flex max-w-xl flex-col gap-6 p-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Record a payment to {supplier.name}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="mb-4 font-mono text-lg font-bold text-amber-800">Balance due: {money(balance)} AFN</p>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label>Which bill</Label>
                                <Select value={form.data.invoice_id || 'all'} onValueChange={(v) => form.setData('invoice_id', v === 'all' ? '' : v)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Whole account</SelectItem>
                                        {debts.map((row) => (
                                            <SelectItem key={row.id} value={String(row.id)}>
                                                {row.invoice_no} · {money(row.due_afn)} AFN
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.invoice_id && <p className="text-destructive text-xs">{form.errors.invoice_id}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label>Amount (AFN)</Label>
                                <Input type="number" min="0.01" step="0.01" value={form.data.amount_afn} onChange={(e) => form.setData('amount_afn', e.target.value)} required />
                                {form.errors.amount_afn && <p className="text-destructive text-xs">{form.errors.amount_afn}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-2">
                                    <Label>Date</Label>
                                    <Input type="date" value={form.data.payment_date} onChange={(e) => form.setData('payment_date', e.target.value)} required />
                                </div>
                                <div className="space-y-2">
                                    <Label>USD to AFN that day</Label>
                                    <Input type="number" min="0.01" step="0.01" value={form.data.exchange_rate} onChange={(e) => form.setData('exchange_rate', e.target.value)} required />
                                    {form.errors.exchange_rate && <p className="text-destructive text-xs">{form.errors.exchange_rate}</p>}
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>Method</Label>
                                    <Select value={form.data.payment_method} onValueChange={(v) => form.setData('payment_method', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="cash">Cash</SelectItem>
                                            <SelectItem value="bank">Bank</SelectItem>
                                            <SelectItem value="mobile">Mobile</SelectItem>
                                            <SelectItem value="other">Other</SelectItem>
                                        </SelectContent>
                                    </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Notes</Label>
                                <Textarea value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit" disabled={form.processing}>Save and print receipt</Button>
                                <Link href={`/suppliers/${supplier.id}`}>
                                    <Button type="button" variant="outline">Back</Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
