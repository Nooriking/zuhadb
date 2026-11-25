import { formatOriginalAmount } from '@/lib/original-currency';

export type DueCurrencyRow = {
    currency_code: string;
    balance_currency: number | string;
    balance_afn?: number | string;
    last_rate?: number | string | null;
};

type Props = {
    rows?: DueCurrencyRow[] | null;
    totalAfn?: number | string | null;
    className?: string;
    rowClassName?: string;
    empty?: string;
    showAfnHint?: boolean;
    digits?: number;
};

/**
 * Renders customer due in original currency units (never cross-sums currencies).
 */
export default function DueByCurrency({
    rows,
    totalAfn,
    className = 'space-y-0.5',
    rowClassName = 'font-mono font-semibold',
    empty = '—',
    showAfnHint = false,
    digits = 2,
}: Props) {
    const list = (rows || []).filter((row) => Math.abs(Number(row.balance_currency ?? 0)) > 0.009);

    if (list.length === 0) {
        const afn = Number(totalAfn ?? 0);
        if (Math.abs(afn) > 0.009) {
            return <div className={rowClassName}>{formatOriginalAmount(afn, 'AFN', digits)}</div>;
        }
        return <span className="text-muted-foreground">{empty}</span>;
    }

    return (
        <div className={className}>
            {list.map((row) => (
                <div key={row.currency_code} className={rowClassName}>
                    {formatOriginalAmount(row.balance_currency, row.currency_code, digits)}
                    {showAfnHint &&
                        String(row.currency_code).toUpperCase() !== 'AFN' &&
                        row.balance_afn != null && (
                            <span className="text-muted-foreground ml-1 text-xs font-normal">
                                ({formatOriginalAmount(row.balance_afn, 'AFN', digits)})
                            </span>
                        )}
                </div>
            ))}
        </div>
    );
}
