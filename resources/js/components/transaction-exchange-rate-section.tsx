import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useMemo } from 'react';

export type FxBoardSnapshot = {
    usd_to_afn_rate?: string | null;
    aed_to_afn_rate?: string | null;
    usd_to_aed_rate?: string | null;
    rate_effective_at?: string | null;
    rate_source?: string | null;
};

export type FxBoardContext = {
    currencies?: {
        afn?: { id?: number; code: string; name: string; symbol: string | null } | null;
        usd?: { id?: number; code: string; name: string; symbol: string | null } | null;
        aed?: { id?: number; code: string; name: string; symbol: string | null } | null;
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

type Props = {
    usdToAfn: string;
    aedToAfn: string;
    onChange: (field: 'usd_to_afn_rate' | 'aed_to_afn_rate', value: string) => void;
    context?: FxBoardContext;
    canManageRates?: boolean;
    errors?: Record<string, string>;
};

function deriveUsdToAed(usdToAfn: string, aedToAfn: string): string {
    const usd = parseFloat(usdToAfn);
    const aed = parseFloat(aedToAfn);
    if (!usd || !aed || usd <= 0 || aed <= 0) return '';
    return (usd / aed).toFixed(8).replace(/\.?0+$/, (m) => (m.includes('.') ? m.replace(/0+$/, '').replace(/\.$/, '') : m));
}

export function TransactionExchangeRateSection({
    usdToAfn,
    aedToAfn,
    onChange,
    context,
    canManageRates = false,
    errors = {},
}: Props) {
    const usdToAed = useMemo(() => deriveUsdToAed(usdToAfn, aedToAfn), [usdToAfn, aedToAfn]);
    const usdCode = context?.currencies?.usd?.code || 'USD';
    const aedCode = context?.currencies?.aed?.code || 'AED';
    const afnCode = context?.currencies?.afn?.code || 'AFN';

    const historyOptions = useMemo(() => {
        const rows = context?.history || [];
        const usdId = context?.currencies?.usd?.id;
        const aedId = context?.currencies?.aed?.id;
        return rows.map((row) => {
            const labelCode = row.from_currency_id === usdId ? usdCode : row.from_currency_id === aedId ? aedCode : 'FX';
            return {
                ...row,
                label: `${labelCode}→${afnCode} ${row.rate} @ ${row.effective_at || '—'} (${row.source || 'rate'})`,
            };
        });
    }, [context, usdCode, aedCode, afnCode]);

    const applyHistory = (id: string) => {
        if (id === 'current') return;
        const row = (context?.history || []).find((h) => String(h.id) === id);
        if (!row) return;
        const usdId = context?.currencies?.usd?.id;
        const aedId = context?.currencies?.aed?.id;
        if (row.from_currency_id === usdId) {
            onChange('usd_to_afn_rate', row.rate);
        } else if (row.from_currency_id === aedId) {
            onChange('aed_to_afn_rate', row.rate);
        }
    };

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <div>
                <h2 className="text-sm font-semibold">Exchange rates</h2>
                <p className="text-muted-foreground text-xs">
                    {context?.policy ||
                        `Base rates: AFN per 1 ${usdCode} and AFN per 1 ${aedCode}. ${usdCode}/${aedCode} is derived.`}
                </p>
                {context?.board?.rate_effective_at ? (
                    <p className="text-muted-foreground mt-1 text-xs">
                        Active as of {context.board.rate_effective_at}
                        {context.board_kind ? ` · ${context.board_kind}` : ''}
                        {context.board.rate_source ? ` · ${context.board.rate_source}` : ''}
                    </p>
                ) : null}
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div className="space-y-2">
                    <Label htmlFor="tx_usd_to_afn">
                        {usdCode} → {afnCode}
                    </Label>
                    <Input
                        id="tx_usd_to_afn"
                        type="number"
                        min="0.00000001"
                        step="0.00000001"
                        inputMode="decimal"
                        value={usdToAfn}
                        disabled={!canManageRates}
                        onChange={(e) => onChange('usd_to_afn_rate', e.target.value)}
                        placeholder="e.g. 70"
                        className={errors.usd_to_afn_rate ? 'border-destructive' : ''}
                    />
                    {errors.usd_to_afn_rate ? <p className="text-destructive text-xs">{errors.usd_to_afn_rate}</p> : null}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="tx_aed_to_afn">
                        {aedCode} → {afnCode}
                    </Label>
                    <Input
                        id="tx_aed_to_afn"
                        type="number"
                        min="0.00000001"
                        step="0.00000001"
                        inputMode="decimal"
                        value={aedToAfn}
                        disabled={!canManageRates}
                        onChange={(e) => onChange('aed_to_afn_rate', e.target.value)}
                        placeholder="e.g. 19"
                        className={errors.aed_to_afn_rate ? 'border-destructive' : ''}
                    />
                    {errors.aed_to_afn_rate ? <p className="text-destructive text-xs">{errors.aed_to_afn_rate}</p> : null}
                </div>
                <div className="space-y-2">
                    <Label>
                        {usdCode} → {aedCode} (derived)
                    </Label>
                    <Input value={usdToAed || '—'} readOnly tabIndex={-1} />
                    <p className="text-muted-foreground text-xs">Auto: usd_to_afn ÷ aed_to_afn</p>
                </div>
            </div>

            {historyOptions.length > 0 ? (
                <div className="space-y-2">
                    <Label>Select a historical rate</Label>
                    <Select onValueChange={applyHistory} disabled={!canManageRates}>
                        <SelectTrigger>
                            <SelectValue placeholder="Keep current board rates" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="current">Keep current board rates</SelectItem>
                            {historyOptions.map((row) => (
                                <SelectItem key={row.id} value={String(row.id)}>
                                    {row.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {!canManageRates ? (
                        <p className="text-muted-foreground text-xs">
                            Rates are locked for your role. Historical transactions keep their own snapshots.
                        </p>
                    ) : null}
                </div>
            ) : null}
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
