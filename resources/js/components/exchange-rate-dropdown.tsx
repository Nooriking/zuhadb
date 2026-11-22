import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useExchange } from '@/hooks/useExchange';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRightLeft } from 'lucide-react';

function formatRate(value: number | null | undefined): string {
    if (value == null || Number.isNaN(value) || !Number.isFinite(value)) return '—';
    const fixed = value >= 100 ? value.toFixed(2) : value.toFixed(4);
    return fixed.replace(/(\.\d*?[1-9])0+$/, '$1').replace(/\.0+$/, '');
}

function formatWhen(value: string | null | undefined): string | null {
    if (!value) return null;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
}

export function ExchangeRateDropdown({ className }: { className?: string }) {
    const { usdToAfn, aedToAfn, usdToAed, effectiveAt, source } = useExchange();
    const modules = ((usePage().props as { access?: { modules?: string[] } }).access?.modules) ?? [];
    const canManageRates = modules.includes('*') || modules.includes('exchange-rates') || modules.includes('currencies');

    const triggerLabel = usdToAfn != null ? `1 USD = ${formatRate(usdToAfn)} AFN` : 'Exchange rates';

    const rows = [
        { label: '1 USD', equals: formatRate(usdToAfn), unit: 'AFN', missing: usdToAfn == null },
        { label: '1 AED', equals: formatRate(aedToAfn), unit: 'AFN', missing: aedToAfn == null },
        { label: '1 USD', equals: formatRate(usdToAed), unit: 'AED', missing: usdToAed == null },
    ];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className={cn('h-9 max-w-[14rem] gap-1.5 px-2.5 font-normal', className)}
                    aria-label="Today's exchange rates"
                >
                    <ArrowRightLeft className="h-3.5 w-3.5 shrink-0 opacity-70" />
                    <span className="truncate text-xs tabular-nums sm:text-sm">{triggerLabel}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-72" sideOffset={8}>
                <DropdownMenuLabel className="font-normal">
                    <div className="text-sm font-semibold">Today's rates</div>
                    <div className="text-muted-foreground mt-0.5 text-xs">
                        {formatWhen(effectiveAt) ? `As of ${formatWhen(effectiveAt)}` : 'Latest valid board rates'}
                        {source ? ` · ${source}` : null}
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <div className="space-y-1.5 p-2">
                    {rows.map((row) => (
                        <div
                            key={`${row.label}-${row.unit}`}
                            className="flex items-baseline justify-between gap-3 rounded-md px-2 py-1.5 text-sm"
                        >
                            <span className="text-muted-foreground">{row.label}</span>
                            <span className={cn('font-mono font-semibold tabular-nums', row.missing && 'text-amber-700 dark:text-amber-400')}>
                                = {row.equals} {row.unit}
                            </span>
                        </div>
                    ))}
                </div>
                {(usdToAfn == null || aedToAfn == null) && (
                    <>
                        <DropdownMenuSeparator />
                        <p className="text-muted-foreground px-3 py-2 text-xs">
                            Some board rates are missing. Post USD→AFN and AED→AFN on the Exchange Rates page.
                        </p>
                    </>
                )}
                {canManageRates && (
                    <>
                        <DropdownMenuSeparator />
                        <div className="p-1">
                            <Button variant="ghost" size="sm" className="h-8 w-full justify-start text-xs" asChild>
                                <Link href="/exchange-rates">Manage rates</Link>
                            </Button>
                        </div>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
