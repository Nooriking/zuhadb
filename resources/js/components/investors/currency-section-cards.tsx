import { formatOriginalAmount } from '@/lib/original-currency';

export type CurrencySectionMetric = {
    code: string;
    label: string;
    cash_capital: string | number;
    goods_purchase_value: string | number;
    inventory_value: string | number;
    sold_goods_value: string | number;
    investor_profit: string | number;
    paid_profit: string | number;
    pending_profit: string | number;
    withdrawals: string | number;
    current_balance: string | number | null;
    current_balance_available?: boolean;
    historical_afn?: Record<string, string | number>;
    unit_count?: number;
    sold_unit_count?: number;
    batch_count?: number;
    metrics_available?: Record<string, boolean>;
};

export type ExchangeValuation = {
    calculation_date: string;
    basis: string;
    basis_label: string;
    total_afn_inventory_cost: string | number;
    inventory_by_currency: Record<
        string,
        {
            code: string;
            original_amount: string | number;
            historical_afn: string | number;
            units: number;
            batches: number;
            weighted_average_fx_to_afn: string | number | null;
            kind: string;
            available: boolean;
        }
    >;
    weighted_average_usd_afn: string | number | null;
    weighted_average_aed_afn: string | number | null;
    batches_included: number;
    units_included: number;
    note?: string;
};

type Props = {
    currencySections?: Record<string, CurrencySectionMetric> | null;
    exchangeValuation?: ExchangeValuation | null;
    limitations?: string[];
    legacy?: {
        profit_afn_without_currency?: string | number;
        withdrawals_afn_without_currency?: string | number;
        purchases_without_currency?: number;
        note?: string;
    } | null;
};

function MetricRow({
    label,
    amount,
    code,
    available = true,
    historicalAfn,
}: {
    label: string;
    amount: string | number | null | undefined;
    code: string;
    available?: boolean;
    historicalAfn?: string | number;
}) {
    return (
        <div className="flex items-start justify-between gap-3 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
            <span className="text-gray-500 dark:text-gray-400">{label}</span>
            <div className="text-right">
                <p className="font-semibold text-gray-900 dark:text-white">
                    {available === false || amount === null || amount === undefined
                        ? 'Unavailable'
                        : formatOriginalAmount(amount, code, 2)}
                </p>
                {historicalAfn !== undefined && available !== false && amount !== null && (
                    <p className="text-muted-foreground text-[11px]">
                        Historical AFN: {formatOriginalAmount(historicalAfn, 'AFN', 2)}
                    </p>
                )}
            </div>
        </div>
    );
}

const ORDER = ['AFN', 'USD', 'AED'] as const;

