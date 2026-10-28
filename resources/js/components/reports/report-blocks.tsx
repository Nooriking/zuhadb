import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';
import { type Matrix, type NamedRow, fmt, fmtPct, fmtShort, sum } from './report-utils';

const TONES = {
    slate: { border: 'border-l-slate-500', text: '', bg: '' },
    blue: { border: 'border-l-blue-500', text: 'text-blue-700 dark:text-blue-400', bg: 'bg-blue-50/40 dark:bg-blue-950/20' },
    green: { border: 'border-l-emerald-500', text: 'text-emerald-700 dark:text-emerald-400', bg: 'bg-emerald-50/40 dark:bg-emerald-950/20' },
    red: { border: 'border-l-red-500', text: 'text-red-700 dark:text-red-400', bg: 'bg-red-50/40 dark:bg-red-950/20' },
    violet: { border: 'border-l-violet-500', text: 'text-violet-700 dark:text-violet-400', bg: 'bg-violet-50/40 dark:bg-violet-950/20' },
    amber: { border: 'border-l-amber-500', text: 'text-amber-700 dark:text-amber-400', bg: 'bg-amber-50/40 dark:bg-amber-950/20' },
} as const;

export type Tone = keyof typeof TONES;

export function KpiCard({
    label,
    value,
    sub,
    icon: Icon,
    tone = 'slate',
}: {
    label: string;
    value: ReactNode;
    sub?: ReactNode;
    icon?: LucideIcon;
    tone?: Tone;
}) {
    const t = TONES[tone];
    return (
        <Card className={`border-l-4 ${t.border} ${t.bg}`}>
            <CardHeader className="pb-2">
                <CardDescription className={`flex items-center gap-1 ${t.text}`}>
                    {Icon && <Icon className="h-3.5 w-3.5" />} {label}
                </CardDescription>
                <CardTitle className={`truncate font-mono text-2xl ${t.text}`}>{value}</CardTitle>
                {sub && <p className="text-muted-foreground text-xs">{sub}</p>}
            </CardHeader>
        </Card>
    );
}

