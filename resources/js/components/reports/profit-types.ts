import { type Matrix, type MetricRow, type NamedRow } from './report-utils';

export interface ProfitSummary {
    from: string;
    to: string;
    total_sales: number;
    total_discount: number;
    total_returns: number;
    returns_count: number;
    total_cost: number;
    total_profit: number;
    investor_profit: number;
    store_profit: number;
    total_expenses: number;
    net_profit: number;
    store_net_profit: number;
    margin: number;
    avg_order: number;
    total_quantity: number;
    total_orders: number;
    customers: number;
}

export interface BreakdownRow extends MetricRow {
    period: string;
    quantity: number;
    returns: number;
    expenses: number;
    net: number;
    store_net: number;
}

export interface ExpensesData {
    total: number;
    by_category: { category: string; count: number; total: number }[];
}

export interface ExchangeRate {
    month: string;
    avg_rate: number;
    min_rate: number;
    max_rate: number;
    days: number;
}

export interface SalesSection {
    by_branch: NamedRow[];
    by_seller: NamedRow[];
    by_payment: NamedRow[];
    by_customer: NamedRow[];
    seller_matrix: Matrix;
}

export interface ProductRow extends NamedRow {
    brand: string;
}

export interface ProductsSection {
    by_product: ProductRow[];
    by_brand: (NamedRow & { products: number })[];
    stats: {
        variants_sold: number;
        brands_sold: number;
        top_product: string | null;
        top_brand: string | null;
    };
}

export interface InvestorRow extends NamedRow {
    status: string;
    share: number;
    entries_paid: number;
    entries_pending: number;
    total_invested: number;
    capital_balance: number;
    profit_balance: number;
    total_withdrawn: number;
    lifetime_profit: number;
}

export interface InvestorsSection {
    rows: InvestorRow[];
    store: MetricRow;
    matrix: Matrix;
    stats: {
        total_investors: number;
        active_investors: number;
        investor_profit: number;
        total_invested: number;
        capital_balance: number;
        profit_balance: number;
        entries_paid: number;
        entries_pending: number;
    };
}
