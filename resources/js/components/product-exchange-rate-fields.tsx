import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ExchangeRateContext = {
    usd?: { code: string; name: string; symbol: string | null } | null;
    afn?: { code: string; name: string; symbol: string | null } | null;
    aed?: { code: string; name: string; symbol: string | null } | null;
    catalog_usd_to_afn?: string | null;
    catalog_usd_to_aed?: string | null;
    rate_definition?: string;
};

type Props = {
    usdToAfn: string;
    usdToAed: string;
    onChange: (field: 'usd_to_afn_rate' | 'usd_to_aed_rate', value: string) => void;
    errors?: Record<string, string>;
    context?: ExchangeRateContext;
};

export function ProductExchangeRateFields({ usdToAfn, usdToAed, onChange, errors = {}, context }: Props) {
    const usd = context?.usd?.code || 'USD';
    const afn = context?.afn?.code || 'AFN';
    const aed = context?.aed?.code || 'AED';

    return (
        <div className="space-y-3 rounded-lg border p-4">
            <div>
                <h4 className="text-sm font-semibold">Reference rates (not a transaction)</h4>
                <p className="text-muted-foreground text-xs">
                    Direction is always “how many target units equal <strong>1 {usd}</strong>”. Example: if 1 {usd} = 70 {afn}, enter{' '}
                    <strong>70</strong>. These are product-level reference rates for estimates only. They are not proof that a purchase,
                    sale, or cash exchange happened at this rate, and they never rewrite catalog rates or historical snapshots.
                </p>
            </div>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="usd_to_afn_rate">
                        {usd} to {afn} — {afn} per 1 {usd}
                    </Label>
                    <Input
                        id="usd_to_afn_rate"
                        type="number"
                        min="0.00000001"
                        step="0.00000001"
                        inputMode="decimal"
                        value={usdToAfn}
                        onChange={(e) => onChange('usd_to_afn_rate', e.target.value)}
                        placeholder="e.g. 70"
                        className={errors.usd_to_afn_rate ? 'border-destructive' : ''}
                    />
                    <p className="text-muted-foreground text-xs">
                        Direct conversion: 1 {usd} = this many {afn}
                        {context?.afn?.symbol ? ` (${context.afn.symbol})` : ''}. Not the inverse ({usd} per 1 {afn}).
                    </p>
                    {context?.catalog_usd_to_afn ? (
                        <p className="text-muted-foreground text-xs">
                            Catalog (system-wide) currently implies 1 {usd} ≈ {context.catalog_usd_to_afn} {afn}. Leave blank to keep this
                            product without its own rate.
                        </p>
                    ) : null}
                    {errors.usd_to_afn_rate ? <p className="text-destructive text-xs">{errors.usd_to_afn_rate}</p> : null}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="usd_to_aed_rate">
                        {usd} to {aed} — {aed} per 1 {usd}
                    </Label>
                    <Input
                        id="usd_to_aed_rate"
                        type="number"
                        min="0.00000001"
                        step="0.00000001"
                        inputMode="decimal"
                        value={usdToAed}
                        onChange={(e) => onChange('usd_to_aed_rate', e.target.value)}
                        placeholder="e.g. 3.67"
                        className={errors.usd_to_aed_rate ? 'border-destructive' : ''}
                    />
                    <p className="text-muted-foreground text-xs">
                        Direct conversion: 1 {usd} = this many {aed}
                        {context?.aed?.symbol ? ` (${context.aed.symbol})` : ''}. Not the inverse ({usd} per 1 {aed}).
                    </p>
                    {context?.catalog_usd_to_aed ? (
                        <p className="text-muted-foreground text-xs">
                            Catalog (system-wide) currently implies 1 {usd} ≈ {context.catalog_usd_to_aed} {aed}. Leave blank to keep this
                            product without its own rate.
                        </p>
                    ) : null}
                    {errors.usd_to_aed_rate ? <p className="text-destructive text-xs">{errors.usd_to_aed_rate}</p> : null}
                </div>
            </div>
        </div>
    );
}
