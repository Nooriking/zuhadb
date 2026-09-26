// resources/js/pages/sale-returns/edit.tsx
// فقط خلاصه — مشابه create.tsx ولی با data از saleReturn

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useState } from 'react';

interface Props {
    saleReturn: any;
}

const CONDITION_OPTIONS = [
    { value: 'good', label: 'Good — returned to stock' },
    { value: 'damaged', label: 'Damaged — not returned to stock' },
    { value: 'expired', label: 'Expired — not returned to stock' },
    { value: 'used', label: 'Used — expense, amount reduced' },
    { value: 'other', label: 'Other' },
];

export default function SaleReturnsEdit({ saleReturn }: Props) {
    const { toast } = useToast();

    const [form, setForm] = useState({
        return_date: saleReturn.return_date.slice(0, 10),
        reason: saleReturn.reason || '',
        status: saleReturn.status,
    });

    const [items, setItems] = useState(
        Object.fromEntries(
            saleReturn.items.map((it: any) => [
                it.id,
                {
                    quantity: String(it.quantity),
                    condition: it.condition,
                    reason: it.reason || '',
                    expense_amount: String(it.expense_amount_afn || ''),
                },
            ]),
        ) as Record<number, { quantity: string; condition: string; reason: string; expense_amount: string }>,
    );

    const [loading, setLoading] = useState(false);

    const updateItem = (id: number, field: 'quantity' | 'condition' | 'reason' | 'expense_amount', value: string) => {
        setItems((prev) => ({ ...prev, [id]: { ...prev[id], [field]: value } }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);

        const itemsPayload = Object.entries(items).map(([id, entry]) => ({
            id: parseInt(id),
            quantity: parseInt(entry.quantity),
            condition: entry.condition,
            reason: entry.reason || null,
            expense_amount_afn: parseFloat(entry.expense_amount || '0') || 0,
        }));

        router.put(
            `/sale-returns/${saleReturn.id}`,
            { ...form, items: itemsPayload },
            {
                onSuccess: () => {
                    toast({ title: 'Success', description: 'Return updated.' });
                    setLoading(false);
                },
                onError: () => {
                    toast({ title: 'Error', description: 'Please check form.', variant: 'destructive' });
                    setLoading(false);
                },
            },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Sale Returns', href: '/sale-returns' },
        { title: saleReturn.return_number, href: `/sale-returns/${saleReturn.id}` },
        { title: 'Edit', href: `/sale-returns/${saleReturn.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${saleReturn.return_number}`} />
            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Edit Return: {saleReturn.return_number}</h1>
                    <Link href={`/sale-returns/${saleReturn.id}`}>
                        <Button variant="outline" type="button" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Button>
                    </Link>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Items</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="p-3 text-left text-xs font-semibold uppercase">Product</th>
                                        <th className="p-3 text-center text-xs font-semibold uppercase">Qty</th>
                                        <th className="p-3 text-center text-xs font-semibold uppercase">Condition</th>
                                        <th className="p-3 text-right text-xs font-semibold uppercase">Item expense</th>
                                        <th className="p-3 text-center text-xs font-semibold uppercase">Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {saleReturn.items.map((item: any) => {
                                        const entry = items[item.id];
                                        return (
                                            <tr key={item.id} className="border-b">
                                                <td className="p-3">
                                                    {item.variant?.product?.brand?.name && (
                                                        <span className="text-muted-foreground">{item.variant.product.brand.name} — </span>
                                                    )}
                                                    {item.variant?.product?.name}
                                                </td>
                                                <td className="p-3 text-center">
                                                    <Input
                                                        type="number"
                                                        min="1"
                                                        value={entry.quantity}
                                                        onChange={(e) => updateItem(item.id, 'quantity', e.target.value)}
                                                        className="w-20 text-center font-mono"
                                                    />
                                                </td>
                                                <td className="p-3 text-center">
                                                    <Select value={entry.condition} onValueChange={(v) => updateItem(item.id, 'condition', v)}>
                                                        <SelectTrigger className="w-[180px]">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {CONDITION_OPTIONS.map((opt) => (
                                                                <SelectItem key={opt.value} value={opt.value}>
                                                                    {opt.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </td>
                                                <td className="p-3 text-right">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        value={entry.expense_amount}
                                                        onChange={(e) => updateItem(item.id, 'expense_amount', e.target.value)}
                                                        className="w-28 text-right font-mono"
                                                    />
                                                </td>
                                                <td className="p-3 text-center">
                                                    <Input
                                                        value={entry.reason}
                                                        onChange={(e) => updateItem(item.id, 'reason', e.target.value)}
                                                        placeholder="..."
                                                        className="text-sm"
                                                    />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Return Info</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Return Date *</Label>
                                <Input type="date" value={form.return_date} onChange={(e) => setForm({ ...form, return_date: e.target.value })} />
                            </div>
                            <div className="space-y-2">
                                <Label>Status</Label>
                                <Select value={form.status} onValueChange={(v) => setForm({ ...form, status: v })}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="draft">Draft</SelectItem>
                                        <SelectItem value="completed">Completed</SelectItem>
                                        <SelectItem value="cancelled">Cancelled</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label>Reason</Label>
                            <Textarea value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} rows={3} />
                        </div>
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button type="submit" disabled={loading} size="lg" className="gap-2">
                        <Save className="h-4 w-4" />
                        {loading ? 'Saving...' : 'Update Return'}
                    </Button>
                    <Link href={`/sale-returns/${saleReturn.id}`}>
                        <Button variant="outline" type="button" size="lg">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
