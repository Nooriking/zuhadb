import type { ChartCurrency } from '@/lib/three-currency';

type Props = {
    value: ChartCurrency;
    onChange: (currency: ChartCurrency) => void;
    disabled?: boolean;
    available?: boolean;
};

const OPTIONS: ChartCurrency[] = ['AFN', 'USD', 'AED'];

export default function ChartCurrencySelector({ value, onChange, disabled = false, available = true }: Props) {
    return (
        <div className="bg-card flex gap-1 rounded-md border p-1" role="group" aria-label="Chart currency">
            {OPTIONS.map((code) => {
                const isDisabled = disabled || (code !== 'AFN' && !available);

                return (
                    <button
                        key={code}
                        type="button"
                        disabled={isDisabled}
                        onClick={() => onChange(code)}
                        className={`rounded-md px-2.5 py-1 text-xs font-medium transition-colors ${
                            value === code
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground disabled:opacity-40'
                        }`}
                    >
                        {code}
                    </button>
                );
            })}
        </div>
    );
}
