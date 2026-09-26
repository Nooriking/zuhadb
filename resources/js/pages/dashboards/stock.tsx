import KpiCard from '@/components/dashboard/kpi-card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Boxes, Package } from 'lucide-react';

interface LowItem {
    id: number;
    product: string;
    volume_ml: number | null;
    quantity: number;
    min_stock: number;
}

interface Movement {
    id: number;
    type: string;
    product: string;
    quantity: number;
    before_quantity: number;
    after_quantity: number;
}

interface Props {
    kpis: { total_stock: number; low_stock: number; out_of_stock: number };
    lowItems: LowItem[];
    movements: Movement[];
}

export default function StockDashboard({ kpis, lowItems, movements }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
            <Head title="Stock Dashboard" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Stock Dashboard</h1>
                    <Link href="/inventory" className="rounded-md border px-4 py-2 text-sm">
                        Inventory
                    </Link>
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    <KpiCard title="Total stock" value={kpis.total_stock} icon={Boxes} color="blue" />
                    <KpiCard title="Low stock" value={kpis.low_stock} icon={AlertTriangle} color="orange" />
                    <KpiCard title="Out of stock" value={kpis.out_of_stock} icon={Package} color="red" />
                </div>
                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-lg border bg-white">
                        <div className="border-b px-4 py-3 font-semibold">Low stock items</div>
                        {lowItems.map((item) => (
                            <div key={item.id} className="flex justify-between border-b px-4 py-3 text-sm last:border-b-0">
                                <span>
                                    {item.product}
                                    {item.volume_ml ? ` ${item.volume_ml}ml` : ''}
                                </span>
                                <span className="font-mono">
                                    {item.quantity} / {item.min_stock}
                                </span>
                            </div>
                        ))}
                    </div>
                    <div className="rounded-lg border bg-white">
                        <div className="border-b px-4 py-3 font-semibold">Latest stock movements</div>
                        {movements.map((row) => (
                            <div key={row.id} className="flex justify-between border-b px-4 py-3 text-sm last:border-b-0">
                                <span>
                                    {row.product} — {row.type}
                                </span>
                                <span className="font-mono">
                                    {row.before_quantity} → {row.after_quantity}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
