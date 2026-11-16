import { formatThreeCurrencyLine, type ThreeCurrencyAmount } from '@/lib/three-currency';

type Props = {
    amounts?: ThreeCurrencyAmount | null;
    /** Compact single-column stack for KPI cards; inline for tables. */
    layout?: 'stack' | 'inline' | 'compact';
    className?: string;
    showBasisHint?: boolean;
};

export default function ThreeCurrencyAmountView({
    amounts,
    layout = 'stack',
    className = '',
    showBasisHint = false,
}: Props) {
    if (!amounts) {
        return <span className={`text-muted-foreground text-xs ${className}`}>—</span>;
    }

    const afn = formatThreeCurrencyLine(amounts.afn, 'AFN');
    const usd = formatThreeCurrencyLine(amounts.usd, 'USD');
    const aed = formatThreeCurrencyLine(amounts.aed, 'AED');

    if (layout === 'inline') {
        return (
            <span className={`font-mono text-xs ${className}`} title={amounts.available ? 'Reporting-date FX board equivalents' : 'USD/AED unavailable'}>
                {afn}
                <span className="text-muted-foreground"> · </span>
                {usd}
                <span className="text-muted-foreground"> · </span>
                {aed}
            </span>
        );
    }

    if (layout === 'compact') {
        return (
            <div className={`space-y-0.5 font-mono text-xs leading-tight ${className}`}>
                <div>{afn}</div>
                <div className="text-muted-foreground">{usd}</div>
                <div className="text-muted-foreground">{aed}</div>
            </div>
        );
    }

    return (
        <div className={`mt-2 space-y-0.5 ${className}`}>
            <div className="font-mono text-sm font-semibold tabular-nums">{afn}</div>
            <div className="text-muted-foreground font-mono text-xs tabular-nums">{usd}</div>
            <div className="text-muted-foreground font-mono text-xs tabular-nums">{aed}</div>
            {showBasisHint && (
                <p className="text-muted-foreground pt-1 text-[10px] leading-snug">
                    {amounts.available
                        ? 'USD/AED = reporting-date FX board on AFN ledger total'
                        : 'USD/AED unavailable — AFN ledger only'}
                </p>
            )}
        </div>
    );
}
