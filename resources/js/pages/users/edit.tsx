// resources/js/pages/users/edit.tsx

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Building2, Lock, Save, Shield, User as UserIcon } from 'lucide-react';
import { useState } from 'react';

/* =========================================================
 |  Types
 ========================================================= */

interface Branch {
    id: number;
    name: string;
    code: string;
}

interface User {
    id: number;
    branch_id: number | null;
    name: string;
    username: string;
    email: string | null;
    phone: string | null;
    role: string;
    status: 'active' | 'inactive' | 'blocked';
    employee_code: string | null;
}

type RoleLabels = Record<string, string>;

interface Props {
    user: User;
    branches: Branch[];
    roles: RoleLabels;
    isBranchLocked: boolean;
    defaultBranchId: number | null;
    errors?: Record<string, string>;
}

/* =========================================================
 |  Component
 ========================================================= */

export default function UsersEdit({ user, branches, roles, isBranchLocked, defaultBranchId, errors: initialErrors }: Props) {
    const { toast } = useToast();

    const [form, setForm] = useState({
        name: user.name,
        username: user.username,
        email: user.email || '',
        phone: user.phone || '',
        password: '',
        password_confirmation: '',
        branch_id: defaultBranchId ? String(defaultBranchId) : 'none',
        role: user.role,
        employee_code: user.employee_code || '',
        status: user.status,
    });

    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/users' },
        { title: user.name, href: `/users/${user.id}` },
        { title: 'Edit', href: `/users/${user.id}/edit` },
    ];

    /* =========================================================
     |  Submit
     ========================================================= */

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const payload = {
            ...form,
            branch_id: form.branch_id === 'none' ? null : form.branch_id,
        };

        router.put(`/users/${user.id}`, payload, {
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: `User "${form.name}" was updated successfully.`,
                });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err);
                setLoading(false);
                toast({
                    title: 'Error',
                    description: 'There was a problem updating the user.',
                    variant: 'destructive',
                });
            },
        });
    };

    const handleChange = (field: string, value: string) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const n = { ...prev };
                delete n[field];
                return n;
            });
        }
    };

    /* =========================================================
     |  Status change warning
     ========================================================= */

    const statusChanged = form.status !== user.status;

    /* =========================================================
     |  Render
     ========================================================= */

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${user.name}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* ───────── Header ───────── */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <UserIcon className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Edit User</h1>
                        <span className="text-muted-foreground">{user.name}</span>
                    </div>
                    <Link href="/users">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Button>
                    </Link>
                </div>

                {/* ⭐ Branch Manager Banner */}
                {isBranchLocked && (
                    <div className="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                        <Lock className="h-4 w-4" />
                        <span>You can choose users of your own branch. The branch is already set.</span>
                    </div>
                )}

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Edit User Information</CardTitle>
                            <CardDescription>Update the information for user {user.name}.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* ───────── Basic Info ───────── */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                {/* Name */}
                                <div className="space-y-2">
                                    <Label>
                                        Full Name <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        value={form.name}
                                        onChange={(e) => handleChange('name', e.target.value)}
                                        className={errors.name ? 'border-destructive' : ''}
                                    />
                                    {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                                </div>

                                {/* Username */}
                                <div className="space-y-2">
                                    <Label>
                                        Username <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        value={form.username}
                                        onChange={(e) => handleChange('username', e.target.value)}
                                        className={errors.username ? 'border-destructive' : ''}
                                    />
                                    {errors.username && <p className="text-destructive text-sm">{errors.username}</p>}
                                </div>

                                {/* Email */}
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

                                {/* Phone */}
                                <div className="space-y-2">
                                    <Label>Phone</Label>
                                    <Input
                                        value={form.phone}
                                        onChange={(e) => handleChange('phone', e.target.value)}
                                        className={errors.phone ? 'border-destructive' : ''}
                                    />
                                    {errors.phone && <p className="text-destructive text-sm">{errors.phone}</p>}
                                </div>

                                {/* Employee Code */}
                                <div className="space-y-2 md:col-span-2">
                                    <Label>Employee Code</Label>
                                    <Input
                                        value={form.employee_code}
                                        onChange={(e) => handleChange('employee_code', e.target.value)}
                                        placeholder="EMP-0001"
                                        className={errors.employee_code ? 'border-destructive' : ''}
                                    />
                                    {errors.employee_code && <p className="text-destructive text-sm">{errors.employee_code}</p>}
                                </div>
                            </div>

                            {/* ───────── Password Section ───────── */}
                            <div className="rounded-lg border border-dashed p-4">
                                <div className="mb-3 flex items-center gap-2">
                                    <Shield className="text-muted-foreground h-4 w-4" />
                                    <span className="text-sm font-medium">Change Password (optional)</span>
                                </div>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>New Password</Label>
                                        <Input
                                            type="password"
                                            value={form.password}
                                            onChange={(e) => handleChange('password', e.target.value)}
                                            placeholder="Leave blank to keep current"
                                            className={errors.password ? 'border-destructive' : ''}
                                        />
                                        {errors.password && <p className="text-destructive text-sm">{errors.password}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Confirm New Password</Label>
                                        <Input
                                            type="password"
                                            value={form.password_confirmation}
                                            onChange={(e) => handleChange('password_confirmation', e.target.value)}
                                            placeholder="Confirm new password"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* ───────── Role / Branch / Status ───────── */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                {/* Role */}
                                <div className="space-y-2">
                                    <Label>
                                        Role <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={form.role} onValueChange={(v) => handleChange('role', v)}>
                                        <SelectTrigger className={errors.role ? 'border-destructive' : ''}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(roles).map(([key, label]) => (
                                                <SelectItem key={key} value={key}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.role && <p className="text-destructive text-sm">{errors.role}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label className="flex items-center gap-1">
                                        Branch
                                        {isBranchLocked && <Lock className="h-3 w-3 text-amber-500" />}
                                    </Label>
                                    {isBranchLocked ? (
                                        <Input
                                            readOnly
                                            value={branches.find((branch) => String(branch.id) === form.branch_id)?.name || 'Your branch'}
                                        />
                                    ) : (
                                        <Select value={form.branch_id} onValueChange={(v) => handleChange('branch_id', v)}>
                                            <SelectTrigger className={errors.branch_id ? 'border-destructive' : ''}>
                                                <SelectValue placeholder="Select branch" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">No branch</SelectItem>
                                                {branches.map((branch) => (
                                                    <SelectItem key={branch.id} value={String(branch.id)}>
                                                        <div className="flex items-center gap-2">
                                                            <Building2 className="h-4 w-4" />
                                                            {branch.name} ({branch.code})
                                                        </div>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                    {isBranchLocked && <p className="text-xs text-amber-600">Users stay on your branch.</p>}
                                    {errors.branch_id && <p className="text-destructive text-sm">{errors.branch_id}</p>}
                                </div>

                                {/* Status */}
                                <div className="space-y-2">
                                    <Label>
                                        Status <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={form.status} onValueChange={(v) => handleChange('status', v as any)}>
                                        <SelectTrigger className={errors.status ? 'border-destructive' : ''}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="inactive">Inactive</SelectItem>
                                            <SelectItem value="blocked">Blocked</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.status && <p className="text-destructive text-sm">{errors.status}</p>}
                                </div>
                            </div>

                            {/* ⭐ Warning if changing to blocked */}
                            {statusChanged && form.status === 'blocked' && (
                                <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>
                                        Setting status to <strong>Blocked</strong> will prevent this user from logging in. Consider using the block
                                        action instead for better tracking.
                                    </span>
                                </div>
                            )}

                            {/* ───────── Submit ───────── */}
                            <div className="flex gap-2 border-t pt-4">
                                <Button type="submit" disabled={loading} className="gap-2">
                                    <Save className="h-4 w-4" />
                                    {loading ? 'Saving...' : 'Update User'}
                                </Button>
                                <Link href={`/users/${user.id}`}>
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
