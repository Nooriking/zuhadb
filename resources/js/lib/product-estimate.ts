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
 * Convert an original amount to AFN using board rates when available,
 * else catalog rates toward an AFN ledger row.
 *
 * IMPORTANT: never treat catalog is_base (which may be USD) as “already AFN”.
 * Only skip conversion when the source currency code is AFN.
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
    if (!from) {
        return null;
    }

    const code = String(from.code || '').toUpperCase();
    if (code === 'AFN') {
        return amount;
    }
    if (code === 'USD') {
        return usdToAfn > 0 ? amount * usdToAfn : null;
    }
    if (code === 'AED') {
        if (usdToAfn > 0 && usdToAed > 0) {
            return amount * (usdToAfn / usdToAed);
        }
        return null;
    }

    // Non AFN/USD/AED: only convert via catalog rates when ledger is actually AFN.
    const afnLedger =
        ledger && String(ledger.code || '').toUpperCase() === 'AFN' ? ledger : undefined;
    if (!afnLedger) {
        return null;
    }

    return convertToDefault(amount, from, afnLedger);
};

export const formatEstimate = (n: number): string =>
    new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
