// resources/js/components/dashboard/kpi-card.tsx

import { type LucideIcon } from 'lucide-react';

interface Props {
    title: string;
    value: string | number;
    subtitle?: string;
    icon: LucideIcon;
    color?: 'blue' | 'green' | 'red' | 'orange' | 'purple' | 'gray';
    trend?: {
        value: number;
        isPositive: boolean;
    };
}

const COLORS = {
    blue: { bg: 'bg-blue-100 dark:bg-blue-950', text: 'text-blue-600 dark:text-blue-400', ring: 'ring-blue-200 dark:ring-blue-900' },
    green: { bg: 'bg-green-100 dark:bg-green-950', text: 'text-green-600 dark:text-green-400', ring: 'ring-green-200 dark:ring-green-900' },
    red: { bg: 'bg-red-100 dark:bg-red-950', text: 'text-red-600 dark:text-red-400', ring: 'ring-red-200 dark:ring-red-900' },
    orange: { bg: 'bg-orange-100 dark:bg-orange-950', text: 'text-orange-600 dark:text-orange-400', ring: 'ring-orange-200 dark:ring-orange-900' },
    purple: { bg: 'bg-purple-100 dark:bg-purple-950', text: 'text-purple-600 dark:text-purple-400', ring: 'ring-purple-200 dark:ring-purple-900' },
    gray: { bg: 'bg-gray-100 dark:bg-gray-800', text: 'text-gray-600 dark:text-gray-400', ring: 'ring-gray-200 dark:ring-gray-700' },
};

export default function KpiCard({ title, value, subtitle, icon: Icon, color = 'blue', trend }: Props) {
    const c = COLORS[color];

    return (
        <div className="bg-card rounded-lg border p-5 shadow-sm transition-shadow hover:shadow-md">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-muted-foreground text-xs font-medium uppercase">{title}</p>
                    <p className="text-foreground mt-2 truncate text-2xl font-bold">{value}</p>
                    {subtitle && <p className="text-muted-foreground mt-1 text-xs">{subtitle}</p>}

                    {trend && (
                        <div className="mt-2 flex items-center gap-1">
                            <span
                                className={`text-xs font-semibold ${
                                    trend.isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                                }`}
                            >
                                {trend.isPositive ? '▲' : '▼'} {Math.abs(trend.value)}%
                            </span>
                            <span className="text-muted-foreground text-xs">vs last period</span>
                        </div>
                    )}
                </div>
                <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-full ${c.bg} ${c.ring} ring-2`}>
                    <Icon className={`h-5 w-5 ${c.text}`} />
                </div>
            </div>
        </div>
    );
}
