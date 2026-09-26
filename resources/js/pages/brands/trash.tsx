// resources/js/pages/brands/trash.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RotateCcw, Tag, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface Brand {
    id: number;
    name: string;
    country: string | null;
    logo: string | null;
    status: 'active' | 'inactive';
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
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Brands', href: '/brands' },
    { title: 'Trash', href: '/brands/trash' },
];

export default function BrandsTrash({ brands }: Props) {
    const { toast } = useToast();
    const [brandToDelete, setBrandToDelete] = useState<Brand | null>(null);

    const handleRestore = (brand: Brand) => {
        router.post(
            `/brands/${brand.id}/restore`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast({ title: 'Restored', description: `Brand ${brand.name} was restored.` }),
                onError: () => toast({ title: 'Error', description: 'Failed to restore.', variant: 'destructive' }),
            },
        );
    };

    const confirmForceDelete = () => {
        if (!brandToDelete) return;

        router.delete(`/brands/${brandToDelete.id}/force-delete`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Deleted', description: `Brand ${brandToDelete.name} was permanently deleted.` });
                setBrandToDelete(null);
            },
            onError: () => {
                toast({ title: 'Error', description: 'Failed to delete.', variant: 'destructive' });
                setBrandToDelete(null);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trashed Brands" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Trash2 className="text-destructive h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Trashed Brands</h1>
                        <Badge variant="outline">{brands.total} Brands</Badge>
                    </div>
                    <Link href="/brands">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back to Brands
                        </Button>
                    </Link>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Brand</TableHead>
                                <TableHead>Country</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {brands.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-muted-foreground h-24 text-center">
                                        <div className="flex flex-col items-center gap-2">
                                            <Tag className="text-muted-foreground h-8 w-8" />
                                            <p>Trash is empty.</p>
                                        </div>
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
                                                        className="h-8 w-8 rounded-md border object-contain"
                                                    />
                                                ) : (
                                                    <div className="bg-muted flex h-8 w-8 items-center justify-center rounded-md border">
                                                        <Tag className="text-muted-foreground h-4 w-4" />
                                                    </div>
                                                )}
                                                <span className="font-medium">{brand.name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>{brand.country || '—'}</TableCell>
                                        <TableCell>
                                            {brand.status === 'active' ? (
                                                <Badge variant="success">Active</Badge>
                                            ) : (
                                                <Badge variant="secondary">Inactive</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-1">
                                                <Button variant="ghost" size="icon" title="Restore" onClick={() => handleRestore(brand)}>
                                                    <RotateCcw className="h-4 w-4 text-green-600" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Delete Forever"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => setBrandToDelete(brand)}
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

            {brandToDelete && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setBrandToDelete(null)}>
                    <div className="bg-background w-full max-w-md rounded-lg border shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-start gap-4 p-6">
                            <div className="bg-destructive/10 text-destructive flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <Trash2 className="h-5 w-5" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-semibold">Delete forever?</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Brand <strong>{brandToDelete.name}</strong> will be <strong>permanently deleted</strong>. This action cannot be
                                    undone.
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
                            <Button onClick={confirmForceDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                                Delete Forever
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
