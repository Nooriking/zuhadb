// resources/js/pages/branches/Index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Edit, Eye, Lock, Plus, Power, PowerOff, ToggleLeft, ToggleRight, X } from 'lucide-react';
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
    name: string;
    code: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    country: string | null;
    province: string | null;
    district: string | null;
    status: 'active' | 'inactive';
    manager: Manager | null;
    user: { id: number; name: string } | null;
    created_at: string;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Permissions {
    branches_view: boolean;
    branches_create: boolean;
    branches_update: boolean;
    branches_deactivate: boolean;
    is_super_admin: boolean;
    is_admin: boolean;
    is_branch_manager: boolean;
}

interface Props {
    branches: PaginatedData<Branch>;
    filters: {
        search: string | null;
        status: string | null;
    };
    permissions: Permissions;
}

/* ═══════════════════════════════════════════════════════
 |  Breadcrumbs
 ═══════════════════════════════════════════════════════ */

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Branches', href: '/branches' },
];

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function BranchesIndex({ branches, filters, permissions }: Props) {
    const { toast } = useToast();
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');

    const [branchToDeactivate, setBranchToDeactivate] = useState<Branch | null>(null);
    const [branchToActivate, setBranchToActivate] = useState<Branch | null>(null);

    /* ─── Handlers ─── */

    const handleSearch = () => {
        router.get(
            '/branches',
            {
                search: search || undefined,
                status: status === 'all' ? undefined : status,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    /* =========================================================
     |  ⭐ Deactivate / Activate — فقط Super Admin
     ========================================================= */

    const confirmDeactivate = () => {
        if (!branchToDeactivate) return;

        router.post(
            `/branches/${branchToDeactivate.id}/deactivate`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast({
                        title: 'Branch Deactivated',
                        description: `"${branchToDeactivate.name}" has been deactivated.`,
                    });
                    setBranchToDeactivate(null);
                },
                onError: (errors) => {
                    toast({
                        title: 'Deactivate Failed',
                        description: (errors as any).message || 'Cannot deactivate this branch.',
                        variant: 'destructive',
                    });
                    setBranchToDeactivate(null);
                },
            },
        );
    };

    const confirmActivate = () => {
        if (!branchToActivate) return;

        router.post(
            `/branches/${branchToActivate.id}/activate`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast({
                        title: 'Branch Activated',
                        description: `"${branchToActivate.name}" has been activated.`,
                    });
                    setBranchToActivate(null);
                },
                onError: (errors) => {
                    toast({
                        title: 'Activate Failed',
                        description: (errors as any).message || 'Cannot activate this branch.',
                        variant: 'destructive',
                    });
                    setBranchToActivate(null);
                },
            },
        );
    };

    /* ─── Badges ─── */

    const getStatusBadge = (status: string) =>
        status === 'active' ? <Badge variant="success">Active</Badge> : <Badge variant="secondary">Inactive</Badge>;

    /* =========================================================
     |  ⭐ Row Action Permissions
     ========================================================= */

    const canViewBranch = (_branch: Branch) => permissions.branches_view;

    const canEditBranch = (_branch: Branch) => permissions.branches_update;

    const canDeactivateBranch = (branch: Branch) => permissions.branches_deactivate && branch.status === 'active';

    const canActivateBranch = (branch: Branch) => permissions.branches_deactivate && branch.status === 'inactive';

    /* ═══════════════════════════════════════════════════════
     |  Render
     ═══════════════════════════════════════════════════════ */

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Branches" />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Building2 className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Branches</h1>
                        <Badge variant="outline">{branches.total} total</Badge>
                    </div>
                    <div className="flex gap-2">
                        {/* ⛔ دکمه Trash حذف شد */}

                        {permissions.branches_create && (
                            <Link href="/branches/create">
                                <Button className="gap-2">
                                    <Plus className="h-4 w-4" />
                                    New Branch
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {/* ═══════════ ⭐ Info Banner — فقط Super Admin ═══════════ */}
                {permissions.is_super_admin && (
                    <div className="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                        <Lock className="h-4 w-4" />
                        <span>
                            Branches cannot be deleted. Use <strong>Deactivate</strong> to disable a branch instead.
                        </span>
                    </div>
                )}

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search by name, code, email, phone...">
                    <FilterField label="Status">
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>
                </IndexFilters>

                {/* ═══════════ Table ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Branches</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {branches.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12">
                                <Building2 className="text-muted-foreground h-12 w-12" />
                                <p className="text-muted-foreground mt-4">No branches found.</p>
                                {permissions.branches_create && (
                                    <Link href="/branches/create" className="mt-4">
                                        <Button className="gap-2">
                                            <Plus className="h-4 w-4" /> Create First Branch
                                        </Button>
                                    </Link>
                                )}
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="px-4 py-3 text-left text-sm font-medium">Branch</th>
                                            <th className="px-4 py-3 text-left text-sm font-medium">Manager</th>
                                            <th className="px-4 py-3 text-left text-sm font-medium">Contact</th>
                                            <th className="px-4 py-3 text-left text-sm font-medium">Location</th>
                                            <th className="px-4 py-3 text-left text-sm font-medium">Status</th>
                                            <th className="px-4 py-3 text-right text-sm font-medium">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {branches.data.map((b) => (
                                            <tr key={b.id} className="hover:bg-muted/50 border-b">
                                                {/* Branch Info */}
                                                <td className="px-4 py-3">
                                                    <div className="font-medium">{b.name}</div>
                                                    <div className="text-muted-foreground font-mono text-xs">{b.code}</div>
                                                </td>

                                                {/* Manager */}
                                                <td className="px-4 py-3 text-sm">
                                                    {b.manager ? (
                                                        <>
                                                            <div>{b.manager.name}</div>
                                                            <div className="text-muted-foreground text-xs">{b.manager.email}</div>
                                                        </>
                                                    ) : (
                                                        <span className="text-muted-foreground">Unassigned</span>
                                                    )}
                                                </td>

                                                {/* Contact */}
                                                <td className="px-4 py-3 text-sm">
                                                    <div>{b.email || '—'}</div>
                                                    <div className="text-muted-foreground text-xs">{b.phone || '—'}</div>
                                                </td>

                                                {/* Location */}
                                                <td className="px-4 py-3 text-sm">
                                                    <div>{b.province || '—'}</div>
                                                    <div className="text-muted-foreground text-xs">{b.country || '—'}</div>
                                                </td>

                                                {/* Status */}
                                                <td className="px-4 py-3">{getStatusBadge(b.status)}</td>

                                                {/* ⭐ Actions — بدون Delete */}
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-end gap-1">
                                                        {/* View */}
                                                        {canViewBranch(b) && (
                                                            <Link href={`/branches/${b.id}`}>
                                                                <Button variant="ghost" size="icon" title="View">
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                            </Link>
                                                        )}

                                                        {/* Edit */}
                                                        {canEditBranch(b) && (
                                                            <Link href={`/branches/${b.id}/edit`}>
                                                                <Button variant="ghost" size="icon" title="Edit">
                                                                    <Edit className="h-4 w-4" />
                                                                </Button>
                                                            </Link>
                                                        )}

                                                        {/* ⭐ Deactivate */}
                                                        {canDeactivateBranch(b) && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                title="Deactivate Branch"
                                                                onClick={() => setBranchToDeactivate(b)}
                                                            >
                                                                <ToggleLeft className="h-4 w-4 text-red-600" />
                                                            </Button>
                                                        )}

                                                        {/* ⭐ Activate */}
                                                        {canActivateBranch(b) && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                title="Activate Branch"
                                                                onClick={() => setBranchToActivate(b)}
                                                            >
                                                                <ToggleRight className="h-4 w-4 text-green-600" />
                                                            </Button>
                                                        )}

                                                        {/* ⛔ Delete Button حذف شد */}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {/* Pagination */}
                        {branches.last_page > 1 && (
                            <div className="mt-6 flex justify-center gap-1">
                                {branches.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url || '#'}
                                        className={`rounded-md border px-3 py-1 text-sm ${
                                            link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'
                                        } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* ═══════════════════════════════════════════════════
                Deactivate Confirmation Modal
            ═══════════════════════════════════════════════════ */}
            {branchToDeactivate && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setBranchToDeactivate(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <PowerOff className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Deactivate this branch?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Branch <strong>{branchToDeactivate.name}</strong> will be <strong>deactivated</strong>. Users assigned to this
                                    branch may lose access.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setBranchToDeactivate(null)}
                                className="text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setBranchToDeactivate(null)}>
                                Cancel
                            </Button>
                            <Button onClick={confirmDeactivate} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                                Deactivate Branch
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* ═══════════════════════════════════════════════════
                Activate Confirmation Modal
            ═══════════════════════════════════════════════════ */}
            {branchToActivate && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setBranchToActivate(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                <Power className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Activate this branch?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Branch <strong>{branchToActivate.name}</strong> will be <strong>activated</strong> and its users will regain
                                    access.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setBranchToActivate(null)}
                                className="text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setBranchToActivate(null)}>
                                Cancel
                            </Button>
                            <Button onClick={confirmActivate} className="bg-emerald-600 text-white hover:bg-emerald-700">
                                Activate Branch
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
