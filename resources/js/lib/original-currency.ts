/** Shared original-transaction currency display (AFN / USD / AED). Never cross-sum currencies. */

export type OriginalCurrencyCode = 'AFN' | 'USD' | 'AED' | 'UNKNOWN' | string;

export type OriginalCurrencyTotal = {
    code: OriginalCurrencyCode;
    label: string;
    short_label?: string;
    symbol?: string | null;
    total: number;
};

export type OriginalMoney = {
    code: string | null;
    symbol?: string | null;
    known: boolean;
    label: string;
    amount: number | null;
    ledger_afn?: number;
};

export function currencyLabel(code: string | null | undefined): string {
    switch ((code || '').toUpperCase()) {
        case 'AFN':
            return 'AFN (Afghani)';
        case 'USD':
            return 'USD (Dollar)';
        case 'AED':
            return 'AED (Dirham)';
        case 'UNKNOWN':
        case '':
            return 'Unknown';
        default:
            return (code || 'Unknown').toUpperCase();
    }
}

export function formatOriginalAmount(
    amount: number | string | null | undefined,
    code: string | null | undefined,
    digits = 2,
): string {
    if (amount === null || amount === undefined || amount === '') {
        const suffix = code ? currencyLabel(code) : 'Unknown';
        return `— ${suffix}`;
    }

    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    const formatted = new Intl.NumberFormat('en-US', {
        maximumFractionDigits: digits,
        minimumFractionDigits: 0,
    }).format(Number.isFinite(num) ? num : 0);

    const suffix = code ? code.toUpperCase() : 'Unknown';

    return `${formatted} ${suffix}`;
}

export function formatOriginalMoney(money: OriginalMoney | null | undefined, digits = 2): string {
    if (!money) {
        return '— Unknown';
    }

    if (!money.known || money.amount === null || money.amount === undefined) {
        // Surface unknown currency; still show ledger AFN when that is all we have.
        if (money.ledger_afn !== undefined && money.ledger_afn !== null) {
            return `${formatOriginalAmount(money.ledger_afn, 'AFN', digits)} · currency unknown`;
        }

        return '— Unknown';
    }

    return formatOriginalAmount(money.amount, money.code, digits);
}

export function ensureThreeCurrencyTotals(
    list: OriginalCurrencyTotal[] | null | undefined,
): OriginalCurrencyTotal[] {
    const map = new Map((list || []).map((row) => [row.code.toUpperCase(), row]));

    return (['AFN', 'USD', 'AED'] as const).map((code) => {
        const existing = map.get(code);

        return (
            existing || {
                code,
                label: currencyLabel(code),
                short_label: code === 'AFN' ? 'Afghani' : code === 'USD' ? 'Dollar' : 'Dirham',
                symbol: null,
                total: 0,
            }
        );
    }).concat(
        [...map.values()].filter((row) => !['AFN', 'USD', 'AED'].includes(row.code.toUpperCase()) && Number(row.total) !== 0),
    );
}
