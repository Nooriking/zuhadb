import CurrencyNote, { type DashboardReporting } from '@/components/dashboard/currency-note';
import KpiCard from '@/components/dashboard/kpi-card';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { Head, Link } from '@inertiajs/react';
import { Banknote, Receipt, TrendingUp, Users, Wallet } from 'lucide-react';

interface ExpenseRow {
    id: number;
    title: string | null;
    amount_afn: number | string;
    amount_ledger?: number;
    ledger_currency_code?: string | null;
    expense_date: string | null;
}

interface Props {
    kpis: {
        month_sales: number;
        month_collected: number;
        month_expenses: number;
        customer_due: number;
        pending_investor_profit: number;
    };
    recentExpenses: ExpenseRow[];
    reporting: DashboardReporting;
}

const FALLBACK_REPORTING: DashboardReporting = {
    currency: { code: 'AFN', name: 'Afghan Afghani', symbol: '؋', decimal_places: 2 },
    ledger: { code: 'AFN', name: 'Afghan Afghani', symbol: '؋', decimal_places: 2 },
    conversion_available: true,
    converts_from_ledger: false,
};

export default function AccountantDashboard({ kpis, recentExpenses, reporting = FALLBACK_REPORTING }: Props) {
    const money = (value: number | string) => formatMoney(value, reporting.currency);

    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
            <Head title="Accountant Dashboard" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold">Accountant Dashboard</h1>
                    <CurrencyNote reporting={reporting} />
                </div>
                <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-5">
                    <KpiCard title="Month sales" value={money(kpis.month_sales)} icon={TrendingUp} color="blue" />
                    <KpiCard title="Collected" value={money(kpis.month_collected)} icon={Banknote} color="green" />
                    <KpiCard title="Expenses" value={money(kpis.month_expenses)} icon={Receipt} color="red" />
                    <KpiCard title="Customer dues" value={money(kpis.customer_due)} icon={Users} color="orange" />
                    <KpiCard title="Pending investor profit" value={money(kpis.pending_investor_profit)} icon={Wallet} color="purple" />
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
                                    <div>{money(expense.amount_afn)}</div>
                                    {expense.amount_ledger !== undefined &&
                                        reporting.converts_from_ledger &&
                                        reporting.conversion_available && (
                                            <div className="text-muted-foreground text-xs">
                                                {formatMoney(expense.amount_ledger, reporting.ledger)}
                                            </div>
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
