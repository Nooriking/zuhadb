import { useExchange } from '@/hooks/useExchange';

/** @deprecated Prefer useExchange(); kept for pages that still receive fxBoard props. */
export type FxBoardSnapshot = {
    usd_to_afn_rate?: string | null;
    aed_to_afn_rate?: string | null;
    usd_to_aed_rate?: string | null;
    rate_effective_at?: string | null;
    rate_source?: string | null;
};

/** @deprecated Prefer useExchange(); kept for pages that still receive fxBoard props. */
export type FxBoardContext = {
    currencies?: {
        afn?: { id?: number; code: string; name: string; symbol: string | null } | null;
        usd?: { id?: number; code: string; name: string; symbol: string | null } | null;
        aed?: { id?: number; code: string; name: string; symbol: string | null } | null;
    };
    board?: FxBoardSnapshot | null;
    board_kind?: string | null;
    history?: Array<{
        id: number;
        from_currency_id: number;
        to_currency_id: number;
        rate: string;
        effective_at: string | null;
        source: string | null;
    }>;
    policy?: string;
};

type Props = {
    /** Optional labels; rates always come from shared ExchangeService props. */
    context?: FxBoardContext;
};

function formatRate(value: number | null | undefined): string {
    if (value == null || Number.isNaN(value) || !Number.isFinite(value)) return '—';
    const s = String(value);
    if (!s.includes('.')) return s;
    return s.replace(/(\.\d*?[1-9])0+$/, '$1').replace(/\.0+$/, '');
}

export function TransactionExchangeRateSection({ context }: Props) {
    const { usdToAfn, aedToAfn, usdToAed, effectiveAt, source } = useExchange();

    const usdCode = context?.currencies?.usd?.code || 'USD';
    const aedCode = context?.currencies?.aed?.code || 'AED';
    const afnCode = context?.currencies?.afn?.code || 'AFN';

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <div>
                <h2 className="text-sm font-semibold">Exchange rates</h2>
                <p className="text-muted-foreground text-xs">
                    {context?.policy ||
                        `Base rates: AFN per 1 ${usdCode} and AFN per 1 ${aedCode}. ${usdCode}/${aedCode} is derived. Server applies these rates at post time.`}
                </p>
                {effectiveAt || source ? (
                    <p className="text-muted-foreground mt-1 text-xs">
                        {effectiveAt ? `Active as of ${effectiveAt}` : null}
                        {effectiveAt && source ? ' · ' : null}
                        {source || null}
                    </p>
                ) : null}
            </div>

            <dl className="grid grid-cols-1 gap-4 md:grid-cols-3">
                {[
                    { label: `${usdCode} → ${afnCode}`, value: usdToAfn },
                    { label: `${aedCode} → ${afnCode}`, value: aedToAfn },
                    { label: `${usdCode} → ${aedCode} (derived)`, value: usdToAed },
                ].map((row) => (
                    <div key={row.label} className="bg-muted/30 rounded-md border px-3 py-2">
                        <dt className="text-muted-foreground text-xs">{row.label}</dt>
                        <dd className="font-mono text-base font-semibold">{formatRate(row.value)}</dd>
                    </div>
                ))}
            </dl>
            {(!usdToAfn || !aedToAfn) && (
                <p className="text-xs text-amber-800">
                    Some board rates are missing. Only an admin can post rates, from Exchange Rates → New Rate.
                </p>
            )}
        </div>
    );
}

export function threeCurrencyEquivalents(
    amount: number,
    currencyCode: string,
    usdToAfn: number,
    aedToAfn: number,
): { afn: number; usd: number; aed: number } {
    if (!amount || amount < 0) return { afn: 0, usd: 0, aed: 0 };
    const code = (currencyCode || 'AFN').toUpperCase();
    if (!usdToAfn || !aedToAfn || usdToAfn <= 0 || aedToAfn <= 0) {
        return code === 'AFN' ? { afn: amount, usd: 0, aed: 0 } : { afn: 0, usd: 0, aed: 0 };
    }
    let afn = amount;
    if (code === 'USD') afn = amount * usdToAfn;
    if (code === 'AED') afn = amount * aedToAfn;
    const usd = code === 'USD' ? amount : afn / usdToAfn;
    const aed = code === 'AED' ? amount : afn / aedToAfn;
    return {
        afn: Math.round(afn * 100) / 100,
        usd: Math.round(usd * 10000) / 10000,
        aed: Math.round(aed * 10000) / 10000,
    };
}
