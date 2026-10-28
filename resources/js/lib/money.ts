export type SharedCurrency = {
    code: string;
    name?: string;
    symbol: string | null;
    decimal_places: number;
    rate_to_default?: string;
    is_default?: boolean;
};

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
