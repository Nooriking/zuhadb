/**
 * Low-stock threshold — keep in sync with App\Models\Inventory::LOW_STOCK_THRESHOLD
 * and config('business.default_low_stock_threshold').
 */
export const LOW_STOCK_THRESHOLD = 2;

/** Format catalog amounts: AFN as whole/2dp, USD/AED with up to 4dp so small prices are visible. */
export function formatCatalogAmount(
    value: string | number | null | undefined,
    currencyCode?: string | null,
): string {
    const num = typeof value === 'string' ? parseFloat(value) : Number(value ?? 0);
    const safe = Number.isFinite(num) ? num : 0;
    const code = (currencyCode || 'AFN').toUpperCase();
    const maxFractionDigits = code === 'AFN' ? 2 : 4;
    const minFractionDigits = code === 'AFN' ? 0 : 2;

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: minFractionDigits,
        maximumFractionDigits: maxFractionDigits,
    }).format(safe);
}
