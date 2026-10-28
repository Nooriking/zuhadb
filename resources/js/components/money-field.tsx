import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { convertToDefault, currencyLabel, findCurrency, formatAmount, type CatalogCurrency } from '@/lib/currencies';

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
};

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
}: Props) {
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
                <Select value={currencyId} onValueChange={onCurrencyChange} disabled={disabled || currencies.length === 0}>
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
            {error ? <p className="text-destructive text-xs">{error}</p> : null}
            <ConversionHint amount={amount} currencyId={currencyId} currencies={currencies} />
        </div>
    );
}

function ConversionHint({
    amount,
    currencyId,
    currencies,
}: {
    amount: string;
    currencyId: string;
    currencies: CatalogCurrency[];
}) {
    const value = Number(amount);
    if (!Number.isFinite(value) || value <= 0) {
        return null;
    }

    const from = findCurrency(currencies, currencyId);
    const afn = currencies.find((row) => row.code === 'AFN');
    const usd = currencies.find((row) => row.code === 'USD');
    const bits: string[] = [];

    if (from && afn && from.id !== afn.id) {
        const converted = convertToDefault(value, from, afn);
        bits.push(converted === null ? 'AFN conversion unavailable' : `≈ ${formatAmount(converted, afn)} (catalog)`);
    }
    if (from && usd && from.id !== usd.id) {
        const converted = convertToDefault(value, from, usd);
        bits.push(converted === null ? 'USD conversion unavailable' : `≈ ${formatAmount(converted, usd)} (catalog)`);
    }

    if (bits.length === 0) {
        return from ? <p className="text-muted-foreground text-xs">Entered in {from.code}</p> : null;
    }

    return <p className="text-muted-foreground text-xs">{bits.join(' · ')}. Catalog conversions are not extra revenue.</p>;
}
