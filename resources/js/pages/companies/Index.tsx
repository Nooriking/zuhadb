// resources/js/pages/companies/Index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Edit, Eye, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Company {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    currency: string;
    logo: string | null;
    status: 'active' | 'inactive';
    user: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    companies: PaginatedData<Company>;
    filters: {
        search: string | null;
        status: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Companies', href: '/companies' },
];

export default function CompaniesIndex({ companies, filters }: Props) {
    const { toast } = useToast();
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');

    const handleSearch = () => {
        router.get('/companies', { search, status: status === 'all' ? '' : status }, { preserveState: true });
    };

    const handleDelete = (company: Company) => {
        if (!confirm(`Are you sure you want to move "${company.name}" to trash?`)) return;

        router.delete(`/companies/${company.id}`, {
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: `Company "${company.name}" was moved to trash.`,
                });
            },
            onError: () => {
                toast({
                    title: 'Error',
                    description: 'There was a problem moving the company to trash.',
                    variant: 'destructive',
                });
            },
        });
    };

    const getStatusBadge = (status: string) => {
        return status === 'active' ? <Badge variant="success">Active</Badge> : <Badge variant="destructive">Inactive</Badge>;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Companies" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Building2 className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Companies</h1>
                        <Badge variant="outline">{companies.total} total</Badge>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/companies/create">
                            <Button className="gap-2">
                                <Plus className="h-4 w-4" />
                                New Company
                            </Button>
                        </Link>
                    </div>
                </div>

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search by name, email, or phone...">
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

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Companies</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {companies.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12">
                                <Building2 className="text-muted-foreground h-12 w-12" />
                                <p className="text-muted-foreground mt-4">No companies found.</p>
                                <Link href="/companies/create" className="mt-4">
                                    <Button className="gap-2">
                                        <Plus className="h-4 w-4" />
                                        Create First Company
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="px-4 py-3 text-left text-sm font-medium">Name</th>
                                            <th className="px-4 py-3 text-left text-sm font-medium">Contact</th>
                                            <th className="px-4 py-3 text-left text-sm font-medium">Currency</th>
                                            <th className="px-4 py-3 text-left text-sm font-medium">Status</th>
                                            <th className="px-4 py-3 text-left text-sm font-medium">Created By</th>
                                            <th className="px-4 py-3 text-right text-sm font-medium">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {companies.data.map((company) => (
                                            <tr key={company.id} className="hover:bg-muted/50 border-b">
                                                <td className="px-4 py-3">
                                                    <div className="font-medium">{company.name}</div>
                                                    {company.address && (
                                                        <div className="text-muted-foreground max-w-xs truncate text-sm">{company.address}</div>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="text-sm">{company.email || '—'}</div>
                                                    <div className="text-muted-foreground text-sm">{company.phone || '—'}</div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant="outline">{company.currency}</Badge>
                                                </td>
                                                <td className="px-4 py-3">{getStatusBadge(company.status)}</td>
                                                <td className="px-4 py-3 text-sm">{company.user?.name || '—'}</td>
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-end gap-2">
                                                        <Link href={`/companies/${company.id}`}>
                                                            <Button variant="ghost" size="icon">
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Link href={`/companies/${company.id}/edit`}>
                                                            <Button variant="ghost" size="icon">
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Button variant="ghost" size="icon" onClick={() => handleDelete(company)}>
                                                            <Trash2 className="text-destructive h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {/* Pagination */}
                        {companies.last_page > 1 && (
                            <div className="mt-6 flex justify-center gap-1">
                                {companies.links.map((link, index) => (
                                    <Link
                                        key={index}
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
        </AppLayout>
    );
}
