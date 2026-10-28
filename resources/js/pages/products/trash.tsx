import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { forceDeleteItem, restoreItem } from '@/lib/trash-actions';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Package, RotateCcw, Trash2 } from 'lucide-react';

interface Product {
    id: number;
    name: string;
    image: string | null;
    status: 'active' | 'inactive';
    brand: { id: number; name: string } | null;
    category: { id: number; name: string } | null;
}

interface Props {
    products: {
        data: Product[];
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
    { title: 'Products', href: '/products' },
    { title: 'Trash', href: '/products/trash' },
];

export default function ProductsTrash({ products }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trashed Products" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Trash2 className="text-destructive h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Trashed Products</h1>
                        <Badge variant="outline">{products.total} Products</Badge>
                    </div>
                    <Link href="/products">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" /> Back to Products
                        </Button>
                    </Link>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Product</TableHead>
                                <TableHead>Brand</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {products.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground h-24 text-center">
                                        <div className="flex flex-col items-center gap-2">
                                            <Package className="text-muted-foreground h-8 w-8" />
                                            <p>Trash is empty.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                products.data.map((product, index) => (
                                    <TableRow key={product.id}>
                                        <TableCell>{products.from ? products.from + index : index + 1}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                {product.image ? (
                                                    <img
                                                        src={`/storage/${product.image}`}
                                                        alt={product.name}
                                                        loading="lazy"
                                                        decoding="async"
                                                        className="h-10 w-10 rounded-md border object-cover"
                                                    />
                                                ) : (
                                                    <div className="bg-muted flex h-10 w-10 items-center justify-center rounded-md border">
                                                        <Package className="text-muted-foreground h-5 w-5" />
                                                    </div>
                                                )}
                                                <span className="font-medium">{product.name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>{product.brand?.name || '—'}</TableCell>
                                        <TableCell>{product.category?.name || '—'}</TableCell>
                                        <TableCell>
                                            {product.status === 'active' ? (
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
                                                    onClick={() => void restoreItem(product.name, `/products/${product.id}/restore`)}
                                                >
                                                    <RotateCcw className="h-4 w-4 text-green-600" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Delete Forever"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() =>
                                                        void forceDeleteItem(
                                                            product.name,
                                                            `/products/${product.id}/force-delete`,
                                                            'Variants and barcodes will also be removed.',
                                                        )
                                                    }
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
