import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface History {
    id: number;
    change_type: string;
    change_type_label: string;
    old_purchase_price_afn: string;
    new_purchase_price_afn: string;
    old_sale_price_afn: string;
    new_sale_price_afn: string;
    reason: string | null;
    created_at: string;
    changer: { id: number; name: string } | null;
}

interface Props {
    variant: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
        product: { id: number; name: string; brand: { id: number; name: string } | null } | null;
    };
    histories: { data: History[] };
}

export default function PriceHistoryByVariant({ variant, histories }: Props) {
    const title = variant.product?.name || `Variant #${variant.id}`;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Price history', href: '/product-price-histories' },
        { title, href: `/product-price-histories/by-variant/${variant.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Price history — ${title}`} />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{title}</h1>
                        <p className="text-muted-foreground text-sm">
                            {variant.product?.brand?.name ? `${variant.product.brand.name} · ` : ''}
                            {variant.volume_ml ? `${variant.volume_ml}ml` : ''}
                            {variant.concentration ? ` · ${variant.concentration}` : ''}
                        </p>
                    </div>
                    <Link href="/product-price-histories" className="rounded-md border px-3 py-2 text-sm">
                        All history
                    </Link>
                </div>
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-black text-white">
                            <tr>
                                <th className="px-4 py-3 text-left">Date</th>
                                <th className="px-4 py-3 text-left">Type</th>
                                <th className="px-4 py-3 text-right">Purchase AFN</th>
                                <th className="px-4 py-3 text-right">Sale AFN</th>
                                <th className="px-4 py-3 text-left">By</th>
                            </tr>
                        </thead>
                        <tbody>
                            {histories.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="h-24 px-4 text-center text-gray-500">
                                        No price changes for this item.
                                    </td>
                                </tr>
                            ) : (
                                histories.data.map((row) => (
                                    <tr key={row.id} className="border-b">
                                        <td className="px-4 py-3 text-xs">{new Date(row.created_at).toLocaleString('en-US')}</td>
                                        <td className="px-4 py-3">{row.change_type_label || row.change_type}</td>
                                        <td className="px-4 py-3 text-right font-mono">
                                            {row.old_purchase_price_afn} → {row.new_purchase_price_afn}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono">
                                            {row.old_sale_price_afn} → {row.new_sale_price_afn}
                                        </td>
                                        <td className="px-4 py-3 text-xs">{row.changer?.name || 'System'}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
