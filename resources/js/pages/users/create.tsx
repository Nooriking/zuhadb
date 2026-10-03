import { BackButton } from '@/components/form-page';
// resources/js/pages/users/create.tsx

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    Check,
    CheckCircle2,
    Eye,
    EyeOff,
    Info,
    KeyRound,
    Lock,
    Save,
    ShieldAlert,
    ShieldCheck,
    ShieldX,
    Users,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Branch {
    id: number;
    name: string;
    code: string;
}
type RoleLabels = Record<string, string>;

interface Props {
    branches: Branch[];
    roles: RoleLabels;
    defaultBranchId: number | null;
    isBranchLocked: boolean;
    errors?: Record<string, string>;
}

/* ═══════════════════════════════════════════════════════
 |  Constants
 ═══════════════════════════════════════════════════════ */

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/users' },
    { title: 'Create New User', href: '/users/create' },
];

/* ═══════════════════════════════════════════════════════
 |  Password Strength Calculator
 ═══════════════════════════════════════════════════════ */

interface PasswordStrength {
    score: number;
    level: 'empty' | 'weak' | 'medium' | 'strong' | 'very-strong';
    label: string;
    color: string;
    bgColor: string;
    textColor: string;
    borderColor: string;
    icon: React.ReactNode;
    percent: number;
}

function calculatePasswordStrength(password: string): PasswordStrength {
    if (!password) {
        return {
            score: 0,
            level: 'empty',
            label: 'Enter a password',
            color: 'bg-gray-300',
            bgColor: 'bg-gray-50',
            textColor: 'text-gray-500',
            borderColor: 'border-gray-200',
            icon: <KeyRound className="h-4 w-4" />,
            percent: 0,
        };
    }

    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (password.length >= 16) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    let level: PasswordStrength['level'];
    let label: string;
    let color: string;
    let bgColor: string;
    let textColor: string;
    let borderColor: string;
    let icon: React.ReactNode;
    let percent: number;

    if (password.length < 6 || score <= 2) {
        level = 'weak';
        label = 'Weak';
        color = 'bg-red-500';
        bgColor = 'bg-red-50';
        textColor = 'text-red-700';
        borderColor = 'border-red-300';
        icon = <ShieldX className="h-4 w-4" />;
        percent = 25;
    } else if (score <= 4) {
        level = 'medium';
        label = 'Medium';
        color = 'bg-yellow-500';
        bgColor = 'bg-yellow-50';
        textColor = 'text-yellow-700';
        borderColor = 'border-yellow-300';
        icon = <ShieldAlert className="h-4 w-4" />;
        percent = 55;
    } else if (score <= 5) {
        level = 'strong';
        label = 'Strong';
        color = 'bg-green-500';
        bgColor = 'bg-green-50';
        textColor = 'text-green-700';
        borderColor = 'border-green-300';
        icon = <ShieldCheck className="h-4 w-4" />;
        percent = 80;
    } else {
        level = 'very-strong';
        label = 'Very Strong';
        color = 'bg-emerald-600';
        bgColor = 'bg-emerald-50';
        textColor = 'text-emerald-700';
        borderColor = 'border-emerald-300';
        icon = <ShieldCheck className="h-4 w-4" />;
        percent = 100;
    }

    return { score, level, label, color, bgColor, textColor, borderColor, icon, percent };
}

/* ═══════════════════════════════════════════════════════
 |  Requirements Checklist
 ═══════════════════════════════════════════════════════ */

interface Requirement {
    label: string;
    test: (pw: string) => boolean;
}

const REQUIREMENTS: Requirement[] = [
    { label: 'At least 8 characters', test: (pw) => pw.length >= 8 },
    { label: 'Contains lowercase letter', test: (pw) => /[a-z]/.test(pw) },
    { label: 'Contains uppercase letter', test: (pw) => /[A-Z]/.test(pw) },
    { label: 'Contains number', test: (pw) => /[0-9]/.test(pw) },
    { label: 'Contains special character', test: (pw) => /[^A-Za-z0-9]/.test(pw) },
];

