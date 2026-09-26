// resources/js/pages/brands/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { DeleteAction, EditAction, RowAction, RowActions, ViewAction } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Globe, Plus, Tag, ToggleLeft, ToggleRight, Trash2, X } from 'lucide-react';
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
    const [brandToDelete, setBrandToDelete] = useState<Brand | null>(null);

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

    const confirmDelete = () => {
        if (!brandToDelete) return;

        router.delete(`/brands/${brandToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Moved to Trash', description: `Brand ${brandToDelete.name} moved to trash.` });
                setBrandToDelete(null);
            },
            onError: (errors) => {
                toast({
                    title: 'Delete Failed',
                    description: errors.message || 'Cannot delete this brand.',
                    variant: 'destructive',
                });
                setBrandToDelete(null);
            },
        });
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
                                                    <img
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
                                                <DeleteAction onClick={() => setBrandToDelete(brand)} />
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

            {/* Delete Modal */}
            {brandToDelete && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setBrandToDelete(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <AlertTriangle className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Are you sure?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Brand <strong>{brandToDelete.name}</strong> will be moved to trash.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setBrandToDelete(null)}
                                className="text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                            <Button variant="outline" onClick={() => setBrandToDelete(null)}>
                                Cancel
                            </Button>
                            <Button onClick={confirmDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                                Move to Trash
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
