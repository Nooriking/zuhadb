import type { SharedCurrency } from '@/lib/money';
import type { DashboardFxBoard } from '@/lib/three-currency';

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
    /** Reporting-date FX board used for AFN→USD/AED dashboard equivalents. */
    fxBoard?: DashboardFxBoard | null;
};

export default function CurrencyNote({ reporting, originalCurrencyTotals = [], fxBoard = null }: Props) {
    const label = reporting.currency.code;
    const boardHint =
        fxBoard?.available && fxBoard.usd_to_afn && fxBoard.aed_to_afn
            ? ` Three-currency KPI equivalents use the reporting-date board (1 USD = ${fxBoard.usd_to_afn} AFN · 1 AED = ${fxBoard.aed_to_afn} AFN) — not historical per-transaction rates.`
            : fxBoard && !fxBoard.available
              ? ' USD/AED equivalents are unavailable until a valid FX board can be resolved.'
              : '';

    if (reporting.converts_from_ledger && reporting.conversion_available) {
        return (
            <p className="text-muted-foreground text-xs">
                Combined totals are converted from {reporting.ledger.code} ledger amounts using configured catalog rates and shown in{' '}
                {label}. Original transaction rates are preserved on each sale and payment.
                {boardHint}
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
                {boardHint}
            </p>
        );
    }

    return (
        <p className="text-muted-foreground text-xs">
            AFN is the accounting ledger. KPI cards show AFN plus reporting-date USD and AED equivalents when the FX board is available.
            {boardHint || ` Display preference: ${label}.`}
        </p>
    );
}
