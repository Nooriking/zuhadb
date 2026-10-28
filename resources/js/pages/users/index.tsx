// resources/js/pages/users/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { softDeleteItem } from '@/lib/trash-actions';
import { Building2, Edit, Eye, Lock, Plus, Shield, ToggleLeft, ToggleRight, Trash2, Users, X } from 'lucide-react';
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
    branch: { id: number; name: string; code: string } | null;
    created_at: string;
}

interface Branch {
    id: number;
    name: string;
    code: string;
}

type RoleLabels = Record<string, string>;

interface Permissions {
    users_view: boolean;
    users_create: boolean;
    users_update: boolean;
    users_block: boolean;
    users_unblock: boolean;
    branches_view: boolean;
    branches_create: boolean;
    branches_update: boolean;
    branches_delete: boolean;
    is_super_admin: boolean;
    is_admin: boolean;
    is_branch_manager: boolean;
    can_create_users: boolean;
    can_manage_branches: boolean;
}

interface Props {
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    branches: Branch[];
    roles: RoleLabels;
    filters: {
        search: string | null;
        status: string | null;
        branch_id: string | null;
        role: string | null;
    };
    permissions: Permissions;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/users' },
];

/* =========================================================
 |  Component
 ========================================================= */

export default function UsersIndex({ users, branches, roles, filters, permissions }: Props) {
    const { toast } = useToast();
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [branchFilter, setBranchFilter] = useState(filters.branch_id || 'all');
    const [roleFilter, setRoleFilter] = useState(filters.role || 'all');

    const [userToBlock, setUserToBlock] = useState<User | null>(null);
    const [userToUnblock, setUserToUnblock] = useState<User | null>(null);

    /* =========================================================
     |  Filters
     ========================================================= */

    const handleSearch = () => {
        router.get(
            '/users',
            {
                search: search || undefined,
                status: statusFilter === 'all' ? undefined : statusFilter,
                branch_id: branchFilter === 'all' ? undefined : branchFilter,
                role: roleFilter === 'all' ? undefined : roleFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    /* =========================================================
     |  Block / Unblock
     ========================================================= */

    const confirmBlock = () => {
        if (!userToBlock) return;

        router.post(
            `/users/${userToBlock.id}/block`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast({
                        title: 'User Blocked',
                        description: `"${userToBlock.name}" has been blocked.`,
                    });
                    setUserToBlock(null);
                },
                onError: (errors) => {
                    toast({
                        title: 'Block Failed',
                        description: (errors as any).message || 'Cannot block this user.',
                        variant: 'destructive',
                    });
                    setUserToBlock(null);
                },
            },
        );
    };

    const confirmUnblock = () => {
        if (!userToUnblock) return;

        router.post(
            `/users/${userToUnblock.id}/unblock`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast({
                        title: 'User Unblocked',
                        description: `"${userToUnblock.name}" has been unblocked.`,
                    });
                    setUserToUnblock(null);
                },
                onError: (errors) => {
                    toast({
                        title: 'Unblock Failed',
                        description: (errors as any).message || 'Cannot unblock this user.',
                        variant: 'destructive',
                    });
                    setUserToUnblock(null);
                },
            },
        );
    };

    /* =========================================================
     |  Pagination
     ========================================================= */

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    /* =========================================================
     |  Badges
     ========================================================= */

    const getStatusBadge = (status: string) => {
        const map: Record<string, JSX.Element> = {
            active: <Badge variant="success">Active</Badge>,
            inactive: <Badge variant="secondary">Inactive</Badge>,
            blocked: <Badge variant="destructive">Blocked</Badge>,
        };
        return map[status] || map.inactive;
    };

    const getRoleBadge = (role: string) => {
        const isSuper = role === 'super_admin';
        const isAdmin = role === 'admin';

        return (
            <Badge
                variant="outline"
                className={`gap-1 ${
                    isSuper
                        ? 'border-purple-300 bg-purple-50 text-purple-700 dark:bg-purple-900/30'
                        : isAdmin
                          ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30'
                          : ''
                }`}
            >
                <Shield className="h-3 w-3" />
                {roles[role] || role}
            </Badge>
        );
    };

    /* =========================================================
     |  Row Actions Permission Logic
     ========================================================= */

    const canViewUser = (_user: User) => {
        return permissions.users_view;
    };

    const canEditUser = (user: User) => {
        if (!permissions.users_update) return false;

        if (user.role === 'super_admin') {
            return permissions.is_super_admin;
        }

        if (user.role === 'admin') {
            return permissions.is_super_admin;
        }

        if (permissions.is_branch_manager) {
            return true;
        }

        return true;
    };

    const canBlockUser = (user: User) => {
        if (!permissions.users_block) return false;
        if (user.status === 'blocked') return false;

        if (user.role === 'super_admin') return false;

        if (user.role === 'admin') {
            return permissions.is_super_admin;
        }

        return true;
    };

    const canUnblockUser = (user: User) => {
        if (!permissions.users_unblock) return false;
        if (user.status !== 'blocked') return false;

        if (user.role === 'super_admin') return false;

        if (user.role === 'admin') {
            return permissions.is_super_admin;
        }

        return true;
    };

    /* =========================================================
     |  Render
     ========================================================= */

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Management" />

            <div className="flex flex-col gap-6 p-6">
                {/* ───────── Header ───────── */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Users className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">User Management</h1>
                        <Badge variant="outline" className="ml-2">
                            {users.total} Users
                        </Badge>
                    </div>
                    <div className="flex gap-2">
                        {permissions.is_super_admin && (
                            <Link href="/users/trash">
                                <Button variant="outline" className="gap-2">
                                    <Trash2 className="h-4 w-4" /> Trash
                                </Button>
                            </Link>
                        )}

                        {permissions.users_create && (
                            <Link href="/users/create">
                                <Button className="gap-2">
                                    <Plus className="h-4 w-4" /> New User
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search by name, username, email...">
                    <FilterField label="Role">
                        <Select
                            value={roleFilter}
                            onValueChange={(v) => {
                                setRoleFilter(v);
                                setTimeout(handleSearch, 0);
                            }}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="All Roles" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Roles</SelectItem>
                                {Object.entries(roles).map(([key, label]) => (
                                    <SelectItem key={key} value={key}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FilterField>
                    <FilterField label="Status">
                        <Select
                            value={statusFilter}
                            onValueChange={(v) => {
                                setStatusFilter(v);
                                setTimeout(handleSearch, 0);
                            }}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="All Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                                <SelectItem value="blocked">Blocked</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>
                    {!permissions.is_branch_manager && (
                        <FilterField label="Branch">
                            <Select
                                value={branchFilter}
                                onValueChange={(v) => {
                                    setBranchFilter(v);
                                    setTimeout(handleSearch, 0);
                                }}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="All Branches" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Branches</SelectItem>
                                    {branches.map((branch) => (
                                        <SelectItem key={branch.id} value={String(branch.id)}>
                                            {branch.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FilterField>
                    )}
                </IndexFilters>

                {/* ⭐ Branch Manager Info Banner */}
                {permissions.is_branch_manager && (
                    <div className="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                        <Lock className="h-4 w-4" />
                        <span>These are the users of your branch. New users are saved on this branch.</span>
                    </div>
                )}

                {/* ───────── Table ───────── */}
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Username</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground h-24 text-center">
                                        No users found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                users.data.map((user, index) => (
                                    <TableRow key={user.id}>
                                        <TableCell>{users.from ? users.from + index : index + 1}</TableCell>
                                        <TableCell>
                                            <div className="font-medium">{user.name}</div>
                                            <div className="text-muted-foreground text-xs">{user.email || '—'}</div>
                                        </TableCell>
                                        <TableCell>@{user.username}</TableCell>
                                        <TableCell>{getRoleBadge(user.role)}</TableCell>
                                        <TableCell>
                                            {user.branch ? (
                                                <Badge variant="outline" className="gap-1">
                                                    <Building2 className="h-3 w-3" />
                                                    {user.branch.name}
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground text-sm">Not assigned</span>
                                            )}
                                        </TableCell>
                                        <TableCell>{getStatusBadge(user.status)}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-1">
                                                {/* View */}
                                                {canViewUser(user) && (
                                                    <Link href={`/users/${user.id}`}>
                                                        <Button variant="ghost" size="icon" title="View">
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                )}

                                                {/* Edit */}
                                                {canEditUser(user) && (
                                                    <Link href={`/users/${user.id}/edit`}>
                                                        <Button variant="ghost" size="icon" title="Edit">
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                )}

                                                {/* Block Button */}
                                                {canBlockUser(user) && (
                                                    <Button variant="ghost" size="icon" title="Block User" onClick={() => setUserToBlock(user)}>
                                                        <ToggleLeft className="h-4 w-4 text-red-600" />
                                                    </Button>
                                                )}

                                                {/* Unblock Button */}
                                                {canUnblockUser(user) && (
                                                    <Button variant="ghost" size="icon" title="Unblock User" onClick={() => setUserToUnblock(user)}>
                                                        <ToggleRight className="h-4 w-4 text-green-600" />
                                                    </Button>
                                                )}

                                                {permissions.is_super_admin && user.role !== 'super_admin' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Move to Trash"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => void softDeleteItem(user.name, `/users/${user.id}`)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* ───────── Pagination ───────── */}
                {users.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {users.from || 0} to {users.to || 0} of {users.total} results
                        </div>
                        <div className="flex gap-1">
                            {users.links.map((link, i) => (
                                <Button
                                    key={i}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => handlePageChange(link.url)}
                                    disabled={!link.url}
                                    className="min-w-[40px]"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* ═══════════════════════════════════════════════════
                Block Confirmation Modal
            ═══════════════════════════════════════════════════ */}
            {userToBlock && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setUserToBlock(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <Lock className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Block this user?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    User <strong>{userToBlock.name}</strong> will be <strong>blocked</strong>. They will not be able to log in until
                                    unblocked.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setUserToBlock(null)}
                                className="text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setUserToBlock(null)}>
                                Cancel
                            </Button>
                            <Button onClick={confirmBlock} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                                Block User
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* ═══════════════════════════════════════════════════
                Unblock Confirmation Modal
            ═══════════════════════════════════════════════════ */}
            {userToUnblock && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setUserToUnblock(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                <ToggleRight className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Unblock this user?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    User <strong>{userToUnblock.name}</strong> will be <strong>unblocked</strong> and able to log in again.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setUserToUnblock(null)}
                                className="text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setUserToUnblock(null)}>
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
