export type CatalogCurrency = {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    decimal_places: number;
    rate_to_default: string;
    is_default?: boolean;
    rate_configured?: boolean;
    status?: string;
};

export function currencyLabel(currency: CatalogCurrency | null | undefined): string {
    if (!currency) {
        return '';
    }

    const symbol = currency.symbol ? ` ${currency.symbol}` : '';

    return `${currency.code}${symbol} — ${currency.name}`;
}

export function findCurrency(currencies: CatalogCurrency[], id: string | number | null | undefined): CatalogCurrency | undefined {
    if (id === null || id === undefined || id === '') {
        return undefined;
    }

    return currencies.find((row) => String(row.id) === String(id));
}

export function defaultCurrencyId(currencies: CatalogCurrency[]): string {
    const preferred = currencies.find((row) => row.is_default) ?? currencies.find((row) => row.code === 'AFN') ?? currencies[0];

    return preferred ? String(preferred.id) : '';
}

export function convertToDefault(amount: number, from: CatalogCurrency | undefined, to: CatalogCurrency | undefined): number | null {
    if (!Number.isFinite(amount)) {
        return null;
    }
    if (!from || !to) {
        return amount;
    }
    if (from.id === to.id) {
        return amount;
    }

    const fromRate = Number(from.rate_to_default);
    const toRate = Number(to.rate_to_default);
    if (!(fromRate > 0) || !(toRate > 0) || from.rate_configured === false || to.rate_configured === false) {
        return null;
    }

    return (amount * fromRate) / toRate;
}

export type MoneyTotals = {
    by_currency: { code: string; symbol?: string | null; total: string }[];
    combined: { code: string; name?: string; symbol?: string | null; total: string } | null;
};

export function formatTotals(totals?: MoneyTotals | null): string {
    if (!totals) {
        return '';
    }

    if (totals.combined) {
        return `${totals.combined.total} ${totals.combined.code}`;
    }

    if (!totals.by_currency.length) {
        return '—';
    }

    return totals.by_currency.map((row) => `${row.total} ${row.code}`).join(' · ');
}

export function formatAmount(amount: number, currency?: CatalogCurrency | null): string {
    const digits = currency?.decimal_places ?? 2;
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(Number.isFinite(amount) ? amount : 0);
    const suffix = currency?.symbol || currency?.code;

    return suffix ? `${formatted} ${suffix}` : formatted;
}
