import { type ThreeCurrency } from '@/lib/currency';

interface Props {
    value: ThreeCurrency;
    size?: 'xs' | 'sm' | 'md' | 'lg';
    highlight?: 'afn' | 'usd' | 'aed' | null;
    layout?: 'inline' | 'stacked';
}

export function ThreeCurrencyDisplay({ value, size = 'sm', highlight = null, layout = 'inline' }: Props) {
    const fmt = (n: number, d: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: d }).format(n);
    const ts = size === 'xs' ? 'text-[10px]' : size === 'sm' ? 'text-xs' : size === 'md' ? 'text-sm' : 'text-base';
    const h = (k: string) => (highlight === k ? 'font-bold text-primary' : 'font-mono');

    if (layout === 'stacked') {
        return (
            <div className={`flex flex-col gap-0.5 ${ts}`}>
                <span className={h('afn')}>{fmt(value.afn, 2)} AFN</span>
                <span className={h('usd')}>{fmt(value.usd, 4)} USD</span>
                <span className={h('aed')}>{fmt(value.aed, 4)} AED</span>
            </div>
        );
    }

    return (
        <div className={`flex flex-wrap items-baseline gap-1.5 ${ts}`}>
            <span className={h('afn')}>{fmt(value.afn, 2)} AFN</span>
            <span className="text-muted-foreground/40">·</span>
            <span className={h('usd')}>{fmt(value.usd, 4)} USD</span>
            <span className="text-muted-foreground/40">·</span>
            <span className={h('aed')}>{fmt(value.aed, 4)} AED</span>
        </div>
    );
}