export default function CurrencySectionCards({
    currencySections = null,
    exchangeValuation = null,
    limitations = [],
    legacy = null,
}: Props) {
    if (!currencySections) {
        return (
            <div className="rounded-2xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700">
                Multi-currency sections are loading or unavailable.
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">
                    Original currency summaries
                </h2>
                <p className="text-muted-foreground mt-1 text-xs">
                    Each section shows values that belong to that currency only. AFN, USD, and AED originals are never
                    added together. Historical AFN is the ledger snapshot from each transaction&apos;s own rate — not
                    today&apos;s market rate.
                </p>
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                {ORDER.map((code) => {
                    const section = currencySections[code];
                    if (!section) {
                        return null;
                    }

                    return (
                        <div
                            key={code}
                            className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                        >
                            <div className="mb-3 flex items-center justify-between">
                                <h3 className="text-base font-bold text-gray-900 dark:text-white">{section.label}</h3>
                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium dark:bg-gray-800">
                                    {code}
                                </span>
                            </div>
                            <MetricRow
                                label="Cash capital"
                                amount={section.cash_capital}
                                code={code}
                                historicalAfn={section.historical_afn?.cash_capital}
                            />
                            <MetricRow
                                label="Goods purchase value"
                                amount={section.goods_purchase_value}
                                code={code}
                                historicalAfn={section.historical_afn?.goods_purchase_value}
                            />
                            <MetricRow
                                label="Current inventory"
                                amount={section.inventory_value}
                                code={code}
                                historicalAfn={section.historical_afn?.inventory_value}
                            />
                            <MetricRow
                                label="Sold goods value"
                                amount={section.sold_goods_value}
                                code={code}
                                historicalAfn={section.historical_afn?.sold_goods_value}
                            />
                            <MetricRow
                                label="Investor profit"
                                amount={section.investor_profit}
                                code={code}
                                historicalAfn={section.historical_afn?.investor_profit}
                            />
                            <MetricRow
                                label="Paid profit"
                                amount={section.paid_profit}
                                code={code}
                                historicalAfn={section.historical_afn?.paid_profit}
                            />
                            <MetricRow
                                label="Pending profit"
                                amount={section.pending_profit}
                                code={code}
                                historicalAfn={section.historical_afn?.pending_profit}
                            />
                            <MetricRow
                                label="Withdrawals"
                                amount={section.withdrawals}
                                code={code}
                                historicalAfn={section.historical_afn?.withdrawals}
                            />
                            <MetricRow
                                label="Current balance"
                                amount={section.current_balance}
                                code={code}
                                available={section.current_balance_available !== false && section.metrics_available?.current_balance !== false}
                            />
                            <p className="text-muted-foreground mt-3 text-[11px]">
                                Remaining units: {section.unit_count ?? 0} · Sold units: {section.sold_unit_count ?? 0} ·
                                Batches: {section.batch_count ?? 0}
                            </p>
                        </div>
                    );
                })}
            </div>

            {exchangeValuation && (
                <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div className="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="text-base font-bold text-gray-900 dark:text-white">
                                Exchange / currency valuation
                            </h3>
                            <p className="text-muted-foreground text-xs">{exchangeValuation.basis_label}</p>
                        </div>
                        <p className="text-muted-foreground text-xs">As of {exchangeValuation.calculation_date}</p>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                            <p className="text-xs text-gray-500">Total AFN inventory cost</p>
                            <p className="mt-1 font-semibold">
                                {formatOriginalAmount(exchangeValuation.total_afn_inventory_cost, 'AFN', 2)}
                            </p>
                        </div>
                        <div className="rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                            <p className="text-xs text-gray-500">Weighted avg USD/AFN</p>
                            <p className="mt-1 font-semibold">
                                {exchangeValuation.weighted_average_usd_afn != null
                                    ? `${Number(exchangeValuation.weighted_average_usd_afn).toFixed(2)} (historical)`
                                    : 'Unavailable'}
                            </p>
                        </div>
                        <div className="rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                            <p className="text-xs text-gray-500">Weighted avg AED/AFN</p>
                            <p className="mt-1 font-semibold">
                                {exchangeValuation.weighted_average_aed_afn != null
                                    ? `${Number(exchangeValuation.weighted_average_aed_afn).toFixed(2)} (historical)`
                                    : 'Unavailable'}
                            </p>
                        </div>
                        <div className="rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                            <p className="text-xs text-gray-500">Coverage</p>
                            <p className="mt-1 font-semibold">
                                {exchangeValuation.batches_included} batches · {exchangeValuation.units_included} units
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                        {ORDER.map((code) => {
                            const row = exchangeValuation.inventory_by_currency?.[code];
                            if (!row) {
                                return null;
                            }

                            return (
                                <div key={code} className="rounded-xl border border-gray-100 p-3 text-sm dark:border-gray-800">
                                    <p className="font-semibold">{code}-denominated inventory</p>
                                    <p className="mt-1">
                                        Original: {formatOriginalAmount(row.original_amount, code, 2)}
                                    </p>
                                    <p>Historical AFN cost: {formatOriginalAmount(row.historical_afn, 'AFN', 2)}</p>
                                    <p>
                                        Weighted FX:{' '}
                                        {row.weighted_average_fx_to_afn != null
                                            ? `${Number(row.weighted_average_fx_to_afn).toFixed(2)} (${row.kind})`
                                            : 'Unavailable'}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {row.units} units · {row.batches} batches
                                    </p>
                                </div>
                            );
                        })}
                    </div>

                    {exchangeValuation.note && (
                        <p className="text-muted-foreground mt-3 text-xs">{exchangeValuation.note}</p>
                    )}
                </div>
            )}

            {(limitations.length > 0 || legacy) && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200">
                    <p className="font-semibold">Data limitations</p>
                    {legacy?.note && <p className="mt-1">{legacy.note}</p>}
                    {legacy && (
                        <p className="mt-1">
                            Legacy profit AFN without currency: {legacy.profit_afn_without_currency ?? 0} · Legacy
                            withdrawals AFN without currency: {legacy.withdrawals_afn_without_currency ?? 0} · Purchases
                            without currency: {legacy.purchases_without_currency ?? 0}
                        </p>
                    )}
                    {limitations.length > 0 && (
                        <ul className="mt-2 list-disc space-y-1 ps-4">
                            {limitations.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
