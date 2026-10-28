// resources/js/pages/users/show.tsx

import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Building2, Calendar, Edit, Lock, Mail, Phone, Shield, ToggleRight, User as UserIcon, X } from 'lucide-react';
import { useState } from 'react';

/* =========================================================
 |  Types
 ========================================================= */

interface User {
    id: number;
    name: string;
    username: string;
    email: string | null;
    phone: string | null;
    role: string;
    status: 'active' | 'inactive' | 'blocked';
    employee_code?: string | null;
    avatar?: string | null;
    two_factor_enabled?: boolean;
    email_verified_at?: string | null;
    last_login_at: string | null;
    created_at: string;
    updated_at: string;
    branch: { id: number; name: string; code: string } | null;
    creator: { id: number; name: string; email: string } | null;
}

interface Stats {
    total_sales: number;
    total_purchases: number;
    total_expenses: number;
}

type RoleLabels = Record<string, string>;

interface Props {
    user: User;
    stats: Stats;
    roles: RoleLabels;
    permissions: {
        can_edit?: boolean;
        can_delete?: boolean;
        can_block_target?: boolean | null;
        can_unblock_target?: boolean | null;
        users_update?: boolean;
        users_block?: boolean;
        users_unblock?: boolean;
        is_super_admin?: boolean;
        is_branch_manager?: boolean;
    };
}

/* =========================================================
 |  Component
 ========================================================= */

