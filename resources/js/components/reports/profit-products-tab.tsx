import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Award, Package, Search, ShoppingBag, Tag } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { type ProductRow, type ProductsSection, type ProfitSummary } from './profit-types';
import { EmptyState, EntityTable, KpiCard, Section } from './report-blocks';
import { CHART_COLORS, PIE_COLORS, fmt, fmtPct, fmtShort, sum, tooltipStyle } from './report-utils';

interface Props {
    summary: ProfitSummary;
    products: ProductsSection;
}

type SortKey = 'sales' | 'quantity' | 'total_profit' | 'margin';

export function ProfitProductsTab({ summary, products }: Props) {
    const [search, setSearch] = useState('');
    const [sortBy, setSortBy] = useState<SortKey>('sales');

    const rows = useMemo(() => {
        const q = search.trim().toLowerCase();
        return products.by_product
            .filter((r) => !q || r.name.toLowerCase().includes(q) || r.brand.toLowerCase().includes(q))
            .sort((a, b) => (b[sortBy] ?? 0) - (a[sortBy] ?? 0));
    }, [products.by_product, search, sortBy]);

    const topChart = products.by_product.slice(0, 10).map((r) => ({
        name: r.name.length > 28 ? `${r.name.slice(0, 26)}…` : r.name,
        investor: r.investor_profit,
        store: r.store_profit,
        cost: r.cost,
    }));
    const brandPie = products.by_brand.slice(0, 8).map((b) => ({ name: b.name, value: b.sales }));

    return (
        <div className="flex flex-col gap-6">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Products Sold" value={products.stats.variants_sold} sub="Distinct variants" icon={Package} />
                <KpiCard label="Units Sold" value={fmt(summary.total_quantity)} sub="Pieces" icon={ShoppingBag} tone="blue" />
                <KpiCard
                    label="Best Seller"
                    value={<span className="text-lg">{products.stats.top_product ?? '—'}</span>}
                    sub="By sales amount"
                    icon={Award}
                    tone="amber"
                />
                <KpiCard
                    label="Top Brand"
                    value={<span className="text-lg">{products.stats.top_brand ?? '—'}</span>}
                    sub={`${products.stats.brands_sold} brands sold`}
                    icon={Tag}
                    tone="violet"
                />
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <Section title="Top 10 Products" description="Cost and profit split per product (AFN)" icon={Package}>
                        {topChart.length === 0 ? (
                            <EmptyState />
                        ) : (
                            <ResponsiveContainer width="100%" height={Math.max(220, topChart.length * 38)}>
                                <BarChart data={topChart} layout="vertical" margin={{ top: 5, right: 20, left: 10, bottom: 5 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis type="number" tickFormatter={fmtShort} tick={{ fontSize: 11 }} />
                                    <YAxis type="category" dataKey="name" width={170} tick={{ fontSize: 11 }} />
                                    <Tooltip formatter={(v) => `${fmt(Number(v))} AFN`} contentStyle={tooltipStyle} />
                                    <Legend />
                                    <Bar dataKey="cost" name="Cost" stackId="a" fill={CHART_COLORS.cost} />
                                    <Bar dataKey="investor" name="Investor Profit" stackId="a" fill={CHART_COLORS.investor} />
                                    <Bar dataKey="store" name="Store Profit" stackId="a" fill={CHART_COLORS.store} radius={[0, 4, 4, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </Section>
                </div>

                <Section title="Brand Share" description="Sales by brand" icon={Tag}>
                    {brandPie.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <ResponsiveContainer width="100%" height={280}>
                            <PieChart>
                                <Pie data={brandPie} dataKey="value" nameKey="name" cx="50%" cy="45%" innerRadius={45} outerRadius={85}>
                                    {brandPie.map((_, i) => (
                                        <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip formatter={(v) => `${fmt(Number(v))} AFN`} contentStyle={tooltipStyle} />
                                <Legend />
                            </PieChart>
                        </ResponsiveContainer>
                    )}
                </Section>
            </div>

            <Section title="Sales by Brand" icon={Tag}>
                <EntityTable rows={products.by_brand} nameLabel="Brand" />
            </Section>

            <Section
                title={`All Products (${rows.length})`}
                description="Figures are based on sold line items (before returns)"
                icon={Package}
                action={
                    <div className="flex flex-wrap gap-2">
                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search product or brand…"
                                className="h-9 w-56 pl-8 text-sm"
                            />
                        </div>
                        <Select value={sortBy} onValueChange={(v) => setSortBy(v as SortKey)}>
                            <SelectTrigger className="h-9 w-40 text-sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="sales">Sort: Sales</SelectItem>
                                <SelectItem value="quantity">Sort: Quantity</SelectItem>
                                <SelectItem value="total_profit">Sort: Profit</SelectItem>
                                <SelectItem value="margin">Sort: Margin</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                }
            >
                <ProductTable rows={rows} />
            </Section>
        </div>
    );
}

function ProductTable({ rows }: { rows: ProductRow[] }) {
    if (rows.length === 0) return <EmptyState>No products found.</EmptyState>;

    return (
        <div className="max-h-[600px] overflow-auto rounded-md border">
            <Table>
                <TableHeader className="bg-background sticky top-0 z-10">
                    <TableRow>
                        <TableHead className="w-10">#</TableHead>
                        <TableHead>Product</TableHead>
                        <TableHead>Brand</TableHead>
                        <TableHead className="text-center">Orders</TableHead>
                        <TableHead className="text-center">Qty</TableHead>
                        <TableHead className="text-right">Sales</TableHead>
                        <TableHead className="text-right">Cost</TableHead>
                        <TableHead className="bg-blue-50 text-right text-blue-800 dark:bg-blue-950/30">Investor</TableHead>
                        <TableHead className="bg-emerald-50 text-right text-emerald-800 dark:bg-emerald-950/30">Store</TableHead>
                        <TableHead className="text-right">Profit</TableHead>
                        <TableHead className="text-right">Margin</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((r, i) => (
                        <TableRow key={String(r.id)}>
                            <TableCell className="text-muted-foreground text-xs">{i + 1}</TableCell>
                            <TableCell className="font-medium">{r.name}</TableCell>
                            <TableCell className="text-muted-foreground text-xs">{r.brand}</TableCell>
                            <TableCell className="text-center font-mono text-xs">{r.orders}</TableCell>
                            <TableCell className="text-center font-mono text-xs">{r.quantity}</TableCell>
                            <TableCell className="text-right font-mono text-xs font-semibold">{fmt(r.sales)}</TableCell>
                            <TableCell className="text-muted-foreground text-right font-mono text-xs">{fmt(r.cost)}</TableCell>
                            <TableCell className="bg-blue-50 text-right font-mono text-xs text-blue-800 dark:bg-blue-950/30 dark:text-blue-400">
                                {r.investor_profit > 0 ? fmt(r.investor_profit) : '—'}
                            </TableCell>
                            <TableCell className="bg-emerald-50 text-right font-mono text-xs text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                                {fmt(r.store_profit)}
                            </TableCell>
                            <TableCell className="text-right font-mono text-xs font-bold">{fmt(r.total_profit)}</TableCell>
                            <TableCell className={`text-right font-mono text-xs ${r.margin < 0 ? 'text-red-600' : ''}`}>{fmtPct(r.margin)}</TableCell>
                        </TableRow>
                    ))}
                    <TableRow className="bg-muted/60 font-bold">
                        <TableCell />
                        <TableCell colSpan={2}>TOTAL</TableCell>
                        <TableCell className="text-center font-mono text-xs">—</TableCell>
                        <TableCell className="text-center font-mono text-xs">{sum(rows, (r) => r.quantity)}</TableCell>
                        <TableCell className="text-right font-mono text-xs">{fmt(sum(rows, (r) => r.sales))}</TableCell>
                        <TableCell className="text-right font-mono text-xs">{fmt(sum(rows, (r) => r.cost))}</TableCell>
                        <TableCell className="text-right font-mono text-xs">{fmt(sum(rows, (r) => r.investor_profit))}</TableCell>
                        <TableCell className="text-right font-mono text-xs">{fmt(sum(rows, (r) => r.store_profit))}</TableCell>
                        <TableCell className="text-right font-mono text-xs">{fmt(sum(rows, (r) => r.total_profit))}</TableCell>
                        <TableCell />
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    );
}
