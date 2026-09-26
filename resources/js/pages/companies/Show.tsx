// resources/js/pages/companies/Show.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2, Calendar, DollarSign, Edit, Mail, MapPin, Phone, Store, User } from 'lucide-react';

interface Company {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    currency: string;
    logo: string | null;
    status: 'active' | 'inactive';
    user: { id: number; name: string; email: string } | null;
    branches: { id: number; name: string; code: string; status: string }[];
    created_at: string;
    updated_at: string;
}

interface Stats {
    total_branches: number;
    total_users: number;
}

interface Props {
    company: Company;
    stats: Stats;
}

export default function CompaniesShow({ company, stats }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Companies', href: '/companies' },
        { title: company.name, href: `/companies/${company.id}` },
    ];

    const getStatusBadge = (status: string) => {
        return status === 'active' ? (
            <Badge variant="success" className="text-sm">
                Active
            </Badge>
        ) : (
            <Badge variant="destructive" className="text-sm">
                Inactive
            </Badge>
        );
    };

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`View ${company.name}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        {company.logo ? (
                            <img src={`/storage/${company.logo}`} alt={company.name} className="h-14 w-14 rounded-lg border object-contain" />
                        ) : (
                            <div className="bg-muted flex h-14 w-14 items-center justify-center rounded-lg border">
                                <Building2 className="text-muted-foreground h-7 w-7" />
                            </div>
                        )}
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold tracking-tight">{company.name}</h1>
                                {getStatusBadge(company.status)}
                            </div>
                            <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                <DollarSign className="h-4 w-4" />
                                <span>{company.currency}</span>
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link href={`/companies/${company.id}/edit`}>
                            <Button variant="outline" className="gap-2">
                                <Edit className="h-4 w-4" />
                                Edit
                            </Button>
                        </Link>
                        <Link href="/companies">
                            <Button variant="outline" className="gap-2">
                                <ArrowLeft className="h-4 w-4" />
                                Back
                            </Button>
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Info Card */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Company Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-muted-foreground text-sm">Company Name</p>
                                    <p className="text-lg font-medium">{company.name}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">Currency</p>
                                    <Badge variant="outline" className="text-base">
                                        {company.currency}
                                    </Badge>
                                </div>
                            </div>

                            <Separator />

                            <div className="space-y-3">
                                {company.phone && (
                                    <div className="flex items-center gap-3">
                                        <Phone className="text-muted-foreground h-5 w-5" />
                                        <span dir="ltr">{company.phone}</span>
                                    </div>
                                )}
                                {company.email && (
                                    <div className="flex items-center gap-3">
                                        <Mail className="text-muted-foreground h-5 w-5" />
                                        <span>{company.email}</span>
                                    </div>
                                )}
                                {company.address && (
                                    <div className="flex items-start gap-3">
                                        <MapPin className="text-muted-foreground mt-0.5 h-5 w-5" />
                                        <span>{company.address}</span>
                                    </div>
                                )}
                            </div>

                            <Separator />

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-muted-foreground text-sm">Created By</p>
                                    {company.user ? (
                                        <div className="flex items-center gap-2">
                                            <User className="text-muted-foreground h-4 w-4" />
                                            <div>
                                                <p className="font-medium">{company.user.name}</p>
                                                <p className="text-muted-foreground text-xs">{company.user.email}</p>
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground">Unknown</p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">Created At</p>
                                    <div className="flex items-center gap-2">
                                        <Calendar className="text-muted-foreground h-4 w-4" />
                                        <span>{formatDate(company.created_at)}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Stats Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Quick Stats</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between border-b pb-3">
                                <div className="flex items-center gap-3">
                                    <Store className="h-5 w-5 text-blue-500" />
                                    <span>Branches</span>
                                </div>
                                <span className="text-xl font-bold">{stats.total_branches}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <User className="h-5 w-5 text-green-500" />
                                    <span>Users</span>
                                </div>
                                <span className="text-xl font-bold">{stats.total_users}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Branches List */}
                {company.branches && company.branches.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Branches in this Company</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {company.branches.map((branch) => (
                                    <Link
                                        key={branch.id}
                                        href={`/branches/${branch.id}`}
                                        className="hover:bg-muted/50 flex items-center justify-between rounded-lg border p-3 transition-colors"
                                    >
                                        <div>
                                            <p className="font-medium">{branch.name}</p>
                                            <p className="text-muted-foreground font-mono text-sm">{branch.code}</p>
                                        </div>
                                        <Badge variant={branch.status === 'active' ? 'success' : 'destructive'}>
                                            {branch.status === 'active' ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </Link>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
