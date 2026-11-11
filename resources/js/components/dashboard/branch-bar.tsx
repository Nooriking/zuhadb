// resources/js/components/dashboard/branch-bar.tsx

import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import LazyChart from './lazy-chart';

interface BranchStat {
    id: number;
    name: string;
    code: string;
    sales: number;
    profit: number;
    orders: number;
}

interface Props {
    data: BranchStat[];
    currencyCode?: string;
}

const formatPrice = (v: number) => new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 }).format(v);

export default function BranchBar({ data, currencyCode }: Props) {
    if (!data?.length) {
        return <div className="text-muted-foreground flex h-80 items-center justify-center text-sm">No branch sales in this period.</div>;
    }

    const suffix = currencyCode ? ` ${currencyCode}` : '';

    return (
        <LazyChart height="h-80">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#3d3420" />
                    <XAxis dataKey="name" tick={{ fontSize: 11, fill: '#c4a35a' }} axisLine={false} tickLine={false} />
                    <YAxis tick={{ fontSize: 11, fill: '#c4a35a' }} axisLine={false} tickLine={false} tickFormatter={formatPrice} />
                    <Tooltip
                        contentStyle={{
                            backgroundColor: 'var(--popover)',
                            border: '1px solid var(--border)',
                            borderRadius: '8px',
                            fontSize: '12px',
                            color: 'var(--popover-foreground)',
                        }}
                        formatter={(value, name) => {
                            const amount = Array.isArray(value) ? Number(value[0]) : Number(value);
                            return [
                                new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Number.isFinite(amount) ? amount : 0) +
                                    suffix,
                                name === 'sales' ? 'Sales' : 'Store profit',
                            ];
                        }}
                    />
                    <Legend wrapperStyle={{ fontSize: '12px', color: 'var(--foreground)' }} iconType="circle" />
                    <Bar dataKey="sales" fill="#d4af37" radius={[6, 6, 0, 0]} maxBarSize={50} />
                    <Bar dataKey="profit" fill="#0284c7" radius={[6, 6, 0, 0]} maxBarSize={50} />
                </BarChart>
            </ResponsiveContainer>
        </LazyChart>
    );
}
