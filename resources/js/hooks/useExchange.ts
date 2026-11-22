import { usePage } from '@inertiajs/react';
import type { SharedData, SharedExchangeRates } from '@/types';

export type ExchangeHook = {
    usdToAfn: number | null;
    aedToAfn: number | null;
    usdToAed: number | null;
    effectiveAt: string | null;
    source: string | null;
    updatedAt: string | null;
    rateToAfn: (code: string | null | undefined) => number | null;
    convert: (amount: number | string | null | undefined, from: string, to: string) => number | null;
    hasRate: (from: string, to?: string) => boolean;
};

const positive = (v: unknown): number | null => {
    if (v == null || v === '') return null;
    const n = Number(v);
    return Number.isFinite(n) && n > 0 ? n : null;
};

/**
 * Shared Inertia board rates from HandleInertiaRequests → `exchange`
 * (ExchangeRateService::flatBoard). Prefer page `fxBoard` only for form labels/history.
 */
export function useExchange(): ExchangeHook {
    const { exchange } = usePage<SharedData>().props;
    const board: SharedExchangeRates | undefined = exchange;

    const usdToAfn = positive(board?.usd_to_afn);
    const aedToAfn = positive(board?.aed_to_afn);
    const usdToAed = positive(board?.usd_to_aed);

    const rateToAfn = (code: string | null | undefined): number | null => {
        const c = String(code || 'AFN').toUpperCase();
        if (c === 'AFN') return 1;
        if (c === 'USD') return usdToAfn;
        if (c === 'AED') return aedToAfn;
        return null;
    };

    const convert = (
        amount: number | string | null | undefined,
        from: string,
        to: string,
    ): number | null => {
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

    const hasRate = (from: string, to = 'AFN'): boolean => convert(1, from, to) != null;

    return {
        usdToAfn,
        aedToAfn,
        usdToAed,
        effectiveAt: board?.effective_at ?? null,
        source: board?.source ?? null,
        updatedAt: board?.updated_at ?? null,
        rateToAfn,
        convert,
        hasRate,
    };
}

/** @deprecated Alias — there is no separate board hook; shared prop is `exchange`. */
export const useExchangeBoard = useExchange;
