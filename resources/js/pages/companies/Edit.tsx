// resources/js/pages/companies/Edit.tsx

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
import { ArrowLeft, Building2, Save } from 'lucide-react';
import { useState } from 'react';

interface Company {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    currency: string;
    logo: string | null;
    status: 'active' | 'inactive';
}

interface Props {
    company: Company;
    errors?: Record<string, string>;
}

export default function CompaniesEdit({ company, errors: initialErrors }: Props) {
    const { toast } = useToast();
    const [form, setForm] = useState({
        name: company.name,
        phone: company.phone || '',
        email: company.email || '',
        address: company.address || '',
        currency: company.currency || 'USD',
        status: company.status,
    });
    const [logo, setLogo] = useState<File | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Companies', href: '/companies' },
        { title: company.name, href: `/companies/${company.id}` },
        { title: 'Edit', href: `/companies/${company.id}/edit` },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const formData = new FormData();
        formData.append('_method', 'PUT');
        Object.entries(form).forEach(([key, value]) => formData.append(key, value));
        if (logo) formData.append('logo', logo);

        router.post(`/companies/${company.id}`, formData, {
            forceFormData: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: `Company "${form.name}" was updated successfully.`,
                });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err);
                setLoading(false);
                toast({
                    title: 'Error',
                    description: 'There was a problem updating the company.',
                    variant: 'destructive',
                });
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
            <Head title={`Edit ${company.name}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Building2 className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Edit Company</h1>
                        <span className="text-muted-foreground">{company.name}</span>
                    </div>
                    <Link href="/companies">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" />
                            Back
                        </Button>
                    </Link>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Edit Company Information</CardTitle>
                            <CardDescription>Update the information for company {company.name}.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                {/* Name */}
                                <div className="space-y-2">
                                    <Label htmlFor="name">
                                        Company Name <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="name"
                                        value={form.name}
                                        onChange={(e) => handleChange('name', e.target.value)}
                                        className={errors.name ? 'border-destructive' : ''}
                                    />
                                    {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                                </div>

                                {/* Email */}
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={form.email}
                                        onChange={(e) => handleChange('email', e.target.value)}
                                        className={errors.email ? 'border-destructive' : ''}
                                    />
                                    {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                                </div>

                                {/* Phone */}
                                <div className="space-y-2">
                                    <Label htmlFor="phone">Phone</Label>
                                    <Input
                                        id="phone"
                                        value={form.phone}
                                        onChange={(e) => handleChange('phone', e.target.value)}
                                        className={errors.phone ? 'border-destructive' : ''}
                                    />
                                    {errors.phone && <p className="text-destructive text-sm">{errors.phone}</p>}
                                </div>

                                {/* Currency */}
                                <div className="space-y-2">
                                    <Label htmlFor="currency">Currency</Label>
                                    <Select value={form.currency} onValueChange={(v) => handleChange('currency', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="USD">USD - US Dollar</SelectItem>
                                            <SelectItem value="EUR">EUR - Euro</SelectItem>
                                            <SelectItem value="GBP">GBP - British Pound</SelectItem>
                                            <SelectItem value="AFN">AFN - Afghan Afghani</SelectItem>
                                            <SelectItem value="PKR">PKR - Pakistani Rupee</SelectItem>
                                            <SelectItem value="IRR">IRR - Iranian Rial</SelectItem>
                                            <SelectItem value="AED">AED - UAE Dirham</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {/* Address */}
                            <div className="space-y-2">
                                <Label htmlFor="address">Address</Label>
                                <Textarea
                                    id="address"
                                    value={form.address}
                                    onChange={(e) => handleChange('address', e.target.value)}
                                    rows={3}
                                    className={errors.address ? 'border-destructive' : ''}
                                />
                                {errors.address && <p className="text-destructive text-sm">{errors.address}</p>}
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                {/* Logo */}
                                <div className="space-y-2">
                                    <Label htmlFor="logo">Logo</Label>
                                    {company.logo && (
                                        <div className="mb-2">
                                            <img
                                                src={`/storage/${company.logo}`}
                                                alt={company.name}
                                                className="h-16 w-16 rounded-md border object-contain"
                                            />
                                        </div>
                                    )}
                                    <Input
                                        id="logo"
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) => setLogo(e.target.files?.[0] || null)}
                                        className={errors.logo ? 'border-destructive' : ''}
                                    />
                                    {errors.logo && <p className="text-destructive text-sm">{errors.logo}</p>}
                                </div>

                                {/* Status */}
                                <div className="space-y-2">
                                    <Label htmlFor="status">
                                        Status <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={form.status} onValueChange={(v) => handleChange('status', v as 'active' | 'inactive')}>
                                        <SelectTrigger className={errors.status ? 'border-destructive' : ''}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="inactive">Inactive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.status && <p className="text-destructive text-sm">{errors.status}</p>}
                                </div>
                            </div>

                            {/* Submit */}
                            <div className="flex gap-2 border-t pt-4">
                                <Button type="submit" disabled={loading} className="gap-2">
                                    <Save className="h-4 w-4" />
                                    {loading ? 'Saving...' : 'Update Company'}
                                </Button>
                                <Link href={`/companies/${company.id}`}>
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
