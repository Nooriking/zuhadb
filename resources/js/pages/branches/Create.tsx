import { BackButton } from '@/components/form-page';
// resources/js/pages/branches/Create.tsx

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Building2, Save } from 'lucide-react';
import { useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Props {
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Branches', href: '/branches' },
    { title: 'Create Branch', href: '/branches/create' },
];

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function BranchesCreate({ errors: initialErrors }: Props) {
    const { toast } = useToast();

    const [form, setForm] = useState({
        name: '',
        code: '',
        phone: '',
        email: '',
        country: '',
        province: '',
        district: '',
        address: '',
        status: 'active' as 'active' | 'inactive',
    });

    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        // ⭐ no manager_user_id at all — branch is created without manager
        router.post('/branches', form, {
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: `Branch "${form.name}" was created successfully.`,
                });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err);
                setLoading(false);
                toast({
                    title: 'Error',
                    description: 'There was a problem creating the branch.',
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
            <Head title="Create Branch" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/branches" label="Back" />
                    <div className="flex items-center gap-3">
                        <Building2 className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Create Branch</h1>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Branch Information</CardTitle>
                            <CardDescription>Enter the required information to create a new branch. You can assign a manager later.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Name + Code */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>
                                        Branch Name <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        value={form.name}
                                        onChange={(e) => handleChange('name', e.target.value)}
                                        placeholder="e.g. Main Branch"
                                        className={errors.name ? 'border-destructive' : ''}
                                    />
                                    {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label>
                                        Branch Code <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        value={form.code}
                                        onChange={(e) => handleChange('code', e.target.value)}
                                        placeholder="e.g. BR-001"
                                        className={errors.code ? 'border-destructive' : ''}
                                    />
                                    {errors.code && <p className="text-destructive text-sm">{errors.code}</p>}
                                </div>
                            </div>

                            {/* Contact */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Phone</Label>
                                    <Input value={form.phone} onChange={(e) => handleChange('phone', e.target.value)} placeholder="+93 700 000 000" />
                                </div>

                                <div className="space-y-2">
                                    <Label>Email</Label>
                                    <Input
                                        type="email"
                                        value={form.email}
                                        onChange={(e) => handleChange('email', e.target.value)}
                                        placeholder="branch@company.com"
                                    />
                                </div>
                            </div>

                            {/* Location */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div className="space-y-2">
                                    <Label>Country</Label>
                                    <Input
                                        value={form.country}
                                        onChange={(e) => handleChange('country', e.target.value)}
                                        placeholder="e.g. Afghanistan"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Province</Label>
                                    <Input
                                        value={form.province}
                                        onChange={(e) => handleChange('province', e.target.value)}
                                        placeholder="e.g. Kabul"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>District</Label>
                                    <Input
                                        value={form.district}
                                        onChange={(e) => handleChange('district', e.target.value)}
                                        placeholder="e.g. District 4"
                                    />
                                </div>
                            </div>

                            {/* Address */}
                            <div className="space-y-2">
                                <Label>Address</Label>
                                <Textarea
                                    value={form.address}
                                    onChange={(e) => handleChange('address', e.target.value)}
                                    placeholder="Full branch address"
                                    rows={3}
                                />
                            </div>

                            {/* Status */}
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
                                    {loading ? 'Saving...' : 'Save Branch'}
                                </Button>
                                <BackButton href="/branches" label="Cancel" />
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
