// resources/js/pages/suppliers/edit.tsx

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

interface Supplier {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    contact_person: string | null;
    address: string | null;
    opening_balance: string;
    notes: string | null;
    status: 'active' | 'inactive';
}

interface Props {
    supplier: Supplier;
    errors?: Record<string, string>;
}

export default function SuppliersEdit({ supplier, errors: initialErrors }: Props) {
    const { toast } = useToast();
    const [form, setForm] = useState({
        name: supplier.name,
        phone: supplier.phone || '',
        email: supplier.email || '',
        contact_person: supplier.contact_person || '',
        address: supplier.address || '',
        opening_balance: supplier.opening_balance || '0',
        notes: supplier.notes || '',
        status: supplier.status,
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Suppliers', href: '/suppliers' },
        { title: supplier.name, href: `/suppliers/${supplier.id}` },
        { title: 'Edit', href: `/suppliers/${supplier.id}/edit` },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.put(`/suppliers/${supplier.id}`, form, {
            onSuccess: () => {
                toast({ title: 'Success', description: `Supplier "${form.name}" was updated successfully.` });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err);
                setLoading(false);
                toast({ title: 'Error', description: 'There was a problem updating the supplier.', variant: 'destructive' });
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
            <Head title={`Edit ${supplier.name}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Truck className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Edit Supplier</h1>
                        <span className="text-muted-foreground">{supplier.name}</span>
                    </div>
                    <Link href="/suppliers">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Button>
                    </Link>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Edit Supplier Information</CardTitle>
                            <CardDescription>Update the information for supplier {supplier.name}.</CardDescription>
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
                                        className={errors.name ? 'border-destructive' : ''}
                                    />
                                    {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label>Contact Person</Label>
                                    <Input value={form.contact_person} onChange={(e) => handleChange('contact_person', e.target.value)} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Phone</Label>
                                    <Input value={form.phone} onChange={(e) => handleChange('phone', e.target.value)} dir="ltr" />
                                </div>
                                <div className="space-y-2">
                                    <Label>Email</Label>
                                    <Input
                                        type="email"
                                        value={form.email}
                                        onChange={(e) => handleChange('email', e.target.value)}
                                        className={errors.email ? 'border-destructive' : ''}
                                    />
                                    {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Address</Label>
                                <Textarea value={form.address} onChange={(e) => handleChange('address', e.target.value)} rows={3} />
                            </div>

                            <div className="rounded-lg border border-dashed p-4">
                                <h3 className="mb-3 text-sm font-semibold">Opening Balance</h3>
                                <div className="space-y-2">
                                    <Label>Amount (AFN)</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={form.opening_balance}
                                        onChange={(e) => handleChange('opening_balance', e.target.value)}
                                        className="font-mono"
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Notes</Label>
                                <Textarea value={form.notes} onChange={(e) => handleChange('notes', e.target.value)} rows={3} />
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
                                    {loading ? 'Saving...' : 'Update Supplier'}
                                </Button>
                                <Link href={`/suppliers/${supplier.id}`}>
                                    <Button variant="outline" type="button">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
