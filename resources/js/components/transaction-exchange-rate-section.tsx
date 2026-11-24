import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useExchange } from '@/hooks/useExchange';
import { useToast } from '@/hooks/use-toast';
import { router } from '@inertiajs/react';
import { ArrowRightLeft, Loader2, Lock, RefreshCw, Save, Unlock } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/** @deprecated Prefer useExchange(); kept for pages that still receive fxBoard props. */
export type FxBoardSnapshot = {
    usd_to_afn_rate?: string | number | null;
    aed_to_afn_rate?: string | number | null;
    usd_to_aed_rate?: string | number | null;
    rate_effective_at?: string | null;
    rate_source?: string | null;
};

/** @deprecated Prefer useExchange(); kept for pages that still receive fxBoard props. */
export type FxBoardContext = {
    currencies?: {
        afn?: { id?: number; code: string; name?: string; symbol: string | null } | null;
        usd?: { id?: number; code: string; name?: string; symbol: string | null } | null;
        aed?: { id?: number; code: string; name?: string; symbol: string | null } | null;
    };
    board?: FxBoardSnapshot | null;
    board_kind?: string | null;
    history?: Array<{
        id: number;
        from_currency_id: number;
        to_currency_id: number;
        rate: string;
        effective_at: string | null;
        source: string | null;
    }>;
    policy?: string;
};

export interface EditedRates {
    usd_to_afn: number;
    aed_to_afn: number;
    usd_to_aed: number;
    is_edited: boolean;
    rate_source: string;
}

interface Props {
    context?: FxBoardContext;
    /** When false, rates stay read-only (default for non-sale pages). */
    editable?: boolean;
    /** Fires whenever the effective rates change. */
    onChange?: (rates: EditedRates) => void;
    /** Optional seed rates (e.g. restoring a draft). */
    initialRates?: { usd_to_afn: number; aed_to_afn: number; usd_to_aed: number };
    /**
     * When set, "Save rates" POSTs USD→AFN / AED→AFN to this endpoint
     * (writes into exchange_rates). Defaults to /sales/exchange-board.
     */
    saveUrl?: string;
}

const fmt = (n: number | null | undefined, digits = 2) =>
    n != null && Number.isFinite(Number(n))
        ? new Intl.NumberFormat('en-US', {
              minimumFractionDigits: digits,
              maximumFractionDigits: digits,
          }).format(Number(n))
        : '—';

const toNum = (v: string | number | null | undefined): number => {
    const n = Number(v);
    return Number.isFinite(n) && n > 0 ? n : 0;
};

/** USD→AED = usd_to_afn ÷ aed_to_afn (never independently authoritative). */
const deriveUsdToAed = (usdToAfn: number, aedToAfn: number): number =>
    usdToAfn > 0 && aedToAfn > 0 ? usdToAfn / aedToAfn : 0;

