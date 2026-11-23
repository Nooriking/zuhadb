import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useExchange } from '@/hooks/useExchange';
import { catalogEquivalents, resolveCatalogFxRates } from '@/lib/currency';
import { currencyLabel, findCurrency, formatAmount, type CatalogCurrency } from '@/lib/currencies';
import { estimateToAfn } from '@/lib/product-estimate';

type Props = {
    label: string;
    required?: boolean;
    amount: string;
    currencyId: string;
    currencies: CatalogCurrency[];
    onAmountChange: (value: string) => void;
    onCurrencyChange: (value: string) => void;
    error?: string;
    disabled?: boolean;
    /** When true, currency selector is locked (e.g. profit follows purchase currency). */
    currencyLocked?: boolean;
    /** Optional pair rates for estimate hints; prefer board rates. */
    usdToAfn?: number;
    usdToAed?: number;
    boardUsdToAfn?: number;
    boardAedToAfn?: number;
    /** Optional quantity — when set, shows total profit in original + AFN/USD/AED. */
    quantity?: number;
    showThreeCurrency?: boolean;
    /** Set false when the page renders its own ExchangePreviewPanel. */
    showHint?: boolean;
};

function formatPreview(n: number): string {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(n);
}

export function MoneyField({
    label,
    required,
    amount,
    currencyId,
    currencies,
    onAmountChange,
    onCurrencyChange,
    error,
    disabled,
    currencyLocked = false,
    usdToAfn = 0,
    usdToAed = 0,
    boardUsdToAfn = 0,
    boardAedToAfn = 0,
    quantity,
    showThreeCurrency = true,
    showHint = true,
}: Props) {
    const exchange = useExchange();
    const boardUsd = boardUsdToAfn || exchange.usdToAfn || 0;
    const boardAed = boardAedToAfn || exchange.aedToAfn || 0;

    return (
        <div className="space-y-2">
            <Label>
                {label}
                {required ? <span className="text-destructive"> *</span> : null}
            </Label>
            <div className="flex flex-col gap-2 sm:flex-row">
                <Input
                    type="number"
                    min="0"
                    step="0.01"
                    value={amount}
                    disabled={disabled}
                    onChange={(e) => onAmountChange(e.target.value)}
                    className={`min-w-0 flex-1 font-mono ${error ? 'border-destructive' : ''}`}
                />
                <Select
                    value={currencyId}
                    onValueChange={onCurrencyChange}
                    disabled={disabled || currencyLocked || currencies.length === 0}
                >
                    <SelectTrigger className="w-full sm:w-52">
                        <SelectValue placeholder="Currency" />
                    </SelectTrigger>
                    <SelectContent>
                        {currencies.map((currency) => (
                            <SelectItem key={currency.id} value={String(currency.id)}>
                                {currencyLabel(currency)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            {currencyLocked ? (
                <p className="text-muted-foreground text-xs">Currency follows the purchase currency.</p>
            ) : null}
            {error ? <p className="text-destructive whitespace-pre-wrap text-xs">{error}</p> : null}
            {!showHint ? null : showThreeCurrency ? (
                <ThreeCurrencyHint
                    amount={amount}
                    currencyId={currencyId}
                    currencies={currencies}
                    usdToAfn={usdToAfn}
                    usdToAed={usdToAed}
                    boardUsdToAfn={boardUsd}
                    boardAedToAfn={boardAed}
                    quantity={quantity}
                />
            ) : (
                <ConversionHint
                    amount={amount}
                    currencyId={currencyId}
                    currencies={currencies}
                    usdToAfn={usdToAfn || boardUsd}
                    usdToAed={usdToAed || exchange.usdToAed || 0}
                />
            )}
        </div>
    );
}

function ThreeCurrencyHint({
    amount,
    currencyId,
    currencies,
    usdToAfn,
    usdToAed,
    boardUsdToAfn,
    boardAedToAfn,
    quantity,
}: {
    amount: string;
    currencyId: string;
    currencies: CatalogCurrency[];
    usdToAfn: number;
    usdToAed: number;
    boardUsdToAfn: number;
    boardAedToAfn: number;
    quantity?: number;
}) {
    const value = Number(amount);
    if (!Number.isFinite(value) || value <= 0) {
        return null;
    }

    const from = findCurrency(currencies, currencyId);
    if (!from) {
        return null;
    }

    const rates = resolveCatalogFxRates({
        usdToAfn,
        usdToAed,
        boardUsdToAfn,
        boardAedToAfn,
    });

    if (!rates) {
        return (
            <p className="text-muted-foreground text-xs">
                Central Exchange Board rates unavailable — configure USD→AFN and AED→AFN under Exchange Rates to preview AFN / USD / AED.
            </p>
        );
    }

    const eq = catalogEquivalents(value, from.code, rates);
    if (!eq) {
        return null;
    }

    const qty = quantity && quantity > 0 ? quantity : null;
    const totalOriginal = qty ? value * qty : null;

    return (
        <div className="bg-muted/40 space-y-1 rounded-md px-3 py-2 text-xs">
            <p className="text-muted-foreground font-medium">
                Per-unit equivalents (central Exchange Board)
            </p>
            <div className="grid grid-cols-3 gap-2 font-mono">
                {(
                    [
                        { code: 'AFN', value: eq.afn },
                        { code: 'USD', value: eq.usd },
                        { code: 'AED', value: eq.aed },
                    ] as const
                ).map((row) => {
                    const isOriginal = row.code === from.code.toUpperCase();
                    return (
                        <div
                            key={row.code}
                            className={isOriginal ? 'text-foreground font-semibold' : 'text-muted-foreground'}
                        >
                            <span className="block text-[10px] uppercase tracking-wide">
                                {row.code}
                                {isOriginal ? ' · entered' : ''}
                            </span>
                            {formatPreview(row.value)}
                        </div>
                    );
                })}
            </div>
            {qty && totalOriginal !== null ? (
                <p className="text-muted-foreground font-mono text-[10px]">
                    Total × {qty}: {formatPreview(totalOriginal)} {from.code}
                    {' · '}
                    AFN {formatPreview(eq.afn * qty)}
                    {' · '}
                    USD {formatPreview(eq.usd * qty)}
                    {' · '}
                    AED {formatPreview(eq.aed * qty)}
                </p>
            ) : null}
            <p className="text-muted-foreground text-[10px]">
                Original amount and currency are preserved. AFN is authoritative for ledger persistence at save time.
            </p>
        </div>
    );
}

function ConversionHint({
    amount,
    currencyId,
    currencies,
    usdToAfn,
    usdToAed,
}: {
    amount: string;
    currencyId: string;
    currencies: CatalogCurrency[];
    usdToAfn: number;
    usdToAed: number;
}) {
    const value = Number(amount);
    if (!Number.isFinite(value) || value <= 0) {
        return null;
    }

    const from = findCurrency(currencies, currencyId);
    const ledger = currencies.find((row) => row.is_default) ?? currencies.find((row) => row.code === 'AFN');
    if (!from || !ledger) {
        return null;
    }

    if (from.id === ledger.id || from.code === ledger.code) {
        return <p className="text-muted-foreground text-xs">Entered in {from.code} (ledger)</p>;
    }

    const converted = estimateToAfn(value, from, ledger, usdToAfn, usdToAed);
    if (converted === null) {
        return (
            <p className="text-muted-foreground text-xs">
                Configure Central Exchange Board rates to estimate {ledger.code}.
            </p>
        );
    }

    return (
        <p className="text-muted-foreground text-xs">
            ≈ {formatAmount(converted, ledger)} ledger estimate. Not a purchase/sale FX snapshot.
        </p>
    );
}
