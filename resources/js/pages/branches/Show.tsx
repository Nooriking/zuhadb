// resources/js/pages/branches/Show.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2, Calendar, Edit, Mail, MapPin, Package, Phone, ShoppingBag, Users, Wallet } from 'lucide-react';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

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
    manager: { id: number; name: string; email: string } | null;
    user: { id: number; name: string; email: string } | null;
    users: { id: number; name: string; username: string; email: string; status: string }[];
    created_at: string;
}

interface Stats {
    total_users: number;
    total_sales: number;
    total_purchases: number;
    total_expenses: number;
}

interface Props {
    branch: Branch;
    stats: Stats;
}

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function BranchesShow({ branch, stats }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Branches', href: '/branches' },
        { title: branch.name, href: `/branches/${branch.id}` },
    ];

    const getStatusBadge = (status: string) =>
        status === 'active' ? <Badge variant="success">Active</Badge> : <Badge variant="destructive">Inactive</Badge>;

    const formatDate = (date: string) =>
        new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`View ${branch.name}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Building2 className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">{branch.name}</h1>
                        {getStatusBadge(branch.status)}
                        <Badge variant="outline" className="font-mono">
                            {branch.code}
                        </Badge>
                    </div>
                    <div className="flex gap-2">
                        <Link href={`/branches/${branch.id}/edit`}>
                            <Button variant="outline" className="gap-2">
                                <Edit className="h-4 w-4" /> Edit
                            </Button>
                        </Link>
                        <Link href="/branches">
                            <Button variant="outline" className="gap-2">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </Button>
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Info */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Branch Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-muted-foreground text-sm">Branch Name</p>
                                    <p className="text-lg font-medium">{branch.name}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">Branch Code</p>
                                    <p className="font-mono text-lg font-medium">{branch.code}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">Branch Manager</p>
                                    {branch.manager ? (
                                        <div>
                                            <p className="font-medium">{branch.manager.name}</p>
                                            <p className="text-muted-foreground text-xs">{branch.manager.email}</p>
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground">Unassigned</p>
                                    )}
                                </div>
                            </div>

                            <Separator />

                            <div className="space-y-3">
                                {branch.phone && (
                                    <div className="flex items-center gap-3">
                                        <Phone className="text-muted-foreground h-5 w-5" />
                                        <span dir="ltr">{branch.phone}</span>
                                    </div>
                                )}
                                {branch.email && (
                                    <div className="flex items-center gap-3">
                                        <Mail className="text-muted-foreground h-5 w-5" />
                                        <span>{branch.email}</span>
                                    </div>
                                )}
                                {(branch.country || branch.province || branch.district) && (
                                    <div className="flex items-start gap-3">
                                        <MapPin className="text-muted-foreground mt-0.5 h-5 w-5" />
                                        <span>{[branch.district, branch.province, branch.country].filter(Boolean).join(', ')}</span>
                                    </div>
                                )}
                                {branch.address && (
                                    <div className="flex items-start gap-3">
                                        <MapPin className="text-muted-foreground mt-0.5 h-5 w-5" />
                                        <span>{branch.address}</span>
                                    </div>
                                )}
                            </div>

                            <Separator />

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-muted-foreground text-sm">Created By</p>
                                    {branch.user ? (
                                        <p className="font-medium">{branch.user.name}</p>
                                    ) : (
                                        <p className="text-muted-foreground">Unknown</p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">Created At</p>
                                    <div className="flex items-center gap-2">
                                        <Calendar className="text-muted-foreground h-4 w-4" />
                                        <span>{formatDate(branch.created_at)}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Stats */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Quick Stats</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between border-b pb-3">
                                <div className="flex items-center gap-3">
                                    <Users className="h-5 w-5 text-blue-500" />
                                    <span>Users</span>
                                </div>
                                <span className="text-xl font-bold">{stats.total_users}</span>
                            </div>
                            <div className="flex items-center justify-between border-b pb-3">
                                <div className="flex items-center gap-3">
                                    <ShoppingBag className="h-5 w-5 text-green-500" />
                                    <span>Sales</span>
                                </div>
                                <span className="text-xl font-bold">{stats.total_sales}</span>
                            </div>
                            <div className="flex items-center justify-between border-b pb-3">
                                <div className="flex items-center gap-3">
                                    <Package className="h-5 w-5 text-orange-500" />
                                    <span>Purchases</span>
                                </div>
                                <span className="text-xl font-bold">{stats.total_purchases}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <Wallet className="h-5 w-5 text-red-500" />
                                    <span>Expenses</span>
                                </div>
                                <span className="text-xl font-bold">{new Intl.NumberFormat('en-US').format(stats.total_expenses)} AFN</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Users */}
                {branch.users && branch.users.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Users in this Branch</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {branch.users.map((u) => (
                                    <div key={u.id} className="flex items-center justify-between rounded-lg border p-3">
                                        <div>
                                            <p className="font-medium">{u.name}</p>
                                            <p className="text-muted-foreground text-sm">{u.username}</p>
                                        </div>
                                        <Badge variant={u.status === 'active' ? 'success' : 'destructive'}>
                                            {u.status === 'active' ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
