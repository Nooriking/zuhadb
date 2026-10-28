import { Building2, CreditCard, DollarSign, Percent, Receipt, RotateCcw, ShoppingBag, TrendingUp, UserCheck, Users } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { type ProfitSummary, type SalesSection } from './profit-types';
import { EmptyState, EntityTable, KpiCard, MatrixTable, Section } from './report-blocks';
import { CHART_COLORS, PIE_COLORS, type ReportFilters, fmt, fmtPct, fmtShort, tooltipStyle } from './report-utils';

interface Props {
    summary: ProfitSummary;
    sales: SalesSection;
    filters: ReportFilters;
}

export function ProfitSalesTab({ summary: s, sales, filters }: Props) {
    const sellerChart = sales.by_seller.slice(0, 10).map((r) => ({ name: r.name, sales: r.sales, profit: r.total_profit }));
    const paymentPie = sales.by_payment.map((r) => ({ name: r.name, value: r.sales }));
    const matrixUnit = filters.group_by === 'yearly' ? 'year' : 'month';

    return (
        <div className="flex flex-col gap-6">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Net Sales" value={fmt(s.total_sales)} sub={`${s.total_orders} completed orders`} icon={DollarSign} />
                <KpiCard label="Gross Profit" value={fmt(s.total_profit)} sub={`Margin ${fmtPct(s.margin)}`} icon={TrendingUp} tone="green" />
                <KpiCard label="Returns" value={fmt(s.total_returns)} sub={`${s.returns_count} returns`} icon={RotateCcw} tone="red" />
                <KpiCard label="Discounts" value={fmt(s.total_discount)} sub="Given to customers" icon={Percent} tone="amber" />
                <KpiCard label="Average Order" value={fmt(s.avg_order)} sub="AFN per order" icon={Receipt} tone="violet" />
                <KpiCard label="Units Sold" value={fmt(s.total_quantity)} sub="Pieces" icon={ShoppingBag} tone="blue" />
                <KpiCard label="Customers" value={s.customers} sub="Registered buyers" icon={Users} />
                <KpiCard label="Sellers" value={sales.by_seller.length} sub="Users who sold" icon={UserCheck} />
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <Section title="Top Sellers" description="Sales and profit recorded by each user" icon={UserCheck}>
                        {sellerChart.length === 0 ? (
                            <EmptyState />
                        ) : (
                            <ResponsiveContainer width="100%" height={Math.max(200, sellerChart.length * 42)}>
                                <BarChart data={sellerChart} layout="vertical" margin={{ top: 5, right: 20, left: 10, bottom: 5 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis type="number" tickFormatter={fmtShort} tick={{ fontSize: 11 }} />
                                    <YAxis type="category" dataKey="name" width={120} tick={{ fontSize: 11 }} />
                                    <Tooltip formatter={(v) => `${fmt(Number(v))} AFN`} contentStyle={tooltipStyle} />
                                    <Bar dataKey="sales" name="Sales" fill={CHART_COLORS.sales} radius={[0, 4, 4, 0]} />
                                    <Bar dataKey="profit" name="Profit" fill={CHART_COLORS.profit} radius={[0, 4, 4, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </Section>
                </div>

                <Section title="Payment Methods" description="Share of sales by payment type" icon={CreditCard}>
                    {paymentPie.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <>
                            <ResponsiveContainer width="100%" height={180}>
                                <PieChart>
                                    <Pie data={paymentPie} dataKey="value" nameKey="name" cx="50%" cy="50%" innerRadius={45} outerRadius={80}>
                                        {paymentPie.map((_, i) => (
                                            <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip formatter={(v) => `${fmt(Number(v))} AFN`} contentStyle={tooltipStyle} />
                                </PieChart>
                            </ResponsiveContainer>
                            <div className="mt-2 space-y-1.5">
                                {sales.by_payment.map((p, i) => (
                                    <div key={p.name} className="flex items-center justify-between text-sm">
                                        <span className="flex items-center gap-2">
                                            <span className="h-2.5 w-2.5 rounded-full" style={{ background: PIE_COLORS[i % PIE_COLORS.length] }} />
                                            {p.name} <span className="text-muted-foreground text-xs">({p.orders})</span>
                                        </span>
                                        <span className="font-mono text-xs font-semibold">{fmt(p.sales)}</span>
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </Section>
            </div>

            <Section title="Sales by Branch" icon={Building2}>
                <EntityTable rows={sales.by_branch} nameLabel="Branch" />
            </Section>

            <Section title="Sales by Seller" description="Each user who recorded sales in this period" icon={UserCheck}>
                <EntityTable rows={sales.by_seller} nameLabel="Seller" subKey="role" />
            </Section>

            {sales.seller_matrix.periods.length > 1 && (
                <Section
                    title={`Seller Sales per ${matrixUnit === 'year' ? 'Year' : 'Month'}`}
                    description="Compare every seller side by side (AFN)"
                    icon={UserCheck}
                >
                    <MatrixTable matrix={sales.seller_matrix} nameLabel="Seller" />
                </Section>
            )}

            <Section title="Payment Method Details" icon={CreditCard}>
                <EntityTable rows={sales.by_payment} nameLabel="Method" showQty={false} />
            </Section>

            <Section title="Top Customers" description="Up to 30 customers ranked by sales" icon={Users}>
                <EntityTable rows={sales.by_customer} nameLabel="Customer" showQty={false} subKey="phone" />
            </Section>
        </div>
    );
}
