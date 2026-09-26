// resources/js/components/dashboard/low-stock.tsx

import { Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

interface Item {
    id: number;
    branch: string | null;
    product: string | null;
    brand: string | null;
    volume_ml: number | null;
    quantity: number;
    min_stock: number;
}

interface Props {
    items: Item[];
}

export default function LowStock({ items }: Props) {
    if (items.length === 0) {
        return (
            <div className="bg-card flex h-full items-center justify-center rounded-lg border p-6 text-sm text-muted-foreground">
                Stock is above every variant minimum.
            </div>
        );
    }

    return (
        <div className="rounded-lg border border-yellow-200 bg-yellow-50 shadow-sm dark:border-yellow-900 dark:bg-yellow-950/40">
            <div className="flex items-center justify-between border-b border-yellow-200 px-6 py-4 dark:border-yellow-900">
                <div className="flex items-center gap-2">
                    <AlertTriangle className="h-5 w-5 text-yellow-600 dark:text-yellow-500" />
                    <h3 className="font-semibold text-yellow-900 dark:text-yellow-200">Low Stock Alert</h3>
                </div>
                <Link href="/inventory/low-stock" className="text-xs font-semibold text-yellow-700 hover:underline dark:text-yellow-400">
                    View all →
                </Link>
            </div>
            <div className="divide-y divide-yellow-200 dark:divide-yellow-900">
                {items.map((item) => (
                    <Link
                        key={item.id}
                        href={`/inventory/${item.id}`}
                        className="flex items-center justify-between px-6 py-3 transition-colors hover:bg-yellow-100 dark:hover:bg-yellow-950"
                    >
                        <div>
                            <div className="text-sm font-medium">
                                {item.brand && <span className="text-muted-foreground">{item.brand} — </span>}
                                {item.product}
                            </div>
                            <div className="text-muted-foreground text-xs">{item.branch}</div>
                        </div>
                        <div className="text-right">
                            <div className="font-mono text-sm font-bold text-yellow-700 dark:text-yellow-400">{item.quantity}</div>
                            <div className="text-muted-foreground text-xs">min: {item.min_stock}</div>
                        </div>
                    </Link>
                ))}
            </div>
        </div>
    );
}
