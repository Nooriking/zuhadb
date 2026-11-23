import { useExchange } from '@/hooks/useExchange';
import { Coins } from 'lucide-react';
import { useSyncExternalStore } from 'react';

export type CurrencyCode = 'AFN' | 'USD' | 'AED';
/** Dashboard / KPI view: single currency or all three columns */
export type CurrencyView = 'ALL' | CurrencyCode;

const STORAGE_KEY = 'preferred_currency';
const VIEW_STORAGE_KEY = 'dashboard_currency_view';
const VALID: CurrencyCode[] = ['AFN', 'USD', 'AED'];
const VALID_VIEW: CurrencyView[] = ['ALL', 'AFN', 'USD', 'AED'];

function readStored(): CurrencyCode {
    if (typeof window === 'undefined') return 'AFN';
    const stored = localStorage.getItem(STORAGE_KEY) as CurrencyCode | null;
    return stored && VALID.includes(stored) ? stored : 'AFN';
}

function readStoredView(): CurrencyView {
    if (typeof window === 'undefined') return 'ALL';
    const stored = localStorage.getItem(VIEW_STORAGE_KEY) as CurrencyView | null;
    return stored && VALID_VIEW.includes(stored) ? stored : 'ALL';
}

let current: CurrencyCode = typeof window !== 'undefined' ? readStored() : 'AFN';
const listeners = new Set<() => void>();

let currentView: CurrencyView = typeof window !== 'undefined' ? readStoredView() : 'ALL';
const viewListeners = new Set<() => void>();

function subscribe(listener: () => void) {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

function getSnapshot(): CurrencyCode {
    return current;
}

function getServerSnapshot(): CurrencyCode {
    return 'AFN';
}

function setPreferredCurrency(c: CurrencyCode) {
    if (!VALID.includes(c) || c === current) return;
    current = c;
    if (typeof window !== 'undefined') {
        localStorage.setItem(STORAGE_KEY, c);
    }
    listeners.forEach((l) => l());
}

function subscribeView(listener: () => void) {
    viewListeners.add(listener);
    return () => viewListeners.delete(listener);
}

function getViewSnapshot(): CurrencyView {
    return currentView;
}

function getViewServerSnapshot(): CurrencyView {
    return 'ALL';
}

function setCurrencyView(v: CurrencyView) {
    if (!VALID_VIEW.includes(v) || v === currentView) return;
    currentView = v;
    if (typeof window !== 'undefined') {
        localStorage.setItem(VIEW_STORAGE_KEY, v);
    }
    viewListeners.forEach((l) => l());
    if (v !== 'ALL') {
        setPreferredCurrency(v);
    }
}

export function usePreferredCurrency(): [CurrencyCode, (c: CurrencyCode) => void] {
    const currency = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
    return [currency, setPreferredCurrency];
}

/** Dashboard currency view including All (3-column) mode */
export function useCurrencyView(): [CurrencyView, (v: CurrencyView) => void] {
    const view = useSyncExternalStore(subscribeView, getViewSnapshot, getViewServerSnapshot);
    return [view, setCurrencyView];
}

/**
 * Convert an AFN amount to the selected currency
 */
export function useCurrencyConverter() {
    const [currency] = usePreferredCurrency();
    const { usdToAfn, aedToAfn } = useExchange();

    const convert = (afn: number | null | undefined): number => {
        if (afn == null || !Number.isFinite(Number(afn))) return 0;
        const n = Number(afn);
        if (currency === 'AFN') return n;
        if (currency === 'USD' && usdToAfn && usdToAfn > 0) return n / usdToAfn;
        if (currency === 'AED' && aedToAfn && aedToAfn > 0) return n / aedToAfn;
        return n;
    };

    const symbol = currency === 'USD' ? '$' : currency === 'AED' ? 'د.إ' : '؋';

    const format = (afn: number | null | undefined, digits = 2): string => {
        const value = convert(afn);
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits,
        }).format(value);
    };

    return { currency, convert, format, symbol, usdToAfn, aedToAfn };
}

