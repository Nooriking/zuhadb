import { usePage } from '@inertiajs/react';

const positive = (v) => {
    if (v == null || v === '') return null;
    const n = Number(v);
    return Number.isFinite(n) && n > 0 ? n : null;
};

export function useExchange() {
    const { exchange } = usePage().props;

    const usdToAfn = positive(exchange?.usd_to_afn);
    const aedToAfn = positive(exchange?.aed_to_afn);
    const usdToAed = positive(exchange?.usd_to_aed);

    /** AFN per 1 unit of `code`, or null when the board has no rate for it. */
    const rateToAfn = (code) => {
        const c = String(code || 'AFN').toUpperCase();
        if (c === 'AFN') return 1;
        if (c === 'USD') return usdToAfn;
        if (c === 'AED') return aedToAfn;
        return null;
    };

    /** Display-only conversion. Returns null when a required rate is missing. */
    const convert = (amount, from, to) => {
        const n = Number(amount) || 0;
        const f = String(from || 'AFN').toUpperCase();
        const t = String(to || 'AFN').toUpperCase();
        if (f === t) return n;
        if (f === 'USD' && t === 'AED' && usdToAed) return n * usdToAed;
        if (f === 'AED' && t === 'USD' && usdToAed) return n / usdToAed;
        const fromAfn = rateToAfn(f);
        const toAfn = rateToAfn(t);
        if (fromAfn == null || toAfn == null) return null;
        return (n * fromAfn) / toAfn;
    };

    const hasRate = (from, to = 'AFN') => convert(1, from, to) != null;

    return {
        usdToAfn,
        aedToAfn,
        usdToAed,
        effectiveAt: exchange?.effective_at ?? null,
        source: exchange?.source ?? null,
        rateToAfn,
        convert,
        hasRate,
    };
}
