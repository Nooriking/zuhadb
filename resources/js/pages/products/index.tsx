// resources/js/pages/products/index.tsx

import { FilterField, IndexFilters } from '@/components/index-filters';
import { DeleteAction, EditAction, RowActions, ViewAction } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Barcode as BarcodeIcon, Calendar, Package, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Swal from 'sweetalert2';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Barcode {
    id: number;
    barcode: string;
    barcode_type: string;
    is_primary: boolean;
    current_stock: number;
}

interface ProductVariant {
    id: number;
    product_id: number;
    volume_ml: number | null;
    concentration: string | null;
    stock_quantity: number | null;
    sale_price_afn: string;
    wholesale_price_afn: string;
    wholesale_min_qty: number;
    barcodes: Barcode[];
}

interface Product {
    id: number;
    name: string | null;
    gender: 'male' | 'female' | 'unisex';
    status: 'active' | 'inactive';
    description: string | null;
    brand: { id: number; name: string } | null;
    variants: ProductVariant[];
    variants_count: number;
    created_at: string;
    exchange_rate?: number | null;
    purchase_count?: number;
    total_purchase_afn?: number;
    total_profit_afn?: number;
}

interface Brand {
    id: number;
    name: string;
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
    brands: Brand[];
    monthlyRates?: { year: number; month: number; average_rate: string; days: number }[];
    supplierCredits?: {
        total: number;
        suppliers: { id: number | null; supplier: string; amount: number; count: number }[];
        items: { id: number; name: string; supplier: string | null; amount: number; date: string | null; invoice: string | null }[];
    };
    filters: {
        search: string | null;
        status: string | null;
        brand_id: string | null;
        gender: string | null;
    };
}

/* ═══════════════════════════════════════════════════════
 |  Constants
 ═══════════════════════════════════════════════════════ */

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Products', href: '/products' },
];

const genderLabels: Record<string, string> = {
    male: 'Men',
    female: 'Women',
    unisex: 'Unisex',
};

const concentrationLabels: Record<string, string> = {
    parfum: 'Parfum',
    edp: 'EDP',
    edt: 'EDT',
    edc: 'EDC',
    edt_legere: 'EDT Légère',
    body_mist: 'Body Mist',
    attar: 'Attar',
    oud: 'Oud',
    other: 'Other',
};

