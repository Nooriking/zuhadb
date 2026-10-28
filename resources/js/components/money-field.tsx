import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { currencyLabel, type CatalogCurrency } from '@/lib/currencies';

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
        </div>
    );
}
