import { BackButton } from '@/components/form-page';
// resources/js/pages/customers/create.tsx

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
import { ArrowLeft, Save, UserCircle, Wallet } from 'lucide-react';
import { useState } from 'react';

interface Props {
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/customers' },
    { title: 'Create Customer', href: '/customers/create' },
];

export default function CustomersCreate({ errors: initialErrors }: Props) {
    const { toast } = useToast();
    const [form, setForm] = useState({
        name: '',
        phone: '',
        address: '',
        notes: '',
        status: 'active' as 'active' | 'inactive',
        credit_limit_afn: '',
        current_due_afn: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.post('/customers', form, {
            onSuccess: () => {
                toast({ title: 'Success', description: `Customer "${form.name}" was created successfully.` });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err);
                setLoading(false);
                toast({ title: 'Error', description: 'There was a problem creating the customer.', variant: 'destructive' });
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
            <Head title="Create Customer" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/customers" label="Back" />
                    <div className="flex items-center gap-3">
                        <UserCircle className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Create Customer</h1>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-6">
                        {/* Basic Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <UserCircle className="h-5 w-5" />
                                    Customer Information
                                </CardTitle>
                                <CardDescription>Enter the required information for the new customer.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>
                                            Customer Name <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            value={form.name}
                                            onChange={(e) => handleChange('name', e.target.value)}
                                            placeholder="e.g. Ahmad Karimi"
                                            className={errors.name ? 'border-destructive' : ''}
                                        />
                                        {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Phone</Label>
                                        <Input
                                            value={form.phone}
                                            onChange={(e) => handleChange('phone', e.target.value)}
                                            placeholder="+93 70 123 4567"
                                            dir="ltr"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label>Address</Label>
                                    <Textarea
                                        value={form.address}
                                        onChange={(e) => handleChange('address', e.target.value)}
                                        placeholder="Customer address"
                                        rows={3}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label>Notes</Label>
                                    <Textarea
                                        value={form.notes}
                                        onChange={(e) => handleChange('notes', e.target.value)}
                                        placeholder="Any extra notes..."
                                        rows={3}
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
                            </CardContent>
                        </Card>

                        {/* Credit */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Wallet className="h-5 w-5" />
                                    Credit & Due
                                </CardTitle>
                                <CardDescription>Set credit limit and initial due (optional). Default is 0.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Credit Limit (AFN)</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={form.credit_limit_afn}
                                            onChange={(e) => handleChange('credit_limit_afn', e.target.value)}
                                            placeholder="e.g. 30000"
                                            dir="ltr"
                                            className={errors.credit_limit_afn ? 'border-destructive' : ''}
                                        />
                                        {errors.credit_limit_afn && <p className="text-destructive text-sm">{errors.credit_limit_afn}</p>}
                                        <p className="text-muted-foreground text-xs">Maximum allowed due. 0 means no limit.</p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Initial Due (AFN)</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={form.current_due_afn}
                                            onChange={(e) => handleChange('current_due_afn', e.target.value)}
                                            placeholder="e.g. 5000"
                                            dir="ltr"
                                            className={errors.current_due_afn ? 'border-destructive' : ''}
                                        />
                                        {errors.current_due_afn && <p className="text-destructive text-sm">{errors.current_due_afn}</p>}
                                        <p className="text-muted-foreground text-xs">Opening due, if the customer already owes money. Default is 0.</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="mt-6 flex gap-2 border-t pt-4">
                        <Button type="submit" disabled={loading} className="gap-2">
                            <Save className="h-4 w-4" />
                            {loading ? 'Saving...' : 'Save Customer'}
                        </Button>
                        <BackButton href="/customers" label="Cancel" />
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
