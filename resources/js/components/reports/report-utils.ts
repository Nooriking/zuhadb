export type GroupBy = 'daily' | 'monthly' | 'yearly';
export type PeriodType = 'month' | 'year' | 'custom';

export interface ReportFilters {
    period: PeriodType;
    month: string;
    year: number;
    from: string;
    to: string;
    group_by: GroupBy;
    branch_id: number | null;
    branch_name: string | null;
    label: string;
    locked_branch: boolean;
}

export interface BranchOption {
    id: number;
    name: string;
}

/** Shape produced by ProfitReportController::metrics() */
export interface MetricRow {
    orders: number;
    quantity: number | null;
    sales: number;
    discount: number;
    cost: number;
    investor_profit: number;
    store_profit: number;
    total_profit: number;
    margin: number;
    avg_order: number;
}

export interface NamedRow extends MetricRow {
    id: number | string | null;
    name: string;
    role?: string | null;
    phone?: string | null;
    customers?: number;
    last_sale?: string;
}

export interface Matrix {
    periods: string[];
    rows: { id: number | string | null; name: string; values: Record<string, number>; total: number }[];
    totals: Record<string, number>;
}

export const fmt = (v: number | null | undefined): string =>
    new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(v || 0);

export const fmtShort = (v: number): string => {
    const abs = Math.abs(v || 0);
    if (abs >= 1_000_000) return `${(v / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000) return `${(v / 1_000).toFixed(1)}K`;
    return String(Math.round(v || 0));
};

export const fmtPct = (v: number | null | undefined): string => `${(v || 0).toFixed(1)}%`;

export const sum = <T>(rows: T[], pick: (row: T) => number | null | undefined): number => rows.reduce((acc, row) => acc + (pick(row) || 0), 0);

export const CHART_COLORS = {
    investor: '#3b82f6',
    store: '#10b981',
    sales: '#6366f1',
    expenses: '#ef4444',
    net: '#8b5cf6',
    profit: '#10b981',
    cost: '#94a3b8',
};

export const PIE_COLORS = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];

export const tooltipStyle = { borderRadius: 12, border: '1px solid #e5e7eb' };

/** Query params that reproduce the current report filters (for links, PDF and CSV downloads). */
export function filterParams(filters: ReportFilters, extra: Record<string, string | number | undefined> = {}): string {
    const params = new URLSearchParams();
    params.set('period', filters.period);
    if (filters.period === 'month') params.set('month', filters.month);
    if (filters.period === 'year') params.set('year', String(filters.year));
    if (filters.period === 'custom') {
        params.set('from', filters.from);
        params.set('to', filters.to);
    }
    params.set('group_by', filters.group_by);
    if (filters.branch_id) params.set('branch_id', String(filters.branch_id));
    Object.entries(extra).forEach(([k, v]) => {
        if (v !== undefined && v !== '') params.set(k, String(v));
    });
    return params.toString();
}
