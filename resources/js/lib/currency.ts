export type FxRates = {
    usd_to_afn: number;
    aed_to_afn: number;
};

export type ThreeCurrency = {
    afn: number;
    usd: number;
    aed: number;
};

export function rateToAfn(code: string, rates: FxRates): number {
    const u = code.toUpperCase();
    if (u === 'AFN') return 1;
    if (u === 'USD') return rates.usd_to_afn;
    if (u === 'AED') return rates.aed_to_afn;
    return 1;
}

export function toAfn(amount: number, code: string, rates: FxRates): number {
    return Math.round(amount * rateToAfn(code, rates) * 100) / 100;
}

export function fromAfn(amountAfn: number, code: string, rates: FxRates): number {
    const rate = rateToAfn(code, rates);
    if (rate <= 0) return 0;
    const decimals = code.toUpperCase() === 'AFN' ? 2 : 4;
    const f = Math.pow(10, decimals);
    return Math.round((amountAfn / rate) * f) / f;
}

export function threeWays(amountAfn: number, rates: FxRates): ThreeCurrency {
    return {
        afn: Math.round(amountAfn * 100) / 100,
        usd: fromAfn(amountAfn, 'USD', rates),
        aed: fromAfn(amountAfn, 'AED', rates),
    };
}

/**
 * Mirror of backend FxBoard::equivalents — original currency amount preserved exactly.
 * rates.aed_to_afn must already be resolved (not USD→AED).
 */
export function catalogEquivalents(
    amount: number,
    code: string,
    rates: FxRates,
    decimals = 2,
): ThreeCurrency | null {
    if (!Number.isFinite(amount) || amount < 0) {
        return null;
    }
    if (!(rates.usd_to_afn > 0) || !(rates.aed_to_afn > 0)) {
        return null;
    }

    const u = code.toUpperCase();
    let afn: number;
    if (u === 'AFN') {
        afn = amount;
    } else if (u === 'USD') {
        afn = amount * rates.usd_to_afn;
    } else if (u === 'AED') {
        afn = amount * rates.aed_to_afn;
    } else {
        return null;
    }

    const factor = Math.pow(10, decimals);
    const round = (n: number) => Math.round(n * factor) / factor;

    const result: ThreeCurrency = {
        afn: round(afn),
        usd: round(afn / rates.usd_to_afn),
        aed: round(afn / rates.aed_to_afn),
    };

    if (u === 'USD') result.usd = round(amount);
    if (u === 'AED') result.aed = round(amount);
    if (u === 'AFN') result.afn = round(amount);

    return result;
}

/** Derive AFN-pivot rates from product pair rates (AFN/AED per 1 USD) or board rates. */
export function resolveCatalogFxRates(input: {
    usdToAfn?: number;
    usdToAed?: number;
    aedToAfn?: number;
    boardUsdToAfn?: number;
    boardAedToAfn?: number;
}): FxRates | null {
    const usdToAfn =
        input.usdToAfn && input.usdToAfn > 0
            ? input.usdToAfn
            : input.boardUsdToAfn && input.boardUsdToAfn > 0
              ? input.boardUsdToAfn
              : 0;

    let aedToAfn =
        input.aedToAfn && input.aedToAfn > 0
            ? input.aedToAfn
            : input.boardAedToAfn && input.boardAedToAfn > 0
              ? input.boardAedToAfn
              : 0;

    if (!(aedToAfn > 0) && usdToAfn > 0 && input.usdToAed && input.usdToAed > 0) {
        aedToAfn = usdToAfn / input.usdToAed;
    }

    if (!(usdToAfn > 0) || !(aedToAfn > 0)) {
        return null;
    }

    return { usd_to_afn: usdToAfn, aed_to_afn: aedToAfn };
}