export default function UsersShow({ user, stats, roles, permissions }: Props) {
    const { toast } = useToast();

    const [showBlockModal, setShowBlockModal] = useState(false);
    const [showUnblockModal, setShowUnblockModal] = useState(false);

    const canEdit = Boolean(permissions.can_edit ?? permissions.users_update);
    const canBlock = Boolean(permissions.can_block_target ?? permissions.users_block) && user.status !== 'blocked';
    const canUnblock = Boolean(permissions.can_unblock_target ?? permissions.users_unblock) && user.status === 'blocked';
    const canDelete = Boolean(permissions.can_delete);

    /* =========================================================
     |  Breadcrumbs
     ========================================================= */

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/users' },
        { title: user.name, href: `/users/${user.id}` },
    ];

    /* =========================================================
     |  Helpers
     ========================================================= */

    const formatDate = (date: string | null) => {
        if (!date) return '—';
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getInitials = (name: string) =>
        name
            .split(' ')
            .map((n) => n[0])
            .slice(0, 2)
            .join('')
            .toUpperCase();

    const statusColors: Record<string, string> = {
        active: 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-400',
        inactive: 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-800 dark:text-gray-400',
        blocked: 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-400',
    };

    const roleColor = (role: string) => {
        if (role === 'super_admin') return 'border-purple-300 bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300';
        if (role === 'admin') return 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300';
        if (role === 'branch_manager') return 'border-amber-300 bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        return '';
    };

    /* =========================================================
     |  Block / Unblock
     ========================================================= */

    const confirmBlock = () => {
        router.post(
            `/users/${user.id}/block`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast({
                        title: 'User Blocked',
                        description: `"${user.name}" has been blocked.`,
                    });
                    setShowBlockModal(false);
                },
                onError: (errors) => {
                    toast({
                        title: 'Block Failed',
                        description: (errors as any).message || 'Cannot block this user.',
                        variant: 'destructive',
                    });
                    setShowBlockModal(false);
                },
            },
        );
    };

    const confirmUnblock = () => {
        router.post(
            `/users/${user.id}/unblock`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast({
                        title: 'User Unblocked',
                        description: `"${user.name}" has been unblocked.`,
                    });
                    setShowUnblockModal(false);
                },
                onError: (errors) => {
                    toast({
                        title: 'Unblock Failed',
                        description: (errors as any).message || 'Cannot unblock this user.',
                        variant: 'destructive',
                    });
                    setShowUnblockModal(false);
                },
            },
        );
    };

    /* =========================================================
     |  Render
     ========================================================= */

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`View ${user.name}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════════════════════════════════════════
                    Header
                ═══════════════════════════════════════════════ */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        {user.avatar ? (
                            <img loading="lazy" decoding="async" src={`/storage/${user.avatar}`} alt={user.name} className="h-16 w-16 rounded-full border-2 object-cover" />
                        ) : (
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-xl font-bold text-white">
                                {getInitials(user.name)}
                            </div>
                        )}
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold tracking-tight">{user.name}</h1>
                                <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusColors[user.status]}`}>
                                    {user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                                </span>
                            </div>
                            <div className="mt-1 flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                                <span>@{user.username}</span>
                                <span>·</span>
                                <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${roleColor(user.role)}`}>
                                    <Shield className="mr-1 inline h-3 w-3" />
                                    {roles[user.role] || user.role}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* ───────── Action Buttons ───────── */}
                    <div className="flex flex-wrap gap-2">
                        {canBlock && (
                            <Button
                                variant="outline"
                                className="gap-2 border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
                                onClick={() => setShowBlockModal(true)}
                            >
                                <Lock className="h-4 w-4" />
                                Block User
                            </Button>
                        )}

                        {canUnblock && (
                            <Button
                                variant="outline"
                                className="gap-2 border-emerald-200 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-900/20"
                                onClick={() => setShowUnblockModal(true)}
                            >
                                <ToggleRight className="h-4 w-4" />
                                Unblock User
                            </Button>
                        )}

                        {canEdit && (
                            <Link href={`/users/${user.id}/edit`}>
                                <Button variant="outline" className="gap-2">
                                    <Edit className="h-4 w-4" /> Edit
                                </Button>
                            </Link>
                        )}

                        {canDelete && (
                            <Button
                                variant="outline"
                                className="gap-2 border-red-200 text-red-600 hover:bg-red-50"
                                onClick={() => {
                                    if (!window.confirm(`Move "${user.name}" to trash?`)) return;
                                    router.delete(`/users/${user.id}`, { preserveScroll: true });
                                }}
                            >
                                <Lock className="h-4 w-4" /> Trash
                            </Button>
                        )}

                        <Link href="/users">
                            <Button variant="outline" className="gap-2">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* ═══════════════════════════════════════════════
                    Main Grid
                ═══════════════════════════════════════════════ */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* ───────── Info Card ───────── */}
                    <div className="rounded-lg border bg-white shadow-sm lg:col-span-2 dark:border-gray-800 dark:bg-gray-900">
                        <div className="border-b px-6 py-4 dark:border-gray-800">
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <UserIcon className="h-5 w-5" />
                                User Information
                            </h2>
                        </div>
                        <div className="space-y-6 p-6">
                            {/* Basic Info */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Full Name</p>
                                    <p className="text-lg font-medium">{user.name}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Username</p>
                                    <p className="text-lg font-medium">@{user.username}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Role</p>
                                    <p className="mt-1">
                                        <span
                                            className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold ${roleColor(
                                                user.role,
                                            )}`}
                                        >
                                            <Shield className="h-3 w-3" />
                                            {roles[user.role] || user.role}
                                        </span>
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Branch</p>
                                    {user.branch ? (
                                        <Link
                                            href={`/branches/${user.branch.id}`}
                                            className="inline-flex items-center gap-1 font-medium text-blue-600 hover:underline dark:text-blue-400"
                                        >
                                            <Building2 className="h-3.5 w-3.5" />
                                            {user.branch.name} ({user.branch.code})
                                        </Link>
                                    ) : (
                                        <p className="text-gray-500">Not assigned</p>
                                    )}
                                </div>
                                {user.employee_code && (
                                    <div>
                                        <p className="text-sm text-gray-500 dark:text-gray-400">Employee Code</p>
                                        <p className="font-mono font-medium">{user.employee_code}</p>
                                    </div>
                                )}
                            </div>

                            <hr className="border-gray-200 dark:border-gray-800" />

                            {/* Contact Info */}
                            <div className="space-y-3">
                                {user.phone && (
                                    <div className="flex items-center gap-3 text-sm">
                                        <Phone className="h-4 w-4 text-gray-400" />
                                        <span dir="ltr">{user.phone}</span>
                                    </div>
                                )}
                                {user.email && (
                                    <div className="flex items-center gap-3 text-sm">
                                        <Mail className="h-4 w-4 text-gray-400" />
                                        <span>{user.email}</span>
                                    </div>
                                )}
                            </div>

                            <hr className="border-gray-200 dark:border-gray-800" />

                            {/* Meta Info */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Created By</p>
                                    <p className="font-medium">{user.creator?.name || 'System'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Created At</p>
                                    <p className="flex items-center gap-1 text-sm">
                                        <Calendar className="h-3.5 w-3.5 text-gray-400" />
                                        {formatDate(user.created_at)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Last Login</p>
                                    <p className="text-sm">{formatDate(user.last_login_at)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Last Updated</p>
                                    <p className="text-sm">{formatDate(user.updated_at)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Email verified</p>
                                    <p className="text-sm">{formatDate(user.email_verified_at ?? null)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Two-factor authentication</p>
                                    <p className="text-sm font-medium">
                                        {user.two_factor_enabled ? 'Enabled' : 'Disabled'}
                                        <span className="ml-2 text-xs text-gray-500">(read-only; managed in Settings)</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* ───────── Stats Card ───────── */}
                    <div className="rounded-lg border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div className="border-b px-6 py-4 dark:border-gray-800">
                            <h2 className="text-lg font-semibold">Activity Stats</h2>
                        </div>
                        <div className="space-y-4 p-6">
                            <div className="flex items-center justify-between border-b pb-3 dark:border-gray-800">
                                <div className="flex items-center gap-3">
                                    <span className="text-2xl">🛍️</span>
                                    <span className="text-sm">Sales</span>
                                </div>
                                <span className="text-xl font-bold">{stats.total_sales}</span>
                            </div>
                            <div className="flex items-center justify-between border-b pb-3 dark:border-gray-800">
                                <div className="flex items-center gap-3">
                                    <span className="text-2xl">🛒</span>
                                    <span className="text-sm">Purchases</span>
                                </div>
                                <span className="text-xl font-bold">{stats.total_purchases}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <span className="text-2xl">🧾</span>
                                    <span className="text-sm">Expenses</span>
                                </div>
                                <span className="text-xl font-bold">{stats.total_expenses}</span>
                            </div>
                        </div>

                        {/* ⭐ Blocked Status Warning */}
                        {user.status === 'blocked' && (
                            <div className="mx-6 mb-6 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                                <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <span>
                                    This user is <strong>blocked</strong> and cannot log in.
                                </span>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* ═══════════════════════════════════════════════════
                Block Confirmation Modal
            ═══════════════════════════════════════════════════ */}
            {showBlockModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowBlockModal(false)}>
                    <div
                        className="w-full max-w-md rounded-lg border bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-start gap-4 p-6">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                <Lock className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Block this user?</h3>
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    User <strong>{user.name}</strong> will be <strong>blocked</strong>. They will not be able to log in until
                                    unblocked.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowBlockModal(false)}
                                className="text-gray-400 transition-colors hover:text-gray-600"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4 dark:border-gray-800">
                            <Button variant="outline" onClick={() => setShowBlockModal(false)}>
                                Cancel
                            </Button>
                            <Button onClick={confirmBlock} className="bg-red-600 text-white hover:bg-red-700">
                                Block User
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* ═══════════════════════════════════════════════════
                Unblock Confirmation Modal
            ═══════════════════════════════════════════════════ */}
            {showUnblockModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowUnblockModal(false)}>
                    <div
                        className="w-full max-w-md rounded-lg border bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-start gap-4 p-6">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                <ToggleRight className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Unblock this user?</h3>
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    User <strong>{user.name}</strong> will be <strong>unblocked</strong> and able to log in again.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowUnblockModal(false)}
                                className="text-gray-400 transition-colors hover:text-gray-600"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4 dark:border-gray-800">
                            <Button variant="outline" onClick={() => setShowUnblockModal(false)}>
                                Cancel
                            </Button>
                            <Button onClick={confirmUnblock} className="bg-emerald-600 text-white hover:bg-emerald-700">
                                Unblock User
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