/* ═══════════════════════════════════════════════════════
 |  Main Component
 ═══════════════════════════════════════════════════════ */

export default function UsersCreate({ branches, roles, defaultBranchId, isBranchLocked, errors: initialErrors }: Props) {
    const { toast } = useToast();

    const firstRole = Object.keys(roles)[0] || 'salesperson';

    const [form, setForm] = useState({
        name: '',
        username: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        branch_id: defaultBranchId ? String(defaultBranchId) : 'none',
        role: firstRole,
        status: 'active' as 'active' | 'inactive' | 'blocked',
    });

    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    const strength = useMemo(() => calculatePasswordStrength(form.password), [form.password]);

    const passwordsMatch = useMemo(() => {
        if (!form.password_confirmation) return null;
        return form.password === form.password_confirmation;
    }, [form.password, form.password_confirmation]);

    const requirementStatus = useMemo(() => {
        return REQUIREMENTS.map((req) => ({
            ...req,
            met: req.test(form.password),
        }));
    }, [form.password]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const payload = {
            ...form,
            branch_id: form.branch_id === 'none' ? null : form.branch_id,
        };

        router.post('/users', payload, {
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: `User ${form.name} created successfully.`,
                });
                setLoading(false);
            },
            onError: (err) => {
                setErrors(err);
                setLoading(false);
                toast({
                    title: 'Error',
                    description: 'Please check the form.',
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

    /* ⭐ آیا role انتخاب‌شده Branch Manager است؟ */
    const isBranchManagerRole = form.role === 'branch_manager';
    const branchIsEmpty = form.branch_id === 'none';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create New User" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/users" label="Back" />
                    <div className="flex items-center gap-3">
                        <Users className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Create New User</h1>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>User Information</CardTitle>
                            <CardDescription>Please enter the required information for the new user.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* ═══════════ PASSWORD SECTION ═══════════ */}
                            <div className="rounded-lg border-2 border-blue-200 bg-blue-50/30 p-4">
                                <div className="mb-3 flex items-center gap-2">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-white">
                                        <KeyRound className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold">Password</h3>
                                        <p className="text-muted-foreground text-xs">Choose a strong password for this account</p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    {/* Password */}
                                    <div className="space-y-2">
                                        <Label>
                                            Password <span className="text-destructive">*</span>
                                        </Label>
                                        <div className="relative">
                                            <Input
                                                type={showPassword ? 'text' : 'password'}
                                                value={form.password}
                                                onChange={(e) => handleChange('password', e.target.value)}
                                                placeholder="Enter a strong password"
                                                className={`pr-10 font-mono ${errors.password ? 'border-destructive' : ''}`}
                                                autoComplete="new-password"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowPassword((v) => !v)}
                                                className="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2"
                                                title={showPassword ? 'Hide' : 'Show'}
                                            >
                                                {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                            </button>
                                        </div>
                                        {errors.password && <p className="text-destructive text-sm">{errors.password}</p>}
                                    </div>

                                    {/* Confirm Password */}
                                    <div className="space-y-2">
                                        <Label>
                                            Confirm Password <span className="text-destructive">*</span>
                                        </Label>
                                        <div className="relative">
                                            <Input
                                                type={showConfirmPassword ? 'text' : 'password'}
                                                value={form.password_confirmation}
                                                onChange={(e) => handleChange('password_confirmation', e.target.value)}
                                                placeholder="Repeat the password"
                                                className={`pr-10 font-mono ${
                                                    errors.password_confirmation
                                                        ? 'border-destructive'
                                                        : passwordsMatch === false
                                                          ? 'border-destructive'
                                                          : passwordsMatch === true
                                                            ? 'border-green-500'
                                                            : ''
                                                }`}
                                                autoComplete="new-password"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowConfirmPassword((v) => !v)}
                                                className="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2"
                                                title={showConfirmPassword ? 'Hide' : 'Show'}
                                            >
                                                {showConfirmPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                            </button>
                                        </div>
                                        {errors.password_confirmation && <p className="text-destructive text-sm">{errors.password_confirmation}</p>}
                                        {passwordsMatch === false && !errors.password_confirmation && (
                                            <p className="text-destructive flex items-center gap-1 text-xs">
                                                <X className="h-3 w-3" />
                                                Passwords do not match
                                            </p>
                                        )}
                                        {passwordsMatch === true && (
                                            <p className="flex items-center gap-1 text-xs text-green-600">
                                                <Check className="h-3 w-3" />
                                                Passwords match
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {/* Password Strength */}
                                {form.password && (
                                    <div className={`mt-4 rounded-lg border-2 p-3 ${strength.borderColor} ${strength.bgColor}`}>
                                        <div className="mb-2 flex items-center justify-between">
                                            <div className={`flex items-center gap-2 font-semibold ${strength.textColor}`}>
                                                {strength.icon}
                                                <span>Password Strength: {strength.label}</span>
                                            </div>
                                            <span className={`font-mono text-xs ${strength.textColor}`}>{strength.percent}%</span>
                                        </div>

                                        <div className="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                                            <div
                                                className={`h-full ${strength.color} transition-all duration-300`}
                                                style={{ width: `${strength.percent}%` }}
                                            />
                                        </div>

                                        <div className="mt-3 grid grid-cols-1 gap-1 md:grid-cols-2">
                                            {requirementStatus.map((req, i) => (
                                                <div
                                                    key={i}
                                                    className={`flex items-center gap-2 text-xs ${req.met ? 'text-green-700' : 'text-gray-500'}`}
                                                >
                                                    {req.met ? (
                                                        <CheckCircle2 className="h-3.5 w-3.5 shrink-0" />
                                                    ) : (
                                                        <AlertCircle className="h-3.5 w-3.5 shrink-0 opacity-50" />
                                                    )}
                                                    <span className={req.met ? 'font-medium' : ''}>{req.label}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* ═══════════ USER INFO FIELDS ═══════════ */}
                            <div className="space-y-4">
                                <h3 className="text-sm font-semibold">Account</h3>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    {/* Name */}
                                    <div className="space-y-2">
                                        <Label>
                                            Full Name <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            value={form.name}
                                            onChange={(e) => handleChange('name', e.target.value)}
                                            placeholder="John Doe"
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
                                            placeholder="johndoe"
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
                            </div>

                            {/* ═══════════ ROLE AND BRANCH ═══════════ */}
                            <div className="space-y-4 border-t pt-6">
                                <h3 className="text-sm font-semibold">Role and branch</h3>
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
                                                    <SelectItem value="none">No branch (can be assigned later)</SelectItem>
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
                                        {isBranchLocked && <p className="text-xs text-amber-600">Users are saved on your branch.</p>}
                                        {errors.branch_id && <p className="text-destructive text-sm">{errors.branch_id}</p>}

                                        {/* ⭐ Hint for Branch Manager without branch */}
                                        {isBranchManagerRole && branchIsEmpty && !isBranchLocked && (
                                            <div className="flex items-start gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                                <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                                <span>
                                                    This user will be a Branch Manager without a branch. You can assign them later from the branch
                                                    edit page.
                                                </span>
                                            </div>
                                        )}
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
                                            </SelectContent>
                                        </Select>
                                        {errors.status && <p className="text-destructive text-sm">{errors.status}</p>}
                                    </div>
                                </div>
                            </div>

                            {/* ═══════════ ACTIONS ═══════════ */}
                            <div className="flex gap-2 border-t pt-4">
                                <Button type="submit" disabled={loading} className="gap-2">
                                    <Save className="h-4 w-4" />
                                    {loading ? 'Saving...' : 'Save User'}
                                </Button>
                                <BackButton href="/users" label="Cancel" />
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
