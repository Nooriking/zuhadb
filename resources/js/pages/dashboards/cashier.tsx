import CurrencyNote, { type DashboardReporting } from '@/components/dashboard/currency-note';
import KpiCard from '@/components/dashboard/kpi-card';
import ThreeCurrencyAmountView from '@/components/dashboard/three-currency-amount';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import type { DashboardFxBoard, ThreeCurrencyAmount } from '@/lib/three-currency';
import { Head, Link } from '@inertiajs/react';
import { Banknote, CreditCard, ShoppingBag, Users, Wallet } from 'lucide-react';

interface SaleRow {
    id: number;
    invoice_number: string;
    customer: string;
    total_afn: number;
    total_ledger?: number;
    total_3c?: ThreeCurrencyAmount | null;
    remaining_due_afn: number;
    remaining_due_ledger?: number;
    remaining_due_3c?: ThreeCurrencyAmount | null;
    ledger_currency_code?: string | null;
    sale_date: string | null;
}

interface PaymentCurrencyTotal {
    code: string;
    symbol?: string | null;
    total: number;
}

interface Props {
    kpis: {
        today_sales: number;
        today_orders: number;
        today_collected: number;
        cash_collected?: number;
        card_collected?: number;
        customer_due: number;
    };
    kpis3c?: {
        today_sales?: ThreeCurrencyAmount | null;
        today_collected?: ThreeCurrencyAmount | null;
        cash_collected?: ThreeCurrencyAmount | null;
        card_collected?: ThreeCurrencyAmount | null;
        customer_due?: ThreeCurrencyAmount | null;
    } | null;
    paymentCurrencyTotals?: PaymentCurrencyTotal[];
    recentSales: SaleRow[];
    reporting: DashboardReporting;
    fxBoard?: DashboardFxBoard | null;
}

const FALLBACK_REPORTING: DashboardReporting = {
    currency: { code: 'AFN', name: 'Afghan Afghani', symbol: '؋', decimal_places: 2 },
    ledger: { code: 'AFN', name: 'Afghan Afghani', symbol: '؋', decimal_places: 2 },
    conversion_available: true,
    converts_from_ledger: false,
};

export default function CashierDashboard({
    kpis,
    kpis3c = null,
    paymentCurrencyTotals = [],
    recentSales = [],
    reporting = FALLBACK_REPORTING,
    fxBoard = null,
}: Props) {
    const money = (value: number) => formatMoney(value, reporting.currency);
    const showCashCard = kpis.cash_collected !== undefined;
    const showCardCard = kpis.card_collected !== undefined;
    const showPaymentBuckets =
        paymentCurrencyTotals.length > 1 || (!reporting.conversion_available && paymentCurrencyTotals.length > 0);

    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
            <Head title="Cashier Dashboard" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Cashier Dashboard</h1>
                        <CurrencyNote reporting={reporting} originalCurrencyTotals={paymentCurrencyTotals} fxBoard={fxBoard} />
                    </div>
                    <Link href="/sales/create" className="rounded-md bg-gray-900 px-4 py-2 text-sm text-white">
                        New Sale
                    </Link>
                </div>
                <div className={`grid gap-4 md:grid-cols-2 ${showCashCard || showCardCard ? 'xl:grid-cols-3' : 'xl:grid-cols-4'}`}>
                    <KpiCard
                        title="Today's sales"
                        value={money(kpis.today_sales)}
                        threeCurrency={kpis3c?.today_sales}
                        icon={ShoppingBag}
                        color="blue"
                    />
                    <KpiCard title="Invoices" value={kpis.today_orders} icon={Wallet} color="purple" />
                    <KpiCard
                        title="Collected today"
                        value={money(kpis.today_collected)}
                        threeCurrency={kpis3c?.today_collected}
                        icon={Banknote}
                        color="green"
                    />
                    {showCashCard && (
                        <KpiCard
                            title="Cash collected"
                            value={money(kpis.cash_collected ?? 0)}
                            threeCurrency={kpis3c?.cash_collected}
                            icon={Banknote}
                            color="green"
                        />
                    )}
                    {showCardCard && (
                        <KpiCard
                            title="Card collected"
                            value={money(kpis.card_collected ?? 0)}
                            threeCurrency={kpis3c?.card_collected}
                            icon={CreditCard}
                            color="blue"
                        />
                    )}
                    <KpiCard
                        title="Customer dues"
                        value={money(kpis.customer_due)}
                        threeCurrency={kpis3c?.customer_due}
                        icon={Users}
                        color="orange"
                    />
                </div>

                {showPaymentBuckets && (
                    <div className="rounded-lg border bg-white p-4">
                        <h3 className="text-sm font-semibold">Collected by original payment currency</h3>
                        <div className="mt-3 flex flex-wrap gap-3">
                            {paymentCurrencyTotals.map((row) => (
                                <div key={row.code} className="rounded-md border px-3 py-2 text-sm">
                                    <span className="font-mono font-semibold">
                                        {row.total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </span>{' '}
                                    <span className="text-muted-foreground">{row.symbol || row.code}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div className="rounded-lg border bg-white">
                    <div className="border-b px-4 py-3 font-semibold">Recent sales</div>
                    {recentSales.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-6 text-sm">No recent sales.</p>
                    ) : (
                        recentSales.map((sale) => (
                            <Link
                                key={sale.id}
                                href={`/sales/${sale.id}`}
                                className="flex items-center justify-between border-b px-4 py-3 text-sm last:border-b-0"
                            >
                                <span>
                                    {sale.invoice_number} — {sale.customer}
                                </span>
                                <span className="text-right font-mono">
                                    {sale.total_3c ? (
                                        <ThreeCurrencyAmountView amounts={sale.total_3c} layout="compact" />
                                    ) : (
                                        <>
                                            <div>{money(sale.total_afn)}</div>
                                            {sale.total_ledger !== undefined &&
                                                reporting.converts_from_ledger &&
                                                reporting.conversion_available && (
                                                    <div className="text-muted-foreground text-xs">
                                                        {formatMoney(sale.total_ledger, reporting.ledger)}
                                                    </div>
                                                )}
                                        </>
                                    )}
                                </span>
                            </Link>
                        ))
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
