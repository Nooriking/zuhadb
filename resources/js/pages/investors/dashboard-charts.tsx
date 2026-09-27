import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

const COLORS = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];

const fmt = (n: number): string => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(n || 0);

const fmtShort = (n: number): string => {
    if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`;
    if (n >= 1_000) return `${(n / 1_000).toFixed(1)}K`;
    return String(Math.round(n || 0));
};

type Point = { date: string; capital: number; allocated: number; profit: number };
type NameValue = { name: string; value: number };

function SectionCard({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">{title}</h3>
            {children}
        </div>
    );
}

function EmptyState({ message }: { message: string }) {
    return <p className="text-muted-foreground py-10 text-center text-sm">{message}</p>;
}

export default function InvestorDashboardCharts({
    capitalTrend,
    profitByInvestor,
    withdrawalBreakdown,
    investmentStatus,
}: {
    capitalTrend: Point[];
    profitByInvestor: NameValue[];
    withdrawalBreakdown: NameValue[];
    investmentStatus: NameValue[];
}) {
    return (
        <>
            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <SectionCard title="Capital Trend">
                        {capitalTrend.length === 0 ? (
                            <EmptyState message="No capital data for this range" />
                        ) : (
                            <ResponsiveContainer width="100%" height={300}>
                                <AreaChart data={capitalTrend}>
                                    <defs>
                                        <linearGradient id="colorCapital" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor="#6366f1" stopOpacity={0.3} />
                                            <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
                                        </linearGradient>
                                        <linearGradient id="colorAllocated" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor="#10b981" stopOpacity={0.3} />
                                            <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="date" tick={{ fontSize: 12 }} />
                                    <YAxis tickFormatter={fmtShort} tick={{ fontSize: 12 }} />
                                    <Tooltip
                                        formatter={(v) => `${fmt(Number(v))} USD`}
                                        contentStyle={{ borderRadius: 12, border: '1px solid #e5e7eb' }}
                                    />
                                    <Legend />
                                    <Area
                                        type="monotone"
                                        dataKey="capital"
                                        name="Capital"
                                        stroke="#6366f1"
                                        fillOpacity={1}
                                        fill="url(#colorCapital)"
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="allocated"
                                        name="Allocated"
                                        stroke="#10b981"
                                        fillOpacity={1}
                                        fill="url(#colorAllocated)"
                                    />
                                </AreaChart>
                            </ResponsiveContainer>
                        )}
                    </SectionCard>
                </div>

                <SectionCard title="Profit by Investor">
                    {profitByInvestor.length === 0 ? (
                        <EmptyState message="No profit data for this range" />
                    ) : (
                        <ResponsiveContainer width="100%" height={300}>
                            <PieChart>
                                <Pie
                                    data={profitByInvestor}
                                    dataKey="value"
                                    nameKey="name"
                                    cx="50%"
                                    cy="50%"
                                    innerRadius={60}
                                    outerRadius={100}
                                    paddingAngle={2}
                                >
                                    {profitByInvestor.map((_, i) => (
                                        <Cell key={i} fill={COLORS[i % COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip formatter={(v) => `${fmt(Number(v))} USD`} />
                            </PieChart>
                        </ResponsiveContainer>
                    )}
                </SectionCard>
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <SectionCard title="Withdrawal Breakdown">
                    <ResponsiveContainer width="100%" height={260}>
                        <PieChart>
                            <Pie
                                data={withdrawalBreakdown}
                                dataKey="value"
                                nameKey="name"
                                cx="50%"
                                cy="50%"
                                outerRadius={100}
                                label={({ name, value }) => `${name}: ${fmtShort(Number(value))}`}
                            >
                                {withdrawalBreakdown.map((_, i) => (
                                    <Cell key={i} fill={COLORS[i % COLORS.length]} />
                                ))}
                            </Pie>
                            <Tooltip formatter={(v) => `${fmt(Number(v))} USD`} />
                        </PieChart>
                    </ResponsiveContainer>
                </SectionCard>

                <SectionCard title="Investment Status">
                    <ResponsiveContainer width="100%" height={260}>
                        <BarChart data={investmentStatus}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                            <XAxis dataKey="name" tick={{ fontSize: 12 }} />
                            <YAxis tickFormatter={fmtShort} tick={{ fontSize: 12 }} />
                            <Tooltip formatter={(v) => `${fmt(Number(v))} USD`} />
                            <Bar dataKey="value" name="Amount" radius={[8, 8, 0, 0]}>
                                {investmentStatus.map((_, i) => (
                                    <Cell key={i} fill={COLORS[i % COLORS.length]} />
                                ))}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </SectionCard>
            </div>
        </>
    );
}
