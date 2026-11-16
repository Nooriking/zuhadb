import { convertToDefault, type CatalogCurrency } from '@/lib/currencies';

/**
 * Estimate-only profit split helpers for Product Create/Edit.
 *
 * These use product reference rates (or catalog rates) for live previews only.
 * They must never be treated as authoritative purchase/sale FX snapshots —
 * backend PurchaseValuation / sale boards own historical money.
 */

export type ProfitSplitEstimate = {
    totalPerUnit: number;
    investorPerUnit: number;
    storePerUnit: number;
    investorLine: number;
    storeLine: number;
    totalLine: number;
};

/**
 * Estimate-only profit split in AFN ledger units.
 * Does not silently reduce investor profit when gross is insufficient.
 */
export const calcProfits = (
    purchasePricePerUnit: number,
    salePricePerUnit: number,
    investorProfitPerUnit: number,
    qty: number,
): ProfitSplitEstimate => {
    const totalPerUnit = salePricePerUnit - purchasePricePerUnit;
    const investorPerUnit = investorProfitPerUnit;
    const storePerUnit = totalPerUnit - investorPerUnit;

    return {
        totalPerUnit,
        investorPerUnit,
        storePerUnit,
        investorLine: investorPerUnit * qty,
        storeLine: storePerUnit * qty,
        totalLine: totalPerUnit * qty,
    };
};

/**
 * Convert an original amount to AFN using product reference rates when available,
 * else catalog rates. Returns null when conversion cannot be proven.
 */
export const estimateToAfn = (
    amount: number,
    from: CatalogCurrency | undefined,
    ledger: CatalogCurrency | undefined,
    usdToAfn: number,
    usdToAed: number,
): number | null => {
    if (!Number.isFinite(amount)) {
        return null;
    }
    if (!from || !ledger || from.code === 'AFN' || from.id === ledger.id) {
        return amount;
    }
    if (from.code === 'USD' && usdToAfn > 0) {
        return amount * usdToAfn;
    }
    if (from.code === 'AED' && usdToAfn > 0 && usdToAed > 0) {
        const aedToAfn = usdToAfn / usdToAed;
        return amount * aedToAfn;
    }

    return convertToDefault(amount, from, ledger);
};

export const formatEstimate = (n: number): string =>
    new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
