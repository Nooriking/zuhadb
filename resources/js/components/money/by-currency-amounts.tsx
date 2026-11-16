import {
    ensureThreeCurrencyTotals,
    formatOriginalAmount,
    type OriginalCurrencyTotal,
} from '@/lib/original-currency';

type Props = {
    amounts?: OriginalCurrencyTotal[] | null;
    /** Prefix each line, e.g. "فروش" / "برگشت" */
    titlePrefix?: string;
    className?: string;
    valueClassName?: string;
    /** Keep zero AFN/USD/AED lines visible (default true). */
    showZeros?: boolean;
    digits?: number;
};

/**
 * Renders AFN / USD / AED totals on separate lines. Never sums across currencies.
 */
export default function ByCurrencyAmounts({
    amounts,
    titlePrefix,
    className,
    valueClassName = 'mt-1 font-mono text-xl font-bold',
    showZeros = true,
    digits = 0,
}: Props) {
    const rows = ensureThreeCurrencyTotals(amounts).filter((row) => showZeros || Number(row.total) !== 0);
    const unknown = (amounts || []).filter((row) => row.code.toUpperCase() === 'UNKNOWN' && Number(row.total) !== 0);

    if (rows.length === 0 && unknown.length === 0) {
        return <p className={valueClassName}>—</p>;
    }

    return (
        <div className={className ?? 'mt-1 space-y-1'}>
            {rows.map((row) => (
                <div key={row.code} className={valueClassName}>
                    {titlePrefix ? (
                        <span className="text-muted-foreground mr-1 text-xs font-normal">
                            {titlePrefix} {row.short_label || row.label}:
                        </span>
                    ) : null}
                    {formatOriginalAmount(row.total, row.code, digits)}
                </div>
            ))}
            {unknown.map((row) => (
                <div key={`unknown-${row.total}`} className={`${valueClassName} text-amber-700`}>
                    {formatOriginalAmount(row.total, 'AFN', digits)} · Unknown currency
                </div>
            ))}
        </div>
    );
}
