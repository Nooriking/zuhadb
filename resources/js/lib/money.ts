export type SharedCurrency = {
    code: string;
    name?: string;
    symbol: string | null;
    decimal_places: number;
    rate_to_default?: string;
    is_default?: boolean;
};

export type SharedCurrencyPayload = {
    default?: SharedCurrency | null;
    ledger?: SharedCurrency | null;
};

export type LedgerConversion = {
    amount: number;
    currency: SharedCurrency;
    converted: boolean;
};

const FALLBACK_LEDGER: SharedCurrency = {
    code: 'AFN',
    name: 'Afghan Afghani',
    symbol: '؋',
    decimal_places: 2,
    rate_to_default: '1',
    is_default: true,
};

function positiveRate(currency?: SharedCurrency | null): boolean {
    const rate = Number(currency?.rate_to_default ?? 0);
    return Number.isFinite(rate) && rate > 0;
}

/** Convert a ledger (AFN) amount using configured catalog rates. Never mixes source currencies. */
export function convertLedgerAmount(
    amount: number | string | null | undefined,
    payload?: SharedCurrencyPayload | null,
): LedgerConversion {
    const n = Number(amount ?? 0);
    const safe = Number.isFinite(n) ? n : 0;
    const ledger = payload?.ledger || FALLBACK_LEDGER;
    const reporting = payload?.default && positiveRate(payload.default) && positiveRate(ledger) ? payload.default : ledger;

    if (!payload?.default || reporting.code === ledger.code || !positiveRate(ledger) || !positiveRate(payload.default)) {
        return { amount: safe, currency: ledger, converted: false };
    }

    const converted = (safe * Number(ledger.rate_to_default)) / Number(payload.default.rate_to_default);

    return {
        amount: converted,
        currency: payload.default,
        converted: true,
    };
}

export function formatLedgerMoney(
    amount: number | string | null | undefined,
    payload?: SharedCurrencyPayload | null,
): string {
    const converted = convertLedgerAmount(amount, payload);
    return formatMoney(converted.amount, converted.currency);
}

export function formatMoney(amount: number | string | null | undefined, currency?: SharedCurrency | null): string {
    const n = Number(amount ?? 0);
    const digits = currency?.decimal_places ?? 2;
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(Number.isFinite(n) ? n : 0);
    const suffix = currency?.symbol || currency?.code;

    return suffix ? `${formatted} ${suffix}` : formatted;
}

export function formatRate(rate: number | string | null | undefined): string {
    const n = Number(rate ?? 0);
    if (!Number.isFinite(n)) {
        return '—';
    }

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 8,
    }).format(n);
}
