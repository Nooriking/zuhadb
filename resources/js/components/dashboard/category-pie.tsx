// resources/js/components/dashboard/category-pie.tsx

import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import LazyChart from './lazy-chart';

interface DataPoint {
    name: string;
    value: number;
}

interface Props {
    data: DataPoint[];
}

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1'];

export default function CategoryPie({ data }: Props) {
    return (
        <LazyChart height="h-80">
            <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                    <Pie data={data} dataKey="value" nameKey="name" cx="50%" cy="50%" innerRadius={60} outerRadius={100} paddingAngle={2}>
                        {data.map((_, i) => (
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
                        formatter={(value: number) => [new Intl.NumberFormat('en-US').format(value) + ' AFN', 'Sales']}
                    />
                    <Legend wrapperStyle={{ fontSize: '12px', color: 'var(--foreground)' }} iconType="circle" verticalAlign="bottom" />
                </PieChart>
            </ResponsiveContainer>
        </LazyChart>
    );
}
