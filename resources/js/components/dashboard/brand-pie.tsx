// resources/js/components/dashboard/brand-pie.tsx

import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

interface BrandData {
    name: string;
    value: number;
}

interface Props {
    data: BrandData[];
}

const COLORS = ['#d4af37', '#0284c7', '#0f766e', '#8a6a2f', '#c4a35a', '#0369a1', '#6b5424', '#a6843a'];

const formatPrice = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(value);

export default function BrandPie({ data }: Props) {
    const safeData = data ?? [];
    const total = safeData.reduce((sum, item) => sum + item.value, 0);

    if (safeData.length === 0) {
        return <div className="text-muted-foreground flex h-[280px] items-center justify-center text-sm">No brand sales data.</div>;
    }

    return (
        <ResponsiveContainer width="100%" height={280}>
            <PieChart>
                <Pie data={safeData} cx="50%" cy="50%" labelLine={false} outerRadius={90} fill="#8884d8" dataKey="value" nameKey="name">
                    {safeData.map((_, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                </Pie>
                <Tooltip
                    formatter={(value: number, name: string) => {
                        const percent = total > 0 ? ((value / total) * 100).toFixed(1) : '0';
                        return [`${formatPrice(value)} AFN (${percent}%)`, name];
                    }}
                />
                <Legend verticalAlign="bottom" height={36} iconType="circle" wrapperStyle={{ fontSize: '12px' }} />
            </PieChart>
        </ResponsiveContainer>
    );
}
