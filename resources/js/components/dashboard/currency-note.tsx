import type { SharedCurrency } from '@/lib/money';

export type DashboardReporting = {
    currency: SharedCurrency;
    ledger: SharedCurrency;
    conversion_available: boolean;
    converts_from_ledger: boolean;
};

type CurrencyBucket = {
    code: string;
    symbol?: string | null;
    total: number;
};

type Props = {
    reporting: DashboardReporting;
    /** Original tender / transaction totals grouped by currency code. */
    originalCurrencyTotals?: CurrencyBucket[];
};

export default function CurrencyNote({ reporting, originalCurrencyTotals = [] }: Props) {
    const label = reporting.currency.code;

    if (reporting.converts_from_ledger && reporting.conversion_available) {
        return (
            <p className="text-muted-foreground text-xs">
                Combined totals are converted from {reporting.ledger.code} ledger amounts using configured catalog rates and shown in{' '}
                {label}. Original transaction rates are preserved on each sale and payment.
            </p>
        );
    }

    if (originalCurrencyTotals.length > 1) {
        return (
            <p className="text-muted-foreground text-xs">
                No reliable conversion rate is available. Amounts are not mixed across currencies. Original totals:{' '}
                {originalCurrencyTotals
                    .map((row) => `${row.total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${row.code}`)
                    .join(' · ')}
            </p>
        );
    }

    return <p className="text-muted-foreground text-xs">Amounts are shown in {label} (ledger currency).</p>;
}
