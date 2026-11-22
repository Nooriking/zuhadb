import CurrencyNote, { type DashboardReporting } from '@/components/dashboard/currency-note';
import KpiCard from '@/components/dashboard/kpi-card';
import ThreeCurrencyAmountView from '@/components/dashboard/three-currency-amount';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import type { DashboardFxBoard, ThreeCurrencyAmount } from '@/lib/three-currency';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Boxes, Package, ShoppingCart, Store, Users, Wallet } from 'lucide-react';

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

interface ValuedBatch {
    id: number;
    batch_code: string;
    product: string;
    volume_ml: number | null;
    remaining: number;
    unit_cost_afn: number;
    value: number;
    value_ledger?: number;
    value_3c?: ThreeCurrencyAmount | null;
    investor_owned: boolean;
}

interface Props {
    kpis: {
        total_stock: number;
        low_stock: number;
        out_of_stock: number;
        inventory_value?: number;
        store_owned_value?: number;
        investor_owned_value?: number;
        month_purchase_cost?: number;
    };
    kpis3c?: {
        inventory_value?: ThreeCurrencyAmount | null;
        store_owned_value?: ThreeCurrencyAmount | null;
        investor_owned_value?: ThreeCurrencyAmount | null;
        month_purchase_cost?: ThreeCurrencyAmount | null;
    } | null;
    lowItems: LowItem[];
    movements: Movement[];
    valuedBatches?: ValuedBatch[];
    reporting?: DashboardReporting;
    fxBoard?: DashboardFxBoard | null;
}

const FALLBACK_REPORTING: DashboardReporting = {
    currency: { code: 'AFN', name: 'Afghan Afghani', symbol: '؋', decimal_places: 2 },
    ledger: { code: 'AFN', name: 'Afghan Afghani', symbol: '؋', decimal_places: 2 },
    conversion_available: true,
    converts_from_ledger: false,
};

export default function StockDashboard({
    kpis,
    kpis3c = null,
    lowItems,
    movements,
    valuedBatches = [],
    reporting = FALLBACK_REPORTING,
    fxBoard = null,
}: Props) {
    const money = (value: number) => formatMoney(value, reporting.currency);
    const showValuation =
        kpis.inventory_value !== undefined ||
        kpis.store_owned_value !== undefined ||
        kpis.investor_owned_value !== undefined ||
        kpis.month_purchase_cost !== undefined;

    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
            <Head title="Stock Dashboard" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Stock Dashboard</h1>
                        <CurrencyNote reporting={reporting} fxBoard={fxBoard} />
                    </div>
                    <Link href="/inventory" className="rounded-md border px-4 py-2 text-sm">
                        Inventory
                    </Link>
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    <KpiCard title="Total stock" value={kpis.total_stock} icon={Boxes} color="blue" />
                    <KpiCard title="Low stock" value={kpis.low_stock} icon={AlertTriangle} color="orange" />
                    <KpiCard title="Out of stock" value={kpis.out_of_stock} icon={Package} color="red" />
                </div>

                {showValuation && (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {kpis.inventory_value !== undefined && (
                            <KpiCard
                                title="Inventory value"
                                value={money(kpis.inventory_value)}
                                threeCurrency={kpis3c?.inventory_value}
                                icon={Wallet}
                                color="purple"
                            />
                        )}
                        {kpis.store_owned_value !== undefined && (
                            <KpiCard
                                title="Store-owned value"
                                value={money(kpis.store_owned_value)}
                                threeCurrency={kpis3c?.store_owned_value}
                                icon={Store}
                                color="blue"
                            />
                        )}
                        {kpis.investor_owned_value !== undefined && (
                            <KpiCard
                                title="Investor-owned value"
                                value={money(kpis.investor_owned_value)}
                                threeCurrency={kpis3c?.investor_owned_value}
                                icon={Users}
                                color="green"
                            />
                        )}
                        {kpis.month_purchase_cost !== undefined && (
                            <KpiCard
                                title="Month purchase cost"
                                value={money(kpis.month_purchase_cost)}
                                threeCurrency={kpis3c?.month_purchase_cost}
                                icon={ShoppingCart}
                                color="orange"
                            />
                        )}
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-lg border bg-white">
                        <div className="border-b px-4 py-3 font-semibold">Low stock items</div>
                        {lowItems.map((item) => (
                            <div key={item.id} className="flex justify-between border-b px-4 py-3 text-sm last:border-b-0">
                                <span>
                                    {item.product}
                                    {item.volume_ml ? ` ${item.volume_ml}ml` : ''}
                                </span>
                                <span className="font-mono" title="Current qty / low-stock threshold">
                                    {item.quantity} / ≤{item.min_stock}
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

                {valuedBatches.length > 0 && (
                    <div className="rounded-lg border bg-white">
                        <div className="border-b px-4 py-3 font-semibold">Highest-value batches (at cost)</div>
                        {valuedBatches.map((batch) => (
                            <div key={batch.id} className="flex items-center justify-between border-b px-4 py-3 text-sm last:border-b-0">
                                <div>
                                    <div className="font-medium">
                                        {batch.product}
                                        {batch.volume_ml ? ` ${batch.volume_ml}ml` : ''}
                                    </div>
                                    <div className="text-muted-foreground font-mono text-xs">
                                        {batch.batch_code} · {batch.remaining} left
                                        {batch.investor_owned ? ' · investor' : ' · store'}
                                    </div>
                                </div>
                                <div className="text-right">
                                    {batch.value_3c ? (
                                        <ThreeCurrencyAmountView amounts={batch.value_3c} layout="compact" />
                                    ) : (
                                        <span className="font-mono">{money(batch.value)}</span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
