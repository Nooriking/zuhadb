/** Shared three-currency dashboard display types and helpers. */

export type ThreeCurrencyAmount = {
    afn: number | null;
    usd: number | null;
    aed: number | null;
    available: boolean;
    basis: 'reporting_date_fx_board' | 'ledger_only' | string;
    board?: {
        usd_to_afn?: string | null;
        aed_to_afn?: string | null;
        usd_to_aed?: string | null;
        effective_at?: string | null;
        source?: string | null;
        kind?: string | null;
    } | null;
};

export type DashboardFxBoard = {
    available: boolean;
    basis: string;
    usd_to_afn: string | null;
    aed_to_afn: string | null;
    usd_to_aed: string | null;
    effective_at: string | null;
    source: string | null;
    kind: string | null;
};

export type ChartCurrency = 'AFN' | 'USD' | 'AED';

export function formatThreeCurrencyLine(
    amount: number | null | undefined,
    code: ChartCurrency,
    digits = 2,
): string {
    if (amount === null || amount === undefined || !Number.isFinite(Number(amount))) {
        return `— ${code}`;
    }

    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(Number(amount));

    return `${formatted} ${code}`;
}

export function pickChartAmount(
    tri: ThreeCurrencyAmount | null | undefined,
    currency: ChartCurrency,
): number | null {
    if (!tri) {
        return null;
    }

    const key = currency.toLowerCase() as 'afn' | 'usd' | 'aed';
    const value = tri[key];

    if (value === null || value === undefined || !Number.isFinite(Number(value))) {
        return null;
    }

    return Number(value);
}

export function chartSeriesFromTri(
    rows: Array<{ date: string; count?: number; sales_3c?: ThreeCurrencyAmount; profit_3c?: ThreeCurrencyAmount; sales?: number; profit?: number }>,
    currency: ChartCurrency,
): Array<{ date: string; sales: number; profit: number; count: number }> {
    return rows.map((row) => {
        const sales = pickChartAmount(row.sales_3c, currency);
        const profit = pickChartAmount(row.profit_3c, currency);

        return {
            date: row.date,
            sales: sales ?? (currency === 'AFN' ? Number(row.sales ?? 0) : 0),
            profit: profit ?? (currency === 'AFN' ? Number(row.profit ?? 0) : 0),
            count: Number(row.count ?? 0),
        };
    });
}
