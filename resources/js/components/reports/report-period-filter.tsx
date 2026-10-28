import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router } from '@inertiajs/react';
import { CalendarDays, CalendarRange, ChevronLeft, ChevronRight, Filter, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { type BranchOption, type GroupBy, type PeriodType, type ReportFilters, filterParams } from './report-utils';

interface Props {
    url: string;
    filters: ReportFilters;
    branches?: BranchOption[];
    /** Extra query params kept on apply (e.g. the active tab). */
    extra?: Record<string, string | undefined>;
}

const thisMonth = () => new Date().toISOString().slice(0, 7);

const shiftMonth = (month: string, delta: number): string => {
    const [y, m] = month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};

export function ReportPeriodFilter({ url, filters, branches = [], extra = {} }: Props) {
    const [form, setForm] = useState<ReportFilters>(filters);
    const currentYear = new Date().getFullYear();
    const years = Array.from({ length: 8 }, (_, i) => currentYear - i);

    const apply = (next: ReportFilters = form) => {
        router.get(`${url}?${filterParams(next, extra)}`, {}, { preserveScroll: true });
    };

    const setPeriod = (period: PeriodType) => {
        const group_by: GroupBy = period === 'year' ? 'monthly' : period === 'month' ? 'daily' : form.group_by;
        setForm({ ...form, period, group_by });
    };

    const quickApply = (patch: Partial<ReportFilters>) => {
        const next = { ...form, ...patch };
        setForm(next);
        apply(next);
    };

    const periodButtons: { value: PeriodType; label: string; icon: typeof CalendarDays }[] = [
        { value: 'month', label: 'Monthly', icon: CalendarDays },
        { value: 'year', label: 'Yearly', icon: CalendarRange },
        { value: 'custom', label: 'Custom range', icon: Filter },
    ];

    return (
        <Card>
            <CardContent className="space-y-4 pt-6">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="bg-muted inline-flex rounded-lg p-1">
                        {periodButtons.map(({ value, label, icon: Icon }) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => setPeriod(value)}
                                className={`inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                    form.period === value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <Icon className="h-3.5 w-3.5" />
                                {label}
                            </button>
                        ))}
                    </div>

                    <div className="ml-auto flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => quickApply({ period: 'month', month: thisMonth(), group_by: 'daily' })}
                        >
                            This month
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => quickApply({ period: 'month', month: shiftMonth(thisMonth(), -1), group_by: 'daily' })}
                        >
                            Last month
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => quickApply({ period: 'year', year: currentYear, group_by: 'monthly' })}
                        >
                            This year
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => quickApply({ period: 'year', year: currentYear - 1, group_by: 'monthly' })}
                        >
                            Last year
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 items-end gap-4 md:grid-cols-2 lg:grid-cols-5">
                    {form.period === 'month' && (
                        <div className="space-y-2 lg:col-span-2">
                            <Label>Month</Label>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="outline"
                                    onClick={() => quickApply({ month: shiftMonth(form.month, -1) })}
                                    aria-label="Previous month"
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <Input type="month" value={form.month} onChange={(e) => setForm({ ...form, month: e.target.value })} />
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="outline"
                                    onClick={() => quickApply({ month: shiftMonth(form.month, 1) })}
                                    aria-label="Next month"
                                >
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    )}

                    {form.period === 'year' && (
                        <div className="space-y-2 lg:col-span-2">
                            <Label>Year</Label>
                            <Select value={String(form.year)} onValueChange={(v) => setForm({ ...form, year: Number(v) })}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {years.map((y) => (
                                        <SelectItem key={y} value={String(y)}>
                                            {y}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {form.period === 'custom' && (
                        <>
                            <div className="space-y-2">
                                <Label>From</Label>
                                <Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} />
                            </div>
                            <div className="space-y-2">
                                <Label>To</Label>
                                <Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} />
                            </div>
                        </>
                    )}

                    <div className="space-y-2">
                        <Label>Group by</Label>
                        <Select value={form.group_by} onValueChange={(v) => setForm({ ...form, group_by: v as GroupBy })}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="daily">Daily</SelectItem>
                                <SelectItem value="monthly">Monthly</SelectItem>
                                <SelectItem value="yearly">Yearly</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {branches.length > 0 && (
                        <div className="space-y-2">
                            <Label>Branch</Label>
                            <Select
                                value={form.branch_id ? String(form.branch_id) : 'all'}
                                onValueChange={(v) => setForm({ ...form, branch_id: v === 'all' ? null : Number(v) })}
                                disabled={filters.locked_branch}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All branches</SelectItem>
                                    {branches.map((b) => (
                                        <SelectItem key={b.id} value={String(b.id)}>
                                            {b.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <div className="flex gap-2">
                        <Button onClick={() => apply()} className="flex-1 gap-2">
                            <Filter className="h-4 w-4" /> Apply
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() =>
                                quickApply({
                                    period: 'month',
                                    month: thisMonth(),
                                    group_by: 'daily',
                                    branch_id: filters.locked_branch ? filters.branch_id : null,
                                })
                            }
                            aria-label="Reset filters"
                        >
                            <RotateCcw className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
