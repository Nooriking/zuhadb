import { useExchange } from '@/hooks/useExchange';
import { AlertTriangle } from 'lucide-react';
import { type ReactNode } from 'react';

export const formatPreviewAmount = (value: number | null | undefined, digits = 2) =>
    value == null || !Number.isFinite(value)
        ? '—'
        : new Intl.NumberFormat('en-US', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(value);

const formatRateValue = (value: number | null) => (value == null ? '—' : String(Number(value.toFixed(8))));

const formatEffective = (value: string | null) => {
    if (!value) return '—';
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? value : d.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
};

/** Live board rate + AFN equivalent for an amount entered in `currencyCode`. Display only. */
export function useAfnPreview(amount: number, currencyCode: string | null | undefined) {
    const { rateToAfn, effectiveAt, source } = useExchange();
    const code = (currencyCode || 'AFN').toUpperCase();
    const rate = rateToAfn(code);
    const afn = rate == null ? null : Math.round((Number(amount) || 0) * rate * 100) / 100;

    return { code, rate, afn, available: rate != null, effectiveAt, source };
}

export function MissingRateNotice({ code }: { code: string }) {
    return (
        <p className="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            <span>
                No active {code} → AFN rate on the Exchange Board. An admin must post one under Exchange Rates before this can be
                saved.
            </span>
        </p>
    );
}

type Row = { label: string; value: ReactNode; emphasize?: boolean; tone?: 'default' | 'danger' | 'success' };

type Props = {
    title?: string;
    amount: number;
    currencyCode: string | null | undefined;
    /** Extra rows rendered under the rate/AFN rows (e.g. balances). */
    rows?: Row[];
    footer?: ReactNode;
};

/**
 * Read-only preview of the rate the server will snapshot and the AFN equivalent.
 * Nothing here is submitted; the server re-resolves the rate at post time.
 */
export function ExchangePreviewPanel({ title = 'Preview (display only)', amount, currencyCode, rows = [], footer }: Props) {
    const preview = useAfnPreview(amount, currencyCode);

    const toneClass = (tone?: Row['tone']) =>
        tone === 'danger' ? 'text-destructive' : tone === 'success' ? 'text-emerald-700' : '';

    return (
        <div className="bg-muted/30 space-y-2 rounded-lg border p-3 text-sm">
            <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{title}</p>
            {!preview.available ? (
                <MissingRateNotice code={preview.code} />
            ) : (
                <div className="grid grid-cols-2 gap-y-1.5">
                    <span className="text-muted-foreground">Rate used</span>
                    <span className="text-right font-mono">
                        {preview.code === 'AFN' ? 'AFN (ledger)' : `1 ${preview.code} = ${formatRateValue(preview.rate)} AFN`}
                    </span>
                    {preview.code !== 'AFN' && (
                        <>
                            <span className="text-muted-foreground">Effective</span>
                            <span className="text-right text-xs">
                                {formatEffective(preview.effectiveAt)}
                                {preview.source ? ` · ${preview.source}` : ''}
                            </span>
                        </>
                    )}
                    <span className="text-muted-foreground">AFN equivalent</span>
                    <span className="text-right font-mono font-semibold">{formatPreviewAmount(preview.afn)} AFN</span>
                </div>
            )}
            {rows.length > 0 && (
                <div className="grid grid-cols-2 gap-y-1.5 border-t pt-2">
                    {rows.map((row) => (
                        <div key={row.label} className="contents">
                            <span className="text-muted-foreground">{row.label}</span>
                            <span className={`text-right font-mono ${row.emphasize ? 'font-semibold' : ''} ${toneClass(row.tone)}`}>
                                {row.value}
                            </span>
                        </div>
                    ))}
                </div>
            )}
            {footer}
        </div>
    );
}
