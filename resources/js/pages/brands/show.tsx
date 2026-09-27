// resources/js/pages/brands/show.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Edit, Package, Tag } from 'lucide-react';

interface Product {
    id: number;
    name: string;
    image: string | null;
    status: string;
}

interface Brand {
    id: number;
    name: string;
    country: string | null;
    description: string | null;
    logo: string | null;
    status: 'active' | 'inactive';
    creator: { id: number; name: string; email: string } | null;
    products: Product[];
    created_at: string;
    updated_at: string;
}

interface Props {
    brand: Brand;
    stats: {
        total_products: number;
    };
}

export default function BrandsShow({ brand, stats }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Brands', href: '/brands' },
        { title: brand.name, href: `/brands/${brand.id}` },
    ];

    const formatDate = (date: string | null) => {
        if (!date) return '—';
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const statusColors = {
        active: 'bg-green-100 text-green-800 border-green-200',
        inactive: 'bg-gray-100 text-gray-800 border-gray-200',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`View ${brand.name}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        {brand.logo ? (
                            <img loading="lazy" decoding="async" src={`/storage/${brand.logo}`} alt={brand.name} className="h-16 w-16 rounded-lg border object-contain" />
                        ) : (
                            <div className="flex h-16 w-16 items-center justify-center rounded-lg border bg-gray-100">
                                <Tag className="h-8 w-8 text-gray-400" />
                            </div>
                        )}
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold tracking-tight">{brand.name}</h1>
                                <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusColors[brand.status]}`}>
                                    {brand.status.charAt(0).toUpperCase() + brand.status.slice(1)}
                                </span>
                            </div>
                            {brand.country && <p className="text-muted-foreground mt-1 text-sm">{brand.country}</p>}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link href={`/brands/${brand.id}/edit`}>
                            <button className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                <Edit className="h-4 w-4" /> Edit
                            </button>
                        </Link>
                        <Link href="/brands">
                            <button className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </button>
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Info */}
                    <div className="rounded-lg border bg-white shadow-sm lg:col-span-2">
                        <div className="border-b px-6 py-4">
                            <h2 className="text-lg font-semibold">Brand Information</h2>
                        </div>
                        <div className="space-y-4 p-6">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm text-gray-500">Brand Name</p>
                                    <p className="text-lg font-medium">{brand.name}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Country</p>
                                    <p className="font-medium">{brand.country || '—'}</p>
                                </div>
                            </div>

                            {brand.description && (
                                <>
                                    <hr className="border-gray-200" />
                                    <div>
                                        <p className="text-sm text-gray-500">Description</p>
                                        <p className="mt-1 text-sm">{brand.description}</p>
                                    </div>
                                </>
                            )}

                            <hr className="border-gray-200" />

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm text-gray-500">Created By</p>
                                    <p className="font-medium">{brand.creator?.name || 'System'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Created At</p>
                                    <p className="text-sm">{formatDate(brand.created_at)}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Stats */}
                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-6 py-4">
                            <h2 className="text-lg font-semibold">Quick Stats</h2>
                        </div>
                        <div className="space-y-4 p-6">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <Package className="h-5 w-5 text-blue-500" />
                                    <span className="text-sm">Products</span>
                                </div>
                                <span className="text-2xl font-bold">{stats.total_products}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Products List */}
                {brand.products && brand.products.length > 0 && (
                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-6 py-4">
                            <h2 className="text-lg font-semibold">Products from {brand.name}</h2>
                        </div>
                        <div className="grid grid-cols-1 gap-3 p-6 sm:grid-cols-2 lg:grid-cols-4">
                            {brand.products.map((product) => (
                                <Link
                                    key={product.id}
                                    href={`/products/${product.id}`}
                                    className="hover:bg-muted/50 flex items-center gap-3 rounded-lg border p-3 transition-colors"
                                >
                                    {product.image ? (
                                        <img loading="lazy" decoding="async"
                                            src={`/storage/${product.image}`}
                                            alt={product.name}
                                            className="h-12 w-12 rounded-md border object-cover"
                                        />
                                    ) : (
                                        <div className="bg-muted flex h-12 w-12 items-center justify-center rounded-md border">
                                            <Package className="text-muted-foreground h-6 w-6" />
                                        </div>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">{product.name}</p>
                                        <p className="text-muted-foreground text-xs">{product.status === 'active' ? 'Active' : 'Inactive'}</p>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
