// resources/js/pages/brands/edit.tsx

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
import { ArrowLeft, Save, Tag } from 'lucide-react';
import { useState } from 'react';

interface Brand {
    id: number;
    name: string;
    country: string | null;
    description: string | null;
    logo: string | null;
    status: 'active' | 'inactive';
}

interface Props {
    brand: Brand;
    errors?: Record<string, string>;
}

export default function BrandsEdit({ brand, errors: initialErrors }: Props) {
    const { toast } = useToast();
    const [form, setForm] = useState({
        name: brand.name,
        country: brand.country || '',
        description: brand.description || '',
        status: brand.status,
    });
    const [logo, setLogo] = useState<File | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Brands', href: '/brands' },
        { title: brand.name, href: `/brands/${brand.id}` },
        { title: 'Edit', href: `/brands/${brand.id}/edit` },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const formData = new FormData();
        formData.append('_method', 'PUT');
        Object.entries(form).forEach(([key, value]) => formData.append(key, value));
        if (logo) formData.append('logo', logo);

        router.post(`/brands/${brand.id}`, formData, {
            forceFormData: true,
            onSuccess: () => {
                toast({ title: 'Success', description: `Brand "${form.name}" was updated successfully.` });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err);
                setLoading(false);
                toast({ title: 'Error', description: 'There was a problem updating the brand.', variant: 'destructive' });
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
            <Head title={`Edit ${brand.name}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Tag className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Edit Brand</h1>
                        <span className="text-muted-foreground">{brand.name}</span>
                    </div>
                    <Link href="/brands">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Button>
                    </Link>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Edit Brand Information</CardTitle>
                            <CardDescription>Update the information for brand {brand.name}.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>
                                        Brand Name <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        value={form.name}
                                        onChange={(e) => handleChange('name', e.target.value)}
                                        className={errors.name ? 'border-destructive' : ''}
                                    />
                                    {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label>Country</Label>
                                    <Input
                                        value={form.country}
                                        onChange={(e) => handleChange('country', e.target.value)}
                                        className={errors.country ? 'border-destructive' : ''}
                                    />
                                    {errors.country && <p className="text-destructive text-sm">{errors.country}</p>}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Description</Label>
                                <Textarea
                                    value={form.description}
                                    onChange={(e) => handleChange('description', e.target.value)}
                                    rows={4}
                                    className={errors.description ? 'border-destructive' : ''}
                                />
                                {errors.description && <p className="text-destructive text-sm">{errors.description}</p>}
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Logo</Label>
                                    {brand.logo && (
                                        <div className="mb-2">
                                            <img
                                                src={`/storage/${brand.logo}`}
                                                alt={brand.name}
                                                className="h-16 w-16 rounded-md border object-contain"
                                            />
                                        </div>
                                    )}
                                    <Input
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) => setLogo(e.target.files?.[0] || null)}
                                        className={errors.logo ? 'border-destructive' : ''}
                                    />
                                    {errors.logo && <p className="text-destructive text-sm">{errors.logo}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label>
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

                            <div className="flex gap-2 border-t pt-4">
                                <Button type="submit" disabled={loading} className="gap-2">
                                    <Save className="h-4 w-4" />
                                    {loading ? 'Saving...' : 'Update Brand'}
                                </Button>
                                <Link href={`/brands/${brand.id}`}>
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
