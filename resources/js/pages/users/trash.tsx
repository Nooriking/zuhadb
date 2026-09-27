// resources/js/pages/users/trash.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import AppLayout from '@/layouts/app-layout';
import { forceDeleteItem, restoreItem } from '@/lib/trash-actions';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2, RotateCcw, Shield, Trash2, Users } from 'lucide-react';

interface User {
    id: number;
    name: string;
    username: string;
    email: string | null;
    phone: string | null;
    role: string;
    status: 'active' | 'inactive' | 'blocked';
    branch: { id: number; name: string; code: string } | null;
}

type RoleLabels = Record<string, string>;

interface Props {
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    roles: RoleLabels;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/users' },
    { title: 'Trash', href: '/users/trash' },
];

export default function UsersTrash({ users, roles }: Props) {

    const getStatusBadge = (status: string) => {
        const map = {
            active: <Badge variant="success">Active</Badge>,
            inactive: <Badge variant="secondary">Inactive</Badge>,
            blocked: <Badge variant="destructive">Blocked</Badge>,
        };
        return map[status as keyof typeof map] || map.inactive;
    };

    const getRoleBadge = (role: string) => (
        <Badge variant="outline" className="gap-1">
            <Shield className="h-3 w-3" />
            {roles[role] || role}
        </Badge>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trashed Users" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Trash2 className="text-destructive h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Trashed Users</h1>
                        <Badge variant="outline">{users.total} Users</Badge>
                    </div>
                    <Link href="/users">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back to Users
                        </Button>
                    </Link>
                </div>

                {/* Table */}
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
                                        <div className="flex flex-col items-center gap-2">
                                            <Users className="text-muted-foreground h-8 w-8" />
                                            <p>Trash is empty.</p>
                                        </div>
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
                                                <Button variant="ghost" size="icon" title="Restore" onClick={() => void restoreItem(user.name, `/users/${user.id}/restore`)}>
                                                    <RotateCcw className="h-4 w-4 text-green-600" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Delete Forever"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => void forceDeleteItem(user.name, `/users/${user.id}/force-delete`)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {/* Force Delete Confirmation Modal */}

        </AppLayout>
    );
}
