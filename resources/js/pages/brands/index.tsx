// resources/js/pages/brands/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { DeleteAction, EditAction, RowAction, RowActions, ViewAction } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { softDeleteItem } from '@/lib/trash-actions';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Globe, Plus, Tag, ToggleLeft, ToggleRight, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Brand {
    id: number;
    name: string;
    country: string | null;
    description: string | null;
    logo: string | null;
    status: 'active' | 'inactive';
    products_count: number;
    creator: { id: number; name: string } | null;
    created_at: string;
}

interface Props {
    brands: {
        data: Brand[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        search: string | null;
        status: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Brands', href: '/brands' },
];

export default function BrandsIndex({ brands, filters }: Props) {
    const { toast } = useToast();
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');

    const handleSearch = () => {
        router.get(
            '/brands',
            {
                search: search || undefined,
                status: statusFilter === 'all' ? undefined : statusFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleToggleStatus = (brand: Brand) => {
        router.patch(
            `/brands/${brand.id}/toggle-status`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Status Changed', description: `Brand ${brand.name} status updated.` }),
                onError: () => toast({ title: 'Error', description: 'An error occurred.', variant: 'destructive' }),
            },
        );
    };

    const handleSoftDelete = (brand: Brand) => {
        void softDeleteItem(brand.name, `/brands/${brand.id}`);
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Brands" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Tag className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Brands</h1>
                        <Badge variant="outline">{brands.total} Brands</Badge>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/brands/trash">
                            <Button variant="outline" className="gap-2">
                                <Trash2 className="h-4 w-4" /> Trash
                            </Button>
                        </Link>
                        <Link href="/brands/create">
                            <Button className="gap-2">
                                <Plus className="h-4 w-4" /> New Brand
                            </Button>
                        </Link>
                    </div>
                </div>

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search by name or country...">
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
                            </SelectContent>
                        </Select>
                    </FilterField>
                </IndexFilters>

                {/* Table */}
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Brand</TableHead>
                                <TableHead>Country</TableHead>
                                <TableHead className="text-center">Products</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {brands.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground h-24 text-center">
                                        No brands found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                brands.data.map((brand, index) => (
                                    <TableRow key={brand.id}>
                                        <TableCell>{brands.from ? brands.from + index : index + 1}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                {brand.logo ? (
                                                    <img loading="lazy" decoding="async"
                                                        src={`/storage/${brand.logo}`}
                                                        alt={brand.name}
                                                        className="h-10 w-10 rounded-md border object-contain"
                                                    />
                                                ) : (
                                                    <div className="bg-muted flex h-10 w-10 items-center justify-center rounded-md border">
                                                        <Tag className="text-muted-foreground h-5 w-5" />
                                                    </div>
                                                )}
                                                <div>
                                                    <div className="font-medium">{brand.name}</div>
                                                    {brand.description && (
                                                        <div className="text-muted-foreground max-w-xs truncate text-xs">{brand.description}</div>
                                                    )}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {brand.country ? (
                                                <div className="flex items-center gap-1 text-sm">
                                                    <Globe className="text-muted-foreground h-3 w-3" />
                                                    {brand.country}
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <Badge variant="secondary">{brand.products_count}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            {brand.status === 'active' ? (
                                                <Badge variant="success">Active</Badge>
                                            ) : (
                                                <Badge variant="secondary">Inactive</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <RowActions>
                                                <ViewAction href={`/brands/${brand.id}`} />
                                                <EditAction href={`/brands/${brand.id}/edit`} />
                                                <RowAction title="Toggle Status" onClick={() => handleToggleStatus(brand)}>
                                                    {brand.status === 'active' ? (
                                                        <ToggleRight className="h-4 w-4 text-green-600" />
                                                    ) : (
                                                        <ToggleLeft className="h-4 w-4 text-gray-400" />
                                                    )}
                                                </RowAction>
                                                <DeleteAction onClick={() => handleSoftDelete(brand)} />
                                            </RowActions>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination */}
                {brands.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {brands.from || 0} to {brands.to || 0} of {brands.total} results
                        </div>
                        <div className="flex gap-1">
                            {brands.links.map((link, i) => (
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
        </AppLayout>
    );
}