const LOW_STOCK_THRESHOLD = 5;

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function ProductsIndex({ products, brands, filters, monthlyRates = [], supplierCredits }: Props) {
    const { toast } = useToast();
    const page = usePage().props as {
        access?: { is_super_admin?: boolean };
        permissions?: { permissions?: string[] };
    };
    const canManage = Boolean(page.access?.is_super_admin);
    const granted = page.permissions?.permissions ?? [];
    const allow = (name: string) => granted.includes('*') || granted.includes(name);
    const canView = allow('products.view');
    const canEdit = allow('products.update');
    const canDelete = allow('products.delete');
    const money = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(value || 0);
    const [search, setSearch] = useState(filters.search || '');
    const [brandFilter, setBrandFilter] = useState(filters.brand_id || 'all');
    const [genderFilter, setGenderFilter] = useState(filters.gender || 'all');

    /* ═══════════ Handlers ═══════════ */

    const runSearch = (
        overrides: {
            search?: string;
            brand_id?: string;
            gender?: string;
        } = {},
    ) => {
        const params = {
            search: (overrides.search ?? search) || undefined,
            brand_id: (overrides.brand_id ?? brandFilter) === 'all' ? undefined : (overrides.brand_id ?? brandFilter),
            gender: (overrides.gender ?? genderFilter) === 'all' ? undefined : (overrides.gender ?? genderFilter),
        };

        router.get('/products', params, {
            preserveScroll: true,
            preserveState: false,
            replace: false,
        });
    };

    const handleSearch = () => runSearch();

    const handleClearSearch = () => {
        setSearch('');
        setBrandFilter('all');
        setGenderFilter('all');
        router.get('/products', {}, { preserveScroll: true, preserveState: false });
    };

    const confirmDelete = async (product: Product) => {
        const result = await Swal.fire({
            icon: 'warning',
            title: 'Are you sure?',
            html: `Product <strong>${product.name || `#${product.id}`}</strong> and all its variants will be moved to trash.`,
            showCancelButton: true,
            confirmButtonText: 'Yes, move to trash',
            confirmButtonColor: '#dc2626',
            cancelButtonText: 'Cancel',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
        });

        if (!result.isConfirmed) return;

        router.delete(`/products/${product.id}`, {
            preserveScroll: true,
            onSuccess: () => toast({ title: 'Moved to Trash', description: 'Product moved to trash.' }),
            onError: () => toast({ title: 'Error', description: 'Cannot delete.', variant: 'destructive' }),
        });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveScroll: true, preserveState: false });
    };

    /* ═══════════ Helpers ═══════════ */

    const formatPrice = (value: string | number | null | undefined) => {
        const num = typeof value === 'string' ? parseFloat(value) : Number(value ?? 0);
        return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num || 0);
    };

    const formatDate = (dateStr: string) => {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    };

    const getVariantDisplayName = (v: ProductVariant): string => {
        const parts: string[] = [];
        if (v.volume_ml) parts.push(`${v.volume_ml}ml`);
        if (v.concentration) parts.push(concentrationLabels[v.concentration] || v.concentration);
        return parts.join(' · ') || `Variant #${v.id}`;
    };

    const getPrimaryBarcode = (v: ProductVariant): Barcode | null => {
        if (!v.barcodes || v.barcodes.length === 0) return null;
        return v.barcodes.find((b) => b.is_primary) || v.barcodes[0];
    };

    const getProductStock = (p: Product): number => {
        return p.variants.reduce((sum, v) => sum + (v.stock_quantity ?? 0), 0);
    };

    const getTotalBarcodes = (p: Product): number => {
        return p.variants.reduce((sum, v) => sum + (v.barcodes?.length ?? 0), 0);
    };

    const getAvgSalePrice = (p: Product): number => {
        if (p.variants.length === 0) return 0;
        const total = p.variants.reduce((sum, v) => sum + parseFloat(v.sale_price_afn || '0'), 0);
        return total / p.variants.length;
    };

    const hasActiveFilters = !!search || brandFilter !== 'all' || genderFilter !== 'all';

    /* ═══════════════════════════════════════════════════════
     |  Render
     ═══════════════════════════════════════════════════════ */

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Products" />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Package className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Products</h1>
                        <Badge variant="outline">{products.total} Products</Badge>
                    </div>
                    <div className="flex gap-2">
                        {canManage && (
                            <Link href="/products/trash">
                                <Button variant="outline" className="gap-2">
                                    <Trash2 className="h-4 w-4" /> Trash
                                </Button>
                            </Link>
                        )}
                        <Link href="/products/receive">
                            <Button variant="outline" className="gap-2">
                                <Plus className="h-4 w-4" /> Add stock
                            </Button>
                        </Link>
                        <Link href="/products/create">
                            <Button className="gap-2">
                                <Plus className="h-4 w-4" /> New Product
                            </Button>
                        </Link>
                    </div>
                </div>

                <IndexFilters
                    search={search}
                    onSearchChange={setSearch}
                    onSearch={handleSearch}
                    onClear={handleClearSearch}
                    showClear={hasActiveFilters}
                    placeholder="Search by name, barcode, or brand..."
                >
                    <FilterField label="Brand">
                        <Select
                            value={brandFilter}
                            onValueChange={(v) => {
                                setBrandFilter(v);
                                runSearch({ brand_id: v });
                            }}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Brand" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Brands</SelectItem>
                                {brands.map((b) => (
                                    <SelectItem key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FilterField>
                    <FilterField label="Gender">
                        <Select
                            value={genderFilter}
                            onValueChange={(v) => {
                                setGenderFilter(v);
                                runSearch({ gender: v });
                            }}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Gender" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="male">Men</SelectItem>
                                <SelectItem value="female">Women</SelectItem>
                                <SelectItem value="unisex">Unisex</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>
                </IndexFilters>

                {monthlyRates.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {monthlyRates.map((row) => (
                            <div key={`${row.year}-${row.month}`} className="rounded-md border px-3 py-2 text-sm">
                                <span className="text-muted-foreground">
                                    {new Date(row.year, row.month - 1, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' })}
                                </span>
                                <span className="ml-2 font-mono font-semibold">{row.average_rate}</span>
                                <span className="text-muted-foreground ml-1 text-xs">avg USD to AFN</span>
                            </div>
                        ))}
                    </div>
                )}

                <div className="rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-semibold text-amber-900">Supplier balance</p>
                            <p className="text-xs text-amber-800">Bills taken from suppliers that are not fully paid.</p>
                        </div>
                        <p className="font-mono text-2xl font-bold text-amber-900">{money(supplierCredits?.total || 0)} AFN</p>
                    </div>
                    {(supplierCredits?.suppliers.length || 0) > 0 && (
                        <div className="mt-3 space-y-1 text-sm">
                            {supplierCredits?.suppliers.map((row) => (
                                <div key={row.supplier} className="flex items-center justify-between border-t border-amber-200 pt-1">
                                    {row.id ? (
                                        <Link href={`/suppliers/${row.id}`} className="hover:underline">
                                            {row.supplier}
                                            <span className="text-amber-800"> · {row.count} bills</span>
                                        </Link>
                                    ) : (
                                        <span>{row.supplier}</span>
                                    )}
                                    <span className="font-mono font-semibold">{money(row.amount)} AFN</span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* ═══════════ Table ═══════════ */}
                <div className="overflow-x-auto rounded-md border [&_td:not(:last-child)]:whitespace-normal [&_th:not(:last-child)]:whitespace-normal">
                    <Table className="table-fixed">
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10">#</TableHead>
                                <TableHead>Product</TableHead>
                                <TableHead className="w-[12%]">Brand</TableHead>
                                <TableHead className="w-[9%]">Gender</TableHead>
                                <TableHead>Variants</TableHead>
                                <TableHead>USD to AFN</TableHead>
                                <TableHead className="text-center">Barcodes</TableHead>
                                <TableHead className="text-right">Avg. Sale Price</TableHead>
                                <TableHead className="text-center">Stock</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead className="w-[148px] min-w-[148px] px-1 text-center whitespace-nowrap">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {products.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={11} className="text-muted-foreground h-24 text-center">
                                        No products found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                products.data.map((product, index) => {
                                    const stock = getProductStock(product);
                                    const isOutOfStock = stock <= 0;
                                    const isLowStock = !isOutOfStock && stock <= LOW_STOCK_THRESHOLD;
                                    const totalBarcodes = getTotalBarcodes(product);
                                    const avgPrice = getAvgSalePrice(product);

                                    return (
                                        <TableRow key={product.id}>
                                            <TableCell className="text-muted-foreground font-mono text-xs">
                                                {products.from ? products.from + index : index + 1}
                                            </TableCell>

                                            {/* Product */}
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div className="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-md border">
                                                        <Package className="text-muted-foreground h-4 w-4" />
                                                    </div>
                                                    <div className="min-w-0">
                                                        {canView ? (
                                                            <Link
                                                                href={`/products/${product.id}`}
                                                                className="hover:text-primary block truncate font-medium hover:underline"
                                                            >
                                                                {product.name || `Product #${product.id}`}
                                                            </Link>
                                                        ) : (
                                                            <span className="block truncate font-medium">{product.name || `Product #${product.id}`}</span>
                                                        )}
                                                        {product.description && (
                                                            <div className="text-muted-foreground line-clamp-1 text-xs">{product.description}</div>
                                                        )}
                                                    </div>
                                                </div>
                                            </TableCell>

                                            {/* Brand */}
                                            <TableCell>
                                                {product.brand ? (
                                                    <Badge variant="outline">{product.brand.name}</Badge>
                                                ) : (
                                                    <span className="text-muted-foreground text-sm">—</span>
                                                )}
                                            </TableCell>

                                            {/* Gender */}
                                            <TableCell>
                                                <Badge variant="secondary">{genderLabels[product.gender] || product.gender}</Badge>
                                            </TableCell>

                                            {/* Variants + Barcodes */}
                                            <TableCell>
                                                <div className="space-y-1.5">
                                                    {product.variants.slice(0, 2).map((v) => {
                                                        const primary = getPrimaryBarcode(v);
                                                        return (
                                                            <div key={v.id} className="text-xs">
                                                                <div>
                                                                    <span className="font-medium">{getVariantDisplayName(v)}</span>
                                                                    <span className="text-muted-foreground ml-1">
                                                                        ({formatPrice(v.sale_price_afn)} AFN)
                                                                    </span>
                                                                </div>
                                                                {primary && (
                                                                    <div className="text-muted-foreground mt-0.5 font-mono text-[10px]">
                                                                        {primary.barcode}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        );
                                                    })}
                                                    {product.variants.length > 2 && (
                                                        <div className="text-muted-foreground text-xs">+{product.variants.length - 2} more</div>
                                                    )}
                                                    {product.variants.length === 0 && (
                                                        <span className="text-muted-foreground text-xs">No variants</span>
                                                    )}
                                                </div>
                                            </TableCell>

                                            <TableCell className="font-mono text-sm">
                                                {product.exchange_rate ? product.exchange_rate : '—'}
                                            </TableCell>

                                            {/* Barcodes Count */}
                                            <TableCell className="text-center">
                                                {totalBarcodes > 0 ? (
                                                    <Badge variant="outline" className="gap-1 font-mono">
                                                        <BarcodeIcon className="h-3 w-3" />
                                                        {totalBarcodes}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground text-sm">0</span>
                                                )}
                                            </TableCell>

                                            {/* Avg Sale Price */}
                                            <TableCell className="text-right font-mono text-sm">
                                                {avgPrice > 0 ? (
                                                    <span className="font-semibold">{formatPrice(avgPrice)}</span>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                                <span className="text-muted-foreground ml-1 text-xs">AFN</span>
                                            </TableCell>

                                            {/* Stock */}
                                            <TableCell className="text-center">
                                                {isOutOfStock ? (
                                                    <Badge variant="destructive" className="gap-1">
                                                        <AlertTriangle className="h-3 w-3" /> Out
                                                    </Badge>
                                                ) : isLowStock ? (
                                                    <Badge variant="outline" className="gap-1 border-amber-500 text-amber-700">
                                                        <AlertTriangle className="h-3 w-3" /> {stock}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">{stock}</Badge>
                                                )}
                                            </TableCell>

                                            {/* Created */}
                                            <TableCell className="text-muted-foreground text-xs">
                                                <div className="flex items-center gap-1">
                                                    <Calendar className="h-3 w-3" />
                                                    {formatDate(product.created_at)}
                                                </div>
                                            </TableCell>

                                            <TableCell className="w-[148px] min-w-[148px] px-1 whitespace-nowrap">
                                                <RowActions>
                                                    {canView && <ViewAction href={`/products/${product.id}`} />}
                                                    {canEdit && <EditAction href={`/products/${product.id}/edit`} />}
                                                    {canDelete && <DeleteAction onClick={() => confirmDelete(product)} />}
                                                </RowActions>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* ═══════════ Pagination ═══════════ */}
                {products.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-muted-foreground text-sm">
                            Showing {products.from || 0} to {products.to || 0} of {products.total} results
                        </div>
                        <div className="flex gap-1">
                            {products.links.map((link, i) => (
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
