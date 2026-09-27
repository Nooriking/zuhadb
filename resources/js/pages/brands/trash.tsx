import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { forceDeleteItem, restoreItem } from '@/lib/trash-actions';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, RotateCcw, Tag, Trash2 } from 'lucide-react';

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
                                                        loading="lazy"
                                                        decoding="async"
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
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Restore"
                                                    onClick={() => void restoreItem(brand.name, `/brands/${brand.id}/restore`)}
                                                >
                                                    <RotateCcw className="h-4 w-4 text-green-600" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Delete Forever"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => void forceDeleteItem(brand.name, `/brands/${brand.id}/force-delete`)}
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
        </AppLayout>
    );
}
