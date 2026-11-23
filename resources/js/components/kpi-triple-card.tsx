import { useExchange } from '@/hooks/useExchange';
import type { CurrencyView } from '@/components/currency-tabs';
import { type LucideIcon } from 'lucide-react';

type Color = 'blue' | 'green' | 'red' | 'orange' | 'purple' | 'gray';

interface Props {
    title: string;
    /** Ledger amount in AFN — converted via shared exchange board */
    afn: number | null | undefined;
    subtitle?: string;
    icon: LucideIcon;
    color?: Color;
    view: CurrencyView;
    trend?: {
        value: number;
        isPositive: boolean;
    };
}

const COLORS: Record<Color, { bg: string; text: string; ring: string }> = {
    blue: { bg: 'bg-blue-100 dark:bg-blue-950', text: 'text-blue-600 dark:text-blue-400', ring: 'ring-blue-200 dark:ring-blue-900' },
    green: { bg: 'bg-green-100 dark:bg-green-950', text: 'text-green-600 dark:text-green-400', ring: 'ring-green-200 dark:ring-green-900' },
    red: { bg: 'bg-red-100 dark:bg-red-950', text: 'text-red-600 dark:text-red-400', ring: 'ring-red-200 dark:ring-red-900' },
    orange: { bg: 'bg-orange-100 dark:bg-orange-950', text: 'text-orange-600 dark:text-orange-400', ring: 'ring-orange-200 dark:ring-orange-900' },
    purple: { bg: 'bg-purple-100 dark:bg-purple-950', text: 'text-purple-600 dark:text-purple-400', ring: 'ring-purple-200 dark:ring-purple-900' },
    gray: { bg: 'bg-gray-100 dark:bg-gray-800', text: 'text-gray-600 dark:text-gray-400', ring: 'ring-gray-200 dark:ring-gray-700' },
};

const fmt = (n: number, digits = 2) =>
    new Intl.NumberFormat('en-US', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(n);

function useAfnTriple(afn: number | null | undefined) {
    const { usdToAfn, aedToAfn } = useExchange();
    const value = afn == null || !Number.isFinite(Number(afn)) ? 0 : Number(afn);
    const usd = usdToAfn && usdToAfn > 0 ? value / usdToAfn : null;
    const aed = aedToAfn && aedToAfn > 0 ? value / aedToAfn : null;
    return { afn: value, usd, aed };
}

function SingleAmount({
    amount,
    code,
    symbol,
}: {
    amount: number | null;
    code: string;
    symbol: string;
}) {
    if (amount == null) {
        return (
            <p className="text-muted-foreground mt-2 text-2xl font-bold tabular-nums">
                — <span className="text-sm font-medium opacity-70">{code}</span>
            </p>
        );
    }

    return (
        <p className="text-foreground mt-2 truncate text-2xl font-bold tabular-nums">
            <span className="text-lg opacity-80">{symbol}</span> {fmt(amount)}{' '}
            <span className="text-sm font-semibold opacity-70">{code}</span>
        </p>
    );
}

export default function KpiTripleCard({
    title,
    afn,
    subtitle,
    icon: Icon,
    color = 'blue',
    view,
    trend,
}: Props) {
    const c = COLORS[color];
    const { afn: afnVal, usd, aed } = useAfnTriple(afn);

    return (
        <div className="bg-card rounded-lg border p-5 shadow-sm transition-shadow hover:shadow-md">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-muted-foreground text-xs font-medium uppercase tracking-wide">{title}</p>

                    {view === 'ALL' ? (
                        <div className="mt-2 grid grid-cols-3 gap-2">
                            <div className="min-w-0">
                                <p className="text-muted-foreground text-[10px] font-semibold uppercase">AFN</p>
                                <p className="truncate font-mono text-sm font-bold tabular-nums">؋ {fmt(afnVal)}</p>
                            </div>
                            <div className="min-w-0">
                                <p className="text-muted-foreground text-[10px] font-semibold uppercase">USD</p>
                                <p className="truncate font-mono text-sm font-bold tabular-nums">
                                    {usd != null ? `$ ${fmt(usd)}` : '—'}
                                </p>
                            </div>
                            <div className="min-w-0">
                                <p className="text-muted-foreground text-[10px] font-semibold uppercase">AED</p>
                                <p className="truncate font-mono text-sm font-bold tabular-nums">
                                    {aed != null ? `د.إ ${fmt(aed)}` : '—'}
                                </p>
                            </div>
                        </div>
                    ) : view === 'AFN' ? (
                        <SingleAmount amount={afnVal} code="AFN" symbol="؋" />
                    ) : view === 'USD' ? (
                        <SingleAmount amount={usd} code="USD" symbol="$" />
                    ) : (
                        <SingleAmount amount={aed} code="AED" symbol="د.إ" />
                    )}

                    {subtitle && <p className="text-muted-foreground mt-1 text-xs">{subtitle}</p>}

                    {trend && (
                        <div className="mt-2 flex items-center gap-1">
                            <span
                                className={`text-xs font-semibold ${
                                    trend.isPositive
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-red-600 dark:text-red-400'
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

/** Compact money line for tables / nested cards — respects currency view */
export function CurrencyAwareAmount({
    afn,
    view,
    className = '',
}: {
    afn: number | null | undefined;
    view: CurrencyView;
    className?: string;
}) {
    const { afn: afnVal, usd, aed } = useAfnTriple(afn);

    if (view === 'ALL') {
        return (
            <div className={`font-mono text-xs leading-tight tabular-nums ${className}`}>
                <div>؋ {fmt(afnVal)}</div>
                <div className="text-muted-foreground">{usd != null ? `$ ${fmt(usd)}` : '—'}</div>
                <div className="text-muted-foreground">{aed != null ? `د.إ ${fmt(aed)}` : '—'}</div>
            </div>
        );
    }

    if (view === 'USD') {
        return (
            <span className={`font-mono tabular-nums ${className}`}>
                {usd != null ? `$ ${fmt(usd)}` : '—'}
            </span>
        );
    }

    if (view === 'AED') {
        return (
            <span className={`font-mono tabular-nums ${className}`}>
                {aed != null ? `د.إ ${fmt(aed)}` : '—'}
            </span>
        );
    }

    return <span className={`font-mono tabular-nums ${className}`}>؋ {fmt(afnVal)}</span>;
}
