import { useExchange } from '@/hooks/useExchange';

interface Props {
    /** مبلغ اصلی */
    amount: number | null | undefined;
    /** ارز اصلی مبلغ */
    currency: 'AFN' | 'USD' | 'AED' | string | null;
    /** اندازه فونت مبلغ اصلی */
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl';
    /** نمایش معادل‌ها */
    showEquivalents?: boolean;
    /** نمایش به‌صورت افقی (یک خط) */
    inline?: boolean;
    /** برچسب بالا */
    label?: string;
    /** رنگ */
    variant?: 'default' | 'profit' | 'loss' | 'muted';
}

const fmt = (n: number, digits = 2) =>
    new Intl.NumberFormat('en-US', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(n);

/**
 * تبدیل مبلغ به هر سه ارز
 * اگر ارز اصلی AFN است → AFN, USD, AED
 * اگر USD است → USD, AFN, AED
 * اگر AED است → AED, AFN, USD
 */
export function useTripleConversion(amount: number, currency: string | null) {
    const { usdToAfn, aedToAfn } = useExchange();

    const usdRate = usdToAfn || 0;
    const aedRate = aedToAfn || 0;

    let afn = 0;
    let usd = 0;
    let aed = 0;

    const cur = (currency || 'AFN').toUpperCase();

    if (cur === 'AFN') {
        afn = amount;
        usd = usdRate > 0 ? amount / usdRate : 0;
        aed = aedRate > 0 ? amount / aedRate : 0;
    } else if (cur === 'USD') {
        usd = amount;
        afn = amount * usdRate;
        aed = aedRate > 0 ? afn / aedRate : 0;
    } else if (cur === 'AED') {
        aed = amount;
        afn = amount * aedRate;
        usd = usdRate > 0 ? afn / usdRate : 0;
    } else {
        // فرض AFN
        afn = amount;
        usd = usdRate > 0 ? amount / usdRate : 0;
        aed = aedRate > 0 ? amount / aedRate : 0;
    }

    return { afn, usd, aed, usdRate, aedRate };
}

export function MoneyTriple({
    amount,
    currency,
    size = 'md',
    showEquivalents = true,
    inline = false,
    label,
    variant = 'default',
}: Props) {
    const value = amount == null ? 0 : Number(amount);
    const cur = (currency || 'AFN').toUpperCase();
    const { afn, usd, aed } = useTripleConversion(Number.isFinite(value) ? value : 0, cur);

    if (amount == null || !Number.isFinite(Number(amount))) {
        return <span className="text-muted-foreground">—</span>;
    }

    const symbol = cur === 'USD' ? '$' : cur === 'AED' ? 'د.إ' : '؋';
    const mainColor =
        variant === 'profit'
            ? 'text-green-600'
            : variant === 'loss'
              ? 'text-red-600'
              : variant === 'muted'
                ? 'text-muted-foreground'
                : 'text-foreground';

    const mainSize = {
        xs: 'text-xs',
        sm: 'text-sm',
        md: 'text-base font-semibold',
        lg: 'text-xl font-bold',
        xl: 'text-2xl font-bold',
        '2xl': 'text-3xl font-bold',
        '3xl': 'text-4xl font-bold',
    }[size];

    if (inline) {
        return (
            <div className="flex flex-wrap items-center gap-2 font-mono text-sm">
                <span className={`font-bold ${mainColor}`}>
                    {symbol} {fmt(value)} {cur}
                </span>
                {showEquivalents && (
                    <>
                        <span className="text-muted-foreground/40">=</span>
                        <span className="text-xs text-muted-foreground">؋ {fmt(afn)}</span>
                        <span className="text-muted-foreground/40">·</span>
                        <span className="text-xs text-muted-foreground">${fmt(usd, 2)}</span>
                        <span className="text-muted-foreground/40">·</span>
                        <span className="text-xs text-muted-foreground">د.إ {fmt(aed, 2)}</span>
                    </>
                )}
            </div>
        );
    }

    return (
        <div>
            {label && <div className="mb-1 text-xs text-muted-foreground">{label}</div>}
            <div className={`font-mono ${mainSize} ${mainColor}`}>
                {symbol} {fmt(value)} <span className="text-sm opacity-70">{cur}</span>
            </div>
            {showEquivalents && (
                <div className="mt-1 grid grid-cols-1 gap-x-3 gap-y-0.5 font-mono text-xs text-muted-foreground sm:grid-cols-3">
                    <span className={cur === 'AFN' ? 'font-semibold text-foreground' : ''}>
                        ؋ {fmt(afn)} AFN
                    </span>
                    <span className={cur === 'USD' ? 'font-semibold text-foreground' : ''}>
                        $ {fmt(usd, 2)} USD
                    </span>
                    <span className={cur === 'AED' ? 'font-semibold text-foreground' : ''}>
                        د.إ {fmt(aed, 2)} AED
                    </span>
                </div>
            )}
        </div>
    );
}

/**
 * نسخه‌ی "مجموع معادل‌ها" — برای پایین جداول و کارت‌های خلاصه
 */
export function TripleTotal({
    afn,
    label = 'Total equivalents',
    size = 'lg',
}: {
    afn: number | null | undefined;
    label?: string;
    size?: 'sm' | 'md' | 'lg' | 'xl';
}) {
    const value = afn == null ? 0 : Number(afn);
    const { usd, aed } = useTripleConversion(Number.isFinite(value) ? value : 0, 'AFN');

    if (afn == null || !Number.isFinite(Number(afn))) return null;

    const mainSize = {
        sm: 'text-sm',
        md: 'text-lg font-bold',
        lg: 'text-xl font-bold',
        xl: 'text-2xl font-bold',
    }[size];

    return (
        <div className="rounded-lg border-2 border-primary/30 bg-primary/5 p-3">
            <div className="mb-1 text-xs text-muted-foreground">{label}</div>
            <div className={`font-mono ${mainSize} flex flex-wrap items-baseline gap-3`}>
                <span className="text-primary">
                    ؋ {fmt(Number(afn))} <span className="text-sm opacity-70">AFN</span>
                </span>
                <span className="text-muted-foreground/50">=</span>
                <span className="text-blue-600">
                    $ {fmt(usd, 2)} <span className="text-sm opacity-70">USD</span>
                </span>
                <span className="text-muted-foreground/50">=</span>
                <span className="text-green-600">
                    د.إ {fmt(aed, 2)} <span className="text-sm opacity-70">AED</span>
                </span>
            </div>
        </div>
    );
}
