import { BackButton } from '@/components/form-page';
// resources/js/pages/suppliers/create.tsx

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save, Truck } from 'lucide-react';
import { useState } from 'react';

interface Props {
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Suppliers', href: '/suppliers' },
    { title: 'Create Supplier', href: '/suppliers/create' },
];

export default function SuppliersCreate({ errors: initialErrors }: Props) {
    const { toast } = useToast();
    const [form, setForm] = useState({
        name: '',
        phone: '',
        email: '',
        contact_person: '',
        address: '',
        opening_balance: '0',
        notes: '',
        status: 'active' as 'active' | 'inactive',
        invoice_no: '',
        purchase_date: new Date().toISOString().slice(0, 10),
        exchange_rate: '',
        total_afn: '',
        paid_now: '',
        bill_image: null as File | null,
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.post('/suppliers', form, {
            forceFormData: true,
            onSuccess: () => {
                toast({ title: 'Success', description: `Supplier "${form.name}" was created successfully.` });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err);
                setLoading(false);
                toast({ title: 'Error', description: 'There was a problem creating the supplier.', variant: 'destructive' });
            },
        });
    };

    const handleChange = (field: string, value: string) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const newErrors = { ...prev };
                delete newErrors[field];
                return newErrors;
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Supplier" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/suppliers" label="Back" />
                    <div className="flex items-center gap-3">
                        <Truck className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Create Supplier</h1>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Supplier Information</CardTitle>
                            <CardDescription>Enter the required information for the new supplier.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>
                                        Supplier Name <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        value={form.name}
                                        onChange={(e) => handleChange('name', e.target.value)}
                                        placeholder="e.g. Dubai Perfume Trading Co."
                                        className={errors.name ? 'border-destructive' : ''}
                                    />
                                    {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label>Contact Person</Label>
                                    <Input
                                        value={form.contact_person}
                                        onChange={(e) => handleChange('contact_person', e.target.value)}
                                        placeholder="e.g. Ahmed Ali"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label>Phone</Label>
                                    <Input
                                        value={form.phone}
                                        onChange={(e) => handleChange('phone', e.target.value)}
                                        placeholder="+971 50 123 4567"
                                        dir="ltr"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label>Email</Label>
                                    <Input
                                        type="email"
                                        value={form.email}
                                        onChange={(e) => handleChange('email', e.target.value)}
                                        placeholder="supplier@example.com"
                                        className={errors.email ? 'border-destructive' : ''}
                                    />
                                    {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Address</Label>
                                <Textarea
                                    value={form.address}
                                    onChange={(e) => handleChange('address', e.target.value)}
                                    placeholder="Full supplier address"
                                    rows={3}
                                />
                            </div>

                            <div className="rounded-lg border border-amber-300 bg-amber-50 p-4">
                                <h3 className="mb-1 text-sm font-semibold text-amber-950">First bill</h3>
                                <p className="text-muted-foreground mb-3 text-xs">
                                    Leave the amount empty if there is no bill yet. If you pay the full amount now, the balance stays zero. If you pay part of it, the rest stays as credit.
                                </p>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Invoice number</Label>
                                        <Input value={form.invoice_no} onChange={(e) => handleChange('invoice_no', e.target.value)} placeholder="INV-001" />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Date</Label>
                                        <Input type="date" value={form.purchase_date} onChange={(e) => handleChange('purchase_date', e.target.value)} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>USD to AFN that day</Label>
                                        <Input type="number" min="0" step="0.01" value={form.exchange_rate} onChange={(e) => handleChange('exchange_rate', e.target.value)} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Bill total (AFN)</Label>
                                        <Input type="number" min="0" step="0.01" value={form.total_afn} onChange={(e) => handleChange('total_afn', e.target.value)} className="font-mono" />
                                        {errors.total_afn && <p className="text-destructive text-sm">{errors.total_afn}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Paid now (AFN)</Label>
                                        <Input type="number" min="0" step="0.01" value={form.paid_now} onChange={(e) => handleChange('paid_now', e.target.value)} placeholder="Same as the bill if paid in full" className="font-mono" />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Bill image</Label>
                                        <Input type="file" accept="image/*" onChange={(e) => setForm((prev) => ({ ...prev, bill_image: e.target.files?.[0] || null }))} />
                                        {errors.bill_image && <p className="text-destructive text-sm">{errors.bill_image}</p>}
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Notes</Label>
                                <Textarea
                                    value={form.notes}
                                    onChange={(e) => handleChange('notes', e.target.value)}
                                    rows={3}
                                    placeholder="Any extra notes..."
                                />
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>
                                        Status <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={form.status} onValueChange={(v) => handleChange('status', v as 'active' | 'inactive')}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="inactive">Inactive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="flex gap-2 border-t pt-4">
                                <Button type="submit" disabled={loading} className="gap-2">
                                    <Save className="h-4 w-4" />
                                    {loading ? 'Saving...' : 'Save Supplier'}
                                </Button>
                                <BackButton href="/suppliers" label="Cancel" />
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
