import CurrencyNote, { type DashboardReporting } from '@/components/dashboard/currency-note';
import KpiCard from '@/components/dashboard/kpi-card';
import ThreeCurrencyAmountView from '@/components/dashboard/three-currency-amount';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import type { DashboardFxBoard, ThreeCurrencyAmount } from '@/lib/three-currency';
import { Head, Link } from '@inertiajs/react';
import { Banknote, Boxes, Receipt, TrendingUp, Users, Wallet } from 'lucide-react';

interface ExpenseRow {
    id: number;
    title: string | null;
    amount_afn: number | string;
    amount_ledger?: number;
    amount_3c?: ThreeCurrencyAmount | null;
    ledger_currency_code?: string | null;
    expense_date: string | null;
}

interface Props {
    kpis: {
        month_sales: number;
        month_collected: number;
        month_expenses: number;
        month_gross_profit?: number;
        month_store_profit?: number;
        month_investor_profit?: number;
        month_net?: number;
        customer_due: number;
        pending_investor_profit: number;
        paid_investor_profit?: number;
        inventory_value?: number;
    };
    kpis3c?: {
        month_sales?: ThreeCurrencyAmount | null;
        month_collected?: ThreeCurrencyAmount | null;
        month_expenses?: ThreeCurrencyAmount | null;
        month_gross_profit?: ThreeCurrencyAmount | null;
        month_store_profit?: ThreeCurrencyAmount | null;
        month_investor_profit?: ThreeCurrencyAmount | null;
        month_net?: ThreeCurrencyAmount | null;
        customer_due?: ThreeCurrencyAmount | null;
        pending_investor_profit?: ThreeCurrencyAmount | null;
        paid_investor_profit?: ThreeCurrencyAmount | null;
        inventory_value?: ThreeCurrencyAmount | null;
    } | null;
    recentExpenses: ExpenseRow[];
    reporting: DashboardReporting;
    fxBoard?: DashboardFxBoard | null;
}

const FALLBACK_REPORTING: DashboardReporting = {
    currency: { code: 'AFN', name: 'Afghan Afghani', symbol: '؋', decimal_places: 2 },
    ledger: { code: 'AFN', name: 'Afghan Afghani', symbol: '؋', decimal_places: 2 },
    conversion_available: true,
    converts_from_ledger: false,
};

export default function AccountantDashboard({
    kpis,
    kpis3c = null,
    recentExpenses,
    reporting = FALLBACK_REPORTING,
    fxBoard = null,
}: Props) {
    const money = (value: number | string) => formatMoney(value, reporting.currency);

    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
            <Head title="Accountant Dashboard" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold">Accountant Dashboard</h1>
                    <CurrencyNote reporting={reporting} fxBoard={fxBoard} />
                </div>
                <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
                    <KpiCard
                        title="Month sales"
                        value={money(kpis.month_sales)}
                        threeCurrency={kpis3c?.month_sales}
                        icon={TrendingUp}
                        color="blue"
                    />
                    <KpiCard
                        title="Collected"
                        value={money(kpis.month_collected)}
                        threeCurrency={kpis3c?.month_collected}
                        icon={Banknote}
                        color="green"
                    />
                    <KpiCard
                        title="Expenses"
                        value={money(kpis.month_expenses)}
                        threeCurrency={kpis3c?.month_expenses}
                        icon={Receipt}
                        color="red"
                    />
                    {kpis.month_gross_profit !== undefined && (
                        <KpiCard
                            title="Gross profit"
                            value={money(kpis.month_gross_profit)}
                            threeCurrency={kpis3c?.month_gross_profit}
                            icon={TrendingUp}
                            color="green"
                        />
                    )}
                    {kpis.month_store_profit !== undefined && (
                        <KpiCard
                            title="Store profit"
                            value={money(kpis.month_store_profit)}
                            threeCurrency={kpis3c?.month_store_profit}
                            icon={Wallet}
                            color="purple"
                        />
                    )}
                    {kpis.month_investor_profit !== undefined && (
                        <KpiCard
                            title="Investor profit"
                            value={money(kpis.month_investor_profit)}
                            threeCurrency={kpis3c?.month_investor_profit}
                            icon={Users}
                            color="orange"
                        />
                    )}
                    {kpis.month_net !== undefined && (
                        <KpiCard
                            title="Month net"
                            value={money(kpis.month_net)}
                            threeCurrency={kpis3c?.month_net}
                            icon={Wallet}
                            color={kpis.month_net >= 0 ? 'green' : 'red'}
                        />
                    )}
                    <KpiCard
                        title="Customer dues"
                        value={money(kpis.customer_due)}
                        threeCurrency={kpis3c?.customer_due}
                        icon={Users}
                        color="orange"
                    />
                    <KpiCard
                        title="Pending investor profit"
                        value={money(kpis.pending_investor_profit)}
                        threeCurrency={kpis3c?.pending_investor_profit}
                        icon={Wallet}
                        color="purple"
                    />
                    {kpis.paid_investor_profit !== undefined && (
                        <KpiCard
                            title="Paid investor profit"
                            value={money(kpis.paid_investor_profit)}
                            threeCurrency={kpis3c?.paid_investor_profit}
                            icon={Banknote}
                            color="green"
                        />
                    )}
                    {kpis.inventory_value !== undefined && (
                        <KpiCard
                            title="Inventory value"
                            value={money(kpis.inventory_value)}
                            threeCurrency={kpis3c?.inventory_value}
                            icon={Boxes}
                            color="gray"
                        />
                    )}
                </div>
                <div className="rounded-lg border bg-white">
                    <div className="border-b px-4 py-3 font-semibold">Recent expenses</div>
                    {recentExpenses.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-6 text-sm">No recent expenses.</p>
                    ) : (
                        recentExpenses.map((expense) => (
                            <Link
                                key={expense.id}
                                href={`/expenses/${expense.id}`}
                                className="flex justify-between border-b px-4 py-3 text-sm last:border-b-0"
                            >
                                <span>{expense.title || 'Expense'}</span>
                                <span className="text-right font-mono">
                                    {expense.amount_3c ? (
                                        <ThreeCurrencyAmountView amounts={expense.amount_3c} layout="compact" />
                                    ) : (
                                        <>
                                            <div>{money(expense.amount_afn)}</div>
                                            {expense.amount_ledger !== undefined &&
                                                reporting.converts_from_ledger &&
                                                reporting.conversion_available && (
                                                    <div className="text-muted-foreground text-xs">
                                                        {formatMoney(expense.amount_ledger, reporting.ledger)}
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
