import { cn } from '@/lib/utils';

export type ExpenseBearer = 'shop' | 'customer';

const OPTIONS: { value: ExpenseBearer; label: string; hint: string }[] = [
    { value: 'shop', label: 'Shop', hint: 'Recorded as office expense' },
    { value: 'customer', label: 'Customer', hint: 'Deducted from customer amount' },
];

interface Props {
    value: ExpenseBearer;
    onChange: (value: ExpenseBearer) => void;
    disabled?: boolean;
}

export function ExpenseBearerToggle({ value, onChange, disabled = false }: Props) {
    return (
        <div className="inline-flex overflow-hidden rounded-md border text-xs" role="radiogroup">
            {OPTIONS.map((opt) => (
                <button
                    key={opt.value}
                    type="button"
                    role="radio"
                    aria-checked={value === opt.value}
                    title={opt.hint}
                    disabled={disabled}
                    onClick={() => onChange(opt.value)}
                    className={cn(
                        'px-2 py-1 whitespace-nowrap transition disabled:cursor-not-allowed disabled:opacity-50',
                        value === opt.value
                            ? opt.value === 'shop'
                                ? 'bg-blue-600 text-white'
                                : 'bg-orange-600 text-white'
                            : 'bg-background hover:bg-muted',
                    )}
                >
                    {opt.label}
                </button>
            ))}
        </div>
    );
}
