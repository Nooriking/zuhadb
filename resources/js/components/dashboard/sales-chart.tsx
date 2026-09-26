// resources/js/components/dashboard/sales-chart.tsx

import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import LazyChart from './lazy-chart';

interface DataPoint {
    date: string;
    sales: number;
    profit: number;
    count: number;
}

interface Props {
    data: DataPoint[];
}

const formatPrice = (v: number) => new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 }).format(v);

const money = (value: unknown) => {
    const amount = Array.isArray(value) ? Number(value[0]) : Number(value);
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Number.isFinite(amount) ? amount : 0);
};

export default function SalesChart({ data }: Props) {
    if (!data?.length) {
        return <div className="text-muted-foreground flex h-80 items-center justify-center text-sm">No sales in this period.</div>;
    }

    return (
        <LazyChart height="h-80">
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={data} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                    <defs>
                        <linearGradient id="salesGradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="#d4af37" stopOpacity={0.45} />
                            <stop offset="95%" stopColor="#d4af37" stopOpacity={0} />
                        </linearGradient>
                        <linearGradient id="profitGradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="#0284c7" stopOpacity={0.35} />
                            <stop offset="95%" stopColor="#0284c7" stopOpacity={0} />
                        </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke="#3d3420" />
                    <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#c4a35a' }} axisLine={false} tickLine={false} minTickGap={24} />
                    <YAxis tick={{ fontSize: 11, fill: '#c4a35a' }} axisLine={false} tickLine={false} tickFormatter={formatPrice} />
                    <Tooltip
                        contentStyle={{
                            backgroundColor: 'var(--popover)',
                            border: '1px solid var(--border)',
                            borderRadius: '8px',
                            fontSize: '12px',
                            color: 'var(--popover-foreground)',
                        }}
                        formatter={(value, name) => [money(value) + ' AFN', name === 'sales' ? 'Sales' : 'Store profit']}
                    />
                    <Area type="monotone" dataKey="sales" name="sales" stroke="#d4af37" strokeWidth={2} fill="url(#salesGradient)" />
                    <Area type="monotone" dataKey="profit" name="profit" stroke="#0284c7" strokeWidth={2.5} fill="url(#profitGradient)" />
                </AreaChart>
            </ResponsiveContainer>
        </LazyChart>
    );
}