export function TransactionExchangeRateSection({
    context,
    editable = false,
    onChange,
    initialRates,
    saveUrl = '/sales/exchange-board',
}: Props) {
    const exchange = useExchange();
    const { toast } = useToast();

    const boardUsd = toNum(initialRates?.usd_to_afn ?? exchange.usdToAfn ?? context?.board?.usd_to_afn_rate);
    const boardAed = toNum(initialRates?.aed_to_afn ?? exchange.aedToAfn ?? context?.board?.aed_to_afn_rate);
    const boardUsdAed = toNum(
        initialRates?.usd_to_aed ??
            exchange.usdToAed ??
            context?.board?.usd_to_aed_rate ??
            deriveUsdToAed(boardUsd, boardAed),
    );

    const boardSource = context?.board?.rate_source || exchange.source || 'exchange_rates';
    const boardEffective = context?.board?.rate_effective_at || exchange.effectiveAt;

    const [editing, setEditing] = useState(false);
    const [saving, setSaving] = useState(false);
    const [usdToAfn, setUsdToAfn] = useState<string>('');
    const [aedToAfn, setAedToAfn] = useState<string>('');
    const [isEdited, setIsEdited] = useState(false);

    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;

    const parsedUsd = parseFloat(usdToAfn) || 0;
    const parsedAed = parseFloat(aedToAfn) || 0;
    const derivedUsdAed = deriveUsdToAed(parsedUsd, parsedAed);

    // Seed / re-seed from board when board changes and user has not overridden.
    useEffect(() => {
        if (isEdited) return;
        setUsdToAfn(boardUsd > 0 ? String(boardUsd) : '');
        setAedToAfn(boardAed > 0 ? String(boardAed) : '');
    }, [boardUsd, boardAed, isEdited]);

    // Notify parent of effective rates (USD→AED always derived).
    useEffect(() => {
        const u2a = parsedUsd;
        const a2a = parsedAed;
        const u2d = derivedUsdAed;

        const edited =
            (boardUsd > 0 && u2a > 0 && Math.abs(u2a - boardUsd) > 1e-8) ||
            (boardAed > 0 && a2a > 0 && Math.abs(a2a - boardAed) > 1e-8) ||
            (boardUsd <= 0 && u2a > 0) ||
            (boardAed <= 0 && a2a > 0);

        setIsEdited(edited);

        onChangeRef.current?.({
            usd_to_afn: u2a,
            aed_to_afn: a2a,
            usd_to_aed: u2d,
            is_edited: edited,
            rate_source: edited ? 'sale_board' : boardSource,
        });
    }, [parsedUsd, parsedAed, derivedUsdAed, boardUsd, boardAed, boardSource]);

    const resetToBoard = () => {
        setUsdToAfn(boardUsd > 0 ? String(boardUsd) : '');
        setAedToAfn(boardAed > 0 ? String(boardAed) : '');
        setIsEdited(false);
        setEditing(false);
    };

    const saveRates = () => {
        if (!(parsedUsd > 0) || !(parsedAed > 0)) {
            toast({
                title: 'Invalid rates',
                description: 'Both USD→AFN and AED→AFN must be greater than zero.',
                variant: 'destructive',
            });
            return;
        }

        setSaving(true);
        router.post(
            saveUrl,
            {
                usd_to_afn: parsedUsd,
                aed_to_afn: parsedAed,
                source: 'sale_board',
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsEdited(false);
                    setEditing(false);
                    toast({
                        title: 'Rates saved',
                        description: 'New rates were written to exchange_rates and apply to new sales.',
                    });
                    // Refresh shared Inertia `exchange` + page fxBoard from DB.
                    router.reload({ preserveScroll: true });
                },
                onError: (errors) => {
                    const message =
                        errors.usd_to_afn ||
                        errors.aed_to_afn ||
                        errors.exchange_rate ||
                        Object.values(errors)[0] ||
                        'Could not save exchange rates.';
                    toast({
                        title: 'Save failed',
                        description: String(message),
                        variant: 'destructive',
                    });
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const displayUsd = toNum(usdToAfn) || boardUsd || null;
    const displayAed = toNum(aedToAfn) || boardAed || null;
    const displayUsdAed = deriveUsdToAed(displayUsd || 0, displayAed || 0) || boardUsdAed || null;

    return (
        <div className="rounded-lg border-2 border-blue-300 bg-blue-50/50 p-4">
            <div className="mb-3 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <ArrowRightLeft className="h-5 w-5 text-blue-600" />
                    <span className="font-semibold">Exchange Rates</span>
                    {isEdited ? (
                        <Badge variant="outline" className="border-amber-400 bg-amber-50 text-amber-700">
                            Unsaved changes
                        </Badge>
                    ) : (
                        <Badge variant="outline" className="border-green-400 bg-green-50 text-green-700">
                            Live board
                        </Badge>
                    )}
                </div>
                {editable && (
                    <div className="flex gap-2">
                        {!editing ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => setEditing(true)}
                                className="gap-1"
                            >
                                <Unlock className="h-3 w-3" /> Edit rates
                            </Button>
                        ) : (
                            <>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={resetToBoard}
                                    disabled={saving}
                                    className="gap-1 text-red-600"
                                >
                                    <RefreshCw className="h-3 w-3" /> Reset
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="default"
                                    onClick={saveRates}
                                    disabled={saving || !(parsedUsd > 0) || !(parsedAed > 0)}
                                    className="gap-1"
                                >
                                    {saving ? (
                                        <Loader2 className="h-3 w-3 animate-spin" />
                                    ) : (
                                        <Save className="h-3 w-3" />
                                    )}
                                    Save rates
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setEditing(false)}
                                    disabled={saving}
                                    className="gap-1"
                                >
                                    <Lock className="h-3 w-3" /> Lock
                                </Button>
                            </>
                        )}
                    </div>
                )}
            </div>

            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div>
                    <Label className="text-xs">USD → AFN</Label>
                    {editable && editing ? (
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={usdToAfn}
                            onChange={(e) => setUsdToAfn(e.target.value)}
                            className="font-mono"
                        />
                    ) : (
                        <div className="py-1.5 font-mono text-lg font-semibold">{fmt(displayUsd)}</div>
                    )}
                </div>

                <div>
                    <Label className="text-xs">AED → AFN</Label>
                    {editable && editing ? (
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={aedToAfn}
                            onChange={(e) => setAedToAfn(e.target.value)}
                            className="font-mono"
                        />
                    ) : (
                        <div className="py-1.5 font-mono text-lg font-semibold">{fmt(displayAed)}</div>
                    )}
                </div>

                <div>
                    <Label className="text-xs">USD → AED (derived)</Label>
                    <div className="py-1.5 font-mono text-lg font-semibold">{fmt(displayUsdAed, 4)}</div>
                    <p className="text-muted-foreground text-[11px]">usd_to_afn ÷ aed_to_afn</p>
                </div>
            </div>

            {(boardEffective || boardSource) && (
                <p className="text-muted-foreground mt-3 text-xs">
                    {boardEffective ? `Board effective: ${boardEffective}` : null}
                    {boardEffective && boardSource ? ' · ' : null}
                    {boardSource ? `Source: ${boardSource}` : null}
                    {isEdited
                        ? ' · Save rates to write into exchange_rates (applies to new sales only)'
                        : null}
                </p>
            )}

            {(!boardUsd || !boardAed) && !isEdited && (
                <p className="mt-2 text-xs text-amber-800">
                    Board rates are missing. Enter USD→AFN and AED→AFN, then Save rates to post them to
                    exchange_rates.
                </p>
            )}
        </div>
    );
}

export function threeCurrencyEquivalents(
    amount: number,
    currencyCode: string,
    usdToAfn: number,
    aedToAfn: number,
): { afn: number; usd: number; aed: number } {
    if (!amount || amount < 0) return { afn: 0, usd: 0, aed: 0 };
    const code = (currencyCode || 'AFN').toUpperCase();
    if (!usdToAfn || !aedToAfn || usdToAfn <= 0 || aedToAfn <= 0) {
        return code === 'AFN' ? { afn: amount, usd: 0, aed: 0 } : { afn: 0, usd: 0, aed: 0 };
    }
    let afn = amount;
    if (code === 'USD') afn = amount * usdToAfn;
    if (code === 'AED') afn = amount * aedToAfn;
    const usd = code === 'USD' ? amount : afn / usdToAfn;
    const aed = code === 'AED' ? amount : afn / aedToAfn;
    return {
        afn: Math.round(afn * 100) / 100,
        usd: Math.round(usd * 10000) / 10000,
        aed: Math.round(aed * 10000) / 10000,
    };
}
