// resources/js/pages/branches/Edit.tsx

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
import { AlertCircle, ArrowLeft, Building2, Save, UserPlus, UserX } from 'lucide-react';
import { useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Manager {
    id: number;
    name: string;
    email: string;
}

interface Branch {
    id: number;
    manager_user_id: number | null;
    name: string;
    code: string;
    phone: string | null;
    email: string | null;
    country: string | null;
    province: string | null;
    district: string | null;
    address: string | null;
    status: 'active' | 'inactive';
}

interface Props {
    branch: Branch;
    managers: Manager[];
    errors?: Record<string, string>;
}

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function BranchesEdit({ branch, managers, errors: initialErrors }: Props) {
    const { toast } = useToast();

    const [form, setForm] = useState({
        manager_user_id: branch.manager_user_id ? String(branch.manager_user_id) : 'none',
        name: branch.name,
        code: branch.code,
        phone: branch.phone || '',
        email: branch.email || '',
        country: branch.country || '',
        province: branch.province || '',
        district: branch.district || '',
        address: branch.address || '',
        status: branch.status,
    });

    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Branches', href: '/branches' },
        { title: branch.name, href: `/branches/${branch.id}` },
        { title: 'Edit', href: `/branches/${branch.id}/edit` },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const payload = {
            ...form,
            // ⭐ 'none' → null (نه رشته خالی)
            manager_user_id: form.manager_user_id === 'none' ? null : form.manager_user_id,
        };

        router.put(`/branches/${branch.id}`, payload, {
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: `Branch "${form.name}" was updated successfully.`,
                });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err);
                setLoading(false);
                toast({
                    title: 'Error',
                    description: 'There was a problem updating the branch.',
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

    // ⭐ آیا این شعبه مدیر دارد؟
    const hasManager = form.manager_user_id !== 'none';

    // ⭐ آیا هیچ Branch Manager آزادی وجود ندارد؟ (برای هشدار)
    const noFreeManagers = managers.length === 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${branch.name}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Building2 className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Edit Branch</h1>
                        <span className="text-muted-foreground">{branch.name}</span>
                    </div>
                    <Link href="/branches">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Button>
                    </Link>
                </div>

                {/* ⭐ هشدار: این شعبه مدیر ندارد */}
                {!branch.manager_user_id && (
                    <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        <div className="flex-1">
                            <p className="font-medium">این شعبه هنوز مدیری ندارد</p>
                            <p className="mt-1 text-xs">لطفاً یک Branch Manager انتخاب کنید و ذخیره کنید.</p>
                        </div>
                    </div>
                )}

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Edit Branch Information</CardTitle>
                            <CardDescription>Update the information for branch {branch.name}.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* ⭐ هشدار اگر هیچ Branch Manager آزادی وجود ندارد */}
                            {noFreeManagers && hasManager && (
                                <div className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                                    <div className="flex-1">
                                        <p className="font-medium">Branch Manager آزادی وجود ندارد</p>
                                        <p className="mt-1 text-xs">
                                            فقط مدیر فعلی این شعبه در لیست نمایش داده می‌شود. برای تغییر مدیر، ابتدا یک Branch Manager آزاد بسازید.
                                        </p>
                                        <Link
                                            href="/users/create"
                                            className="mt-2 inline-flex items-center gap-1 text-xs font-medium underline hover:no-underline"
                                        >
                                            <UserPlus className="h-3 w-3" />
                                            ساخت Branch Manager جدید
                                        </Link>
                                    </div>
                                </div>
                            )}

                            {/* Manager */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label className="flex items-center gap-2">
                                        Branch Manager
                                        {hasManager ? (
                                            <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                تعیین شده
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                بدون مدیر
                                            </span>
                                        )}
                                    </Label>
                                    <Select value={form.manager_user_id} onValueChange={(v) => handleChange('manager_user_id', v)}>
                                        <SelectTrigger className={errors.manager_user_id ? 'border-destructive' : ''}>
                                            <SelectValue placeholder="انتخاب مدیر" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                <div className="flex items-center gap-2">
                                                    <UserX className="h-4 w-4 text-amber-500" />
                                                    بدون مدیر
                                                </div>
                                            </SelectItem>
                                            {managers.map((m) => (
                                                <SelectItem key={m.id} value={String(m.id)}>
                                                    {m.name} ({m.email})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.manager_user_id && <p className="text-destructive text-sm">{errors.manager_user_id}</p>}
                                    <p className="text-muted-foreground text-xs">
                                        فقط Branch Manager های آزاد + مدیر فعلی این شعبه نمایش داده می‌شوند.
                                    </p>
                                </div>
                            </div>

                            {/* Name + Code */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>
                                        Branch Name <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        value={form.name}
                                        onChange={(e) => handleChange('name', e.target.value)}
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
                                        className={errors.code ? 'border-destructive' : ''}
                                    />
                                    {errors.code && <p className="text-destructive text-sm">{errors.code}</p>}
                                </div>
                            </div>

                            {/* Contact */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Phone</Label>
                                    <Input value={form.phone} onChange={(e) => handleChange('phone', e.target.value)} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Email</Label>
                                    <Input type="email" value={form.email} onChange={(e) => handleChange('email', e.target.value)} />
                                </div>
                            </div>

                            {/* Location */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div className="space-y-2">
                                    <Label>Country</Label>
                                    <Input value={form.country} onChange={(e) => handleChange('country', e.target.value)} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Province</Label>
                                    <Input value={form.province} onChange={(e) => handleChange('province', e.target.value)} />
                                </div>
                                <div className="space-y-2">
                                    <Label>District</Label>
                                    <Input value={form.district} onChange={(e) => handleChange('district', e.target.value)} />
                                </div>
                            </div>

                            {/* Address */}
                            <div className="space-y-2">
                                <Label>Address</Label>
                                <Textarea value={form.address} onChange={(e) => handleChange('address', e.target.value)} rows={3} />
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
                                    {loading ? 'Saving...' : 'Update Branch'}
                                </Button>
                                <Link href={`/branches/${branch.id}`}>
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
