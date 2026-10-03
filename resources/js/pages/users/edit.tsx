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
import { AlertTriangle, ArrowLeft, Building2, CheckCircle2, Eye, EyeOff, Lock, Save, Shield, User as UserIcon, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';

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
 |  Password strength helper (اختیاری و ساده)
 ========================================================= */

function getPasswordStrength(pw: string): { score: number; label: string; color: string } {
    if (!pw) return { score: 0, label: '', color: '' };

    let score = 0;
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[a-z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    if (score <= 2) return { score, label: 'Weak', color: 'bg-red-500' };
    if (score === 3) return { score, label: 'Medium', color: 'bg-amber-500' };
    if (score === 4) return { score, label: 'Good', color: 'bg-blue-500' };
    return { score, label: 'Strong', color: 'bg-green-500' };
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
        status: user.status,
    });

    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    /* ⭐ نمایش / مخفی کردن پسورد */
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirm, setShowPasswordConfirm] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/users' },
        { title: user.name, href: `/users/${user.id}` },
        { title: 'Edit', href: `/users/${user.id}/edit` },
    ];

    /* =========================================================
     |  Password checks
     ========================================================= */

    const passwordStrength = useMemo(() => getPasswordStrength(form.password), [form.password]);

    const passwordMismatch = form.password_confirmation.length > 0 && form.password !== form.password_confirmation;

    const passwordMatch = form.password.length > 0 && form.password === form.password_confirmation;

    const wantsToChangePassword = form.password.length > 0;

    /* =========================================================
     |  Submit
     ========================================================= */

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        // اگر کاربر پسورد جدید وارد نکرده، فیلدها را حذف کن
        const payload: Record<string, any> = {
            ...form,
            branch_id: form.branch_id === 'none' ? null : form.branch_id,
        };

        if (!payload.password) {
            delete payload.password;
            delete payload.password_confirmation;
        }

        // ✅ اصلاح شده: PUT به جای PATCH (مطابق روت لاراول)
        router.put(`/users/${user.id}`, payload, {
            preserveScroll: true,
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
                            </div>

                            {/* ───────── Password Section ⭐ ───────── */}
                            <div className="rounded-lg border border-dashed p-4">
                                <div className="mb-3 flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Shield className="text-muted-foreground h-4 w-4" />
                                        <span className="text-sm font-medium">Change Password (optional)</span>
                                    </div>
                                    {wantsToChangePassword && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                handleChange('password', '');
                                                handleChange('password_confirmation', '');
                                            }}
                                            className="text-muted-foreground hover:text-destructive text-xs underline"
                                        >
                                            Clear
                                        </button>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    {/* New password with eye toggle */}
                                    <div className="space-y-2">
                                        <Label>New Password</Label>
                                        <div className="relative">
                                            <Input
                                                type={showPassword ? 'text' : 'password'}
                                                value={form.password}
                                                onChange={(e) => handleChange('password', e.target.value)}
                                                placeholder="Leave blank to keep current"
                                                className={`pr-10 ${errors.password ? 'border-destructive' : ''}`}
                                                autoComplete="new-password"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowPassword((v) => !v)}
                                                className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 -translate-y-1/2 rounded p-1 transition"
                                                tabIndex={-1}
                                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                                            >
                                                {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                            </button>
                                        </div>

                                        {/* Strength bar */}
                                        {wantsToChangePassword && (
                                            <div className="space-y-1">
                                                <div className="bg-muted h-1.5 w-full overflow-hidden rounded-full">
                                                    <div
                                                        className={`h-full ${passwordStrength.color} transition-all`}
                                                        style={{ width: `${(passwordStrength.score / 5) * 100}%` }}
                                                    />
                                                </div>
                                                <p className="text-muted-foreground text-xs">
                                                    Strength: <span className="font-medium">{passwordStrength.label}</span>
                                                </p>
                                            </div>
                                        )}

                                        {errors.password && <p className="text-destructive text-sm">{errors.password}</p>}
                                    </div>

                                    {/* Confirm password with eye toggle */}
                                    <div className="space-y-2">
                                        <Label>Confirm New Password</Label>
                                        <div className="relative">
                                            <Input
                                                type={showPasswordConfirm ? 'text' : 'password'}
                                                value={form.password_confirmation}
                                                onChange={(e) => handleChange('password_confirmation', e.target.value)}
                                                placeholder="Confirm new password"
                                                className={`pr-10 ${passwordMismatch ? 'border-destructive' : ''}`}
                                                autoComplete="new-password"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowPasswordConfirm((v) => !v)}
                                                className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 -translate-y-1/2 rounded p-1 transition"
                                                tabIndex={-1}
                                                aria-label={showPasswordConfirm ? 'Hide password' : 'Show password'}
                                            >
                                                {showPasswordConfirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                            </button>
                                        </div>

                                        {/* Match indicator */}
                                        {form.password_confirmation.length > 0 && (
                                            <p className={`flex items-center gap-1 text-xs ${passwordMatch ? 'text-green-600' : 'text-destructive'}`}>
                                                {passwordMatch ? (
                                                    <>
                                                        <CheckCircle2 className="h-3 w-3" /> Passwords match
                                                    </>
                                                ) : (
                                                    <>
                                                        <XCircle className="h-3 w-3" /> Passwords do not match
                                                    </>
                                                )}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <p className="text-muted-foreground mt-3 text-xs">Leave both fields empty to keep the current password.</p>
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

                                {/* Branch */}
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
