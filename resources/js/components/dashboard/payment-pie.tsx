// resources/js/components/dashboard/payment-pie.tsx

import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import LazyChart from './lazy-chart';

interface DataPoint {
    name: string;
    value: number;
}

interface Props {
    data: DataPoint[];
    currencyCode?: string;
}

const COLORS = ['#d4af37', '#0284c7', '#8a6a2f', '#0f766e', '#c4a35a'];

export default function PaymentPie({ data, currencyCode }: Props) {
    const safeData = data ?? [];
    const suffix = currencyCode ? ` ${currencyCode}` : '';

    if (safeData.length === 0) {
        return <div className="text-muted-foreground flex h-72 items-center justify-center text-sm">No payment data in this period.</div>;
    }

    return (
        <LazyChart height="h-72">
            <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                    <Pie
                        data={safeData}
                        dataKey="value"
                        nameKey="name"
                        cx="50%"
                        cy="50%"
                        outerRadius={90}
                        label={(entry) => entry.name}
                        labelLine={false}
                    >
                        {safeData.map((_, i) => (
                            <Cell key={i} fill={COLORS[i % COLORS.length]} />
                        ))}
                    </Pie>
                    <Tooltip
                        contentStyle={{
                            backgroundColor: 'var(--popover)',
                            border: '1px solid var(--border)',
                            borderRadius: '8px',
                            fontSize: '12px',
                            color: 'var(--popover-foreground)',
                        }}
                        formatter={(value: number) => [new Intl.NumberFormat('en-US').format(value) + suffix, 'Amount']}
                    />
                    <Legend wrapperStyle={{ fontSize: '12px', color: 'var(--foreground)' }} iconType="circle" />
                </PieChart>
            </ResponsiveContainer>
        </LazyChart>
    );
}