export function Section({
    title,
    description,
    icon: Icon,
    action,
    children,
}: {
    title: string;
    description?: string;
    icon?: LucideIcon;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center justify-between gap-3">
                    <span className="flex items-center gap-2">
                        {Icon && <Icon className="h-5 w-5" />}
                        {title}
                    </span>
                    {action}
                </CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

export function EmptyState({ children = 'No data for this period.' }: { children?: ReactNode }) {
    return <div className="text-muted-foreground py-8 text-center text-sm">{children}</div>;
}

export function ShareBar({ value, color = 'bg-amber-500' }: { value: number; color?: string }) {
    return (
        <div className="flex items-center gap-2">
            <div className="bg-muted h-1.5 w-20 overflow-hidden rounded-full">
                <div className={`h-full ${color}`} style={{ width: `${Math.min(100, Math.max(0, value))}%` }} />
            </div>
            <span className="text-muted-foreground font-mono text-[11px]">{fmtPct(value)}</span>
        </div>
    );
}

/** Ranked performance table for branches, sellers, customers, payment methods and brands. */
export function EntityTable({
    rows,
    nameLabel,
    showQty = true,
    renderName,
    subKey,
}: {
    rows: NamedRow[];
    nameLabel: string;
    showQty?: boolean;
    renderName?: (row: NamedRow) => ReactNode;
    subKey?: 'role' | 'phone';
}) {
    if (rows.length === 0) return <EmptyState />;

    const totalSales = sum(rows, (r) => r.sales);
    const totalProfit = sum(rows, (r) => r.total_profit);

    return (
        <div className="overflow-x-auto rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-10">#</TableHead>
                        <TableHead>{nameLabel}</TableHead>
                        <TableHead className="text-center">Orders</TableHead>
                        {showQty && <TableHead className="text-center">Qty</TableHead>}
                        <TableHead className="text-right">Sales</TableHead>
                        <TableHead className="text-right">Cost</TableHead>
                        <TableHead className="text-right">Profit</TableHead>
                        <TableHead className="text-right">Margin</TableHead>
                        <TableHead>Share of sales</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row, i) => (
                        <TableRow key={`${row.id ?? 'none'}-${i}`}>
                            <TableCell className="text-muted-foreground text-xs">{i + 1}</TableCell>
                            <TableCell className="font-medium">
                                {renderName ? renderName(row) : row.name}
                                {subKey && row[subKey] && <div className="text-muted-foreground text-xs font-normal">{row[subKey]}</div>}
                            </TableCell>
                            <TableCell className="text-center font-mono text-xs">{row.orders}</TableCell>
                            {showQty && <TableCell className="text-center font-mono text-xs">{row.quantity ?? '—'}</TableCell>}
                            <TableCell className="text-right font-mono text-xs font-semibold">{fmt(row.sales)}</TableCell>
                            <TableCell className="text-muted-foreground text-right font-mono text-xs">{fmt(row.cost)}</TableCell>
                            <TableCell className="text-right font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                {fmt(row.total_profit)}
                            </TableCell>
                            <TableCell className="text-right font-mono text-xs">{fmtPct(row.margin)}</TableCell>
                            <TableCell>
                                <ShareBar value={totalSales > 0 ? (row.sales / totalSales) * 100 : 0} />
                            </TableCell>
                        </TableRow>
                    ))}
                    <TableRow className="bg-muted/60 font-bold">
                        <TableCell />
                        <TableCell>TOTAL</TableCell>
                        <TableCell className="text-center font-mono text-xs">{sum(rows, (r) => r.orders)}</TableCell>
                        {showQty && <TableCell className="text-center font-mono text-xs">{sum(rows, (r) => r.quantity)}</TableCell>}
                        <TableCell className="text-right font-mono text-xs">{fmt(totalSales)}</TableCell>
                        <TableCell className="text-right font-mono text-xs">{fmt(sum(rows, (r) => r.cost))}</TableCell>
                        <TableCell className="text-right font-mono text-xs">{fmt(totalProfit)}</TableCell>
                        <TableCell className="text-right font-mono text-xs">
                            {fmtPct(totalSales > 0 ? (totalProfit / totalSales) * 100 : 0)}
                        </TableCell>
                        <TableCell className="font-mono text-xs">100%</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    );
}

/** Person × period pivot (e.g. each investor's profit per month). */
export function MatrixTable({
    matrix,
    nameLabel,
    renderName,
}: {
    matrix: Matrix;
    nameLabel: string;
    renderName?: (row: Matrix['rows'][number]) => ReactNode;
}) {
    if (matrix.rows.length === 0) return <EmptyState />;
    const compact = matrix.periods.length > 6;
    const cell = (v: number) => (v === 0 ? '—' : compact ? fmtShort(v) : fmt(v));

    return (
        <div className="overflow-x-auto rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="bg-background sticky left-0 min-w-40">{nameLabel}</TableHead>
                        {matrix.periods.map((p) => (
                            <TableHead key={p} className="text-right font-mono text-xs whitespace-nowrap">
                                {p}
                            </TableHead>
                        ))}
                        <TableHead className="text-right">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {matrix.rows.map((row) => (
                        <TableRow key={String(row.id ?? row.name)}>
                            <TableCell className="bg-background sticky left-0 font-medium">{renderName ? renderName(row) : row.name}</TableCell>
                            {matrix.periods.map((p) => (
                                <TableCell key={p} className={`text-right font-mono text-xs ${row.values[p] ? '' : 'text-muted-foreground'}`}>
                                    {cell(row.values[p] ?? 0)}
                                </TableCell>
                            ))}
                            <TableCell className="text-right font-mono text-xs font-bold">{cell(row.total)}</TableCell>
                        </TableRow>
                    ))}
                    <TableRow className="bg-muted/60 font-bold">
                        <TableCell className="bg-muted sticky left-0">TOTAL</TableCell>
                        {matrix.periods.map((p) => (
                            <TableCell key={p} className="text-right font-mono text-xs">
                                {cell(matrix.totals[p] ?? 0)}
                            </TableCell>
                        ))}
                        <TableCell className="text-right font-mono text-xs">
                            {cell(Object.values(matrix.totals).reduce((a, b) => a + b, 0))}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    );
}