interface TabsProps {
    /** If true, also show today's rates next to the tabs */
    showRates?: boolean;
    /** Optional: sync with parent state (single currency, no All) */
    value?: CurrencyCode;
    onChange?: (c: CurrencyCode) => void;
    /** Include All tab (3-column KPI mode). Uses CurrencyView. */
    includeAll?: boolean;
    view?: CurrencyView;
    onViewChange?: (v: CurrencyView) => void;
    /** Compact display for mobile */
    compact?: boolean;
}

export function CurrencyTabs({
    showRates = true,
    value,
    onChange,
    includeAll = false,
    view,
    onViewChange,
    compact = false,
}: TabsProps) {
    const [internal, setInternal] = usePreferredCurrency();
    const [internalView, setInternalView] = useCurrencyView();
    const { usdToAfn, aedToAfn } = useExchange();

    const currentTab: CurrencyView = includeAll
        ? (view ?? internalView)
        : (value ?? internal);

    const setCurrent = (next: CurrencyView) => {
        if (includeAll) {
            onViewChange?.(next);
            setInternalView(next);
            return;
        }
        if (next === 'ALL') return;
        onChange?.(next);
        setInternal(next);
    };

    const tabs: { code: CurrencyView; label: string; symbol: string }[] = [
        ...(includeAll ? [{ code: 'ALL' as const, label: 'All', symbol: '⇄' }] : []),
        { code: 'AFN', label: 'AFN', symbol: '؋' },
        { code: 'USD', label: 'USD', symbol: '$' },
        { code: 'AED', label: 'AED', symbol: 'د.إ' },
    ];

    return (
        <div className="flex flex-wrap items-center gap-3 rounded-lg border bg-card p-2">
            <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                <Coins className="h-4 w-4" />
                <span className="hidden sm:inline">Currency</span>
            </div>
            <div className="flex gap-1">
                {tabs.map((tab) => (
                    <button
                        key={tab.code}
                        type="button"
                        onClick={() => setCurrent(tab.code)}
                        className={`rounded-md px-3 py-1.5 text-sm font-semibold transition ${
                            currentTab === tab.code
                                ? 'bg-primary text-primary-foreground shadow'
                                : 'text-muted-foreground hover:bg-muted'
                        }`}
                    >
                        <span className="font-mono">{tab.symbol}</span>
                        <span className="ml-1">{tab.label}</span>
                    </button>
                ))}
            </div>
            {showRates && !compact && (
                <div className="ml-auto flex flex-wrap gap-3 font-mono text-xs text-muted-foreground">
                    <span>1 USD = {usdToAfn ? usdToAfn.toLocaleString() : '—'} AFN</span>
                    <span>1 AED = {aedToAfn ? aedToAfn.toLocaleString() : '—'} AFN</span>
                </div>
            )}
        </div>
    );
}

/**
 * Replacement for AfnDisplay — respects the selected currency
 */
export function Money({
    afn,
    digits = 2,
    size = 'md',
    showAll = false,
}: {
    afn: number | null | undefined;
    digits?: number;
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
    /** If true, show equivalents in all 3 currencies under the amount */
    showAll?: boolean;
}) {
    const { currency, format, symbol, usdToAfn, aedToAfn } = useCurrencyConverter();

    const sizeClass = {
        xs: 'text-xs',
        sm: 'text-sm',
        md: 'text-base font-semibold',
        lg: 'text-xl font-bold',
        xl: 'text-3xl font-bold',
    }[size];

    return (
        <div>
            <div className={`font-mono ${sizeClass}`}>
                {symbol} {format(afn, digits)} <span className="text-xs opacity-70">{currency}</span>
            </div>
            {showAll && afn != null && (
                <div className="font-mono text-[10px] text-muted-foreground">
                    AFN {(afn || 0).toLocaleString()} · USD{' '}
                    {usdToAfn ? (afn / usdToAfn).toFixed(2) : '—'} · AED{' '}
                    {aedToAfn ? (afn / aedToAfn).toFixed(2) : '—'}
                </div>
            )}
        </div>
    );
}
