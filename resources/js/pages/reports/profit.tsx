// resources/js/pages/reports/profit.tsx

import { BackButton } from '@/components/form-page';
import { ProfitInvestorsTab } from '@/components/reports/profit-investors-tab';
import { ProfitOverviewTab } from '@/components/reports/profit-overview-tab';
import { ProfitProductsTab } from '@/components/reports/profit-products-tab';
import { ProfitSalesTab } from '@/components/reports/profit-sales-tab';
import {
    type BreakdownRow,
    type ExchangeRate,
    type ExpensesData,
    type InvestorsSection,
    type ProductsSection,
    type ProfitSummary,
    type SalesSection,
} from '@/components/reports/profit-types';
import { ReportPeriodFilter } from '@/components/reports/report-period-filter';
import { type BranchOption, type ReportFilters, filterParams } from '@/components/reports/report-utils';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { BarChart3, ChevronDown, Download, FileSpreadsheet, FileText, LayoutDashboard, Package, ShoppingCart, Users } from 'lucide-react';
import { useState } from 'react';

type Tab = 'overview' | 'sales' | 'products' | 'investors';

interface Props {
    summary: ProfitSummary;
    breakdown: BreakdownRow[];
    expenses: ExpensesData;
    exchangeRates: ExchangeRate[];
    sales: SalesSection;
    products: ProductsSection;
    investors: InvestorsSection;
    branches: BranchOption[];
    filters: ReportFilters;
    tab: Tab;
}

const TABS: { value: Tab; label: string; icon: typeof BarChart3; pdfTitle: string }[] = [
    { value: 'overview', label: 'Overview', icon: LayoutDashboard, pdfTitle: 'Profit overview' },
    { value: 'sales', label: 'Sales Report', icon: ShoppingCart, pdfTitle: 'Sales report' },
    { value: 'products', label: 'Product Report', icon: Package, pdfTitle: 'Product report' },
    { value: 'investors', label: 'Investor Report', icon: Users, pdfTitle: 'Investor report' },
];

const CSV_EXPORTS: { path: string; label: string }[] = [
    { path: 'export', label: 'Full report (all sections)' },
    { path: 'export-summary', label: 'Summary & expenses' },
    { path: 'export-breakdown', label: 'Period breakdown' },
    { path: 'export-sales', label: 'Sales (branch, seller, customer)' },
    { path: 'export-products', label: 'Products & brands' },
    { path: 'export-per-investor', label: 'Investors' },
    { path: 'export-exchange-rates', label: 'Exchange rates' },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Profit Report', href: '/reports/profit' },
];

export default function ProfitReport({
    summary,
    breakdown,
    expenses,
    exchangeRates,
    sales,
    products,
    investors,
    branches,
    filters,
    tab: initialTab,
}: Props) {
    const [tab, setTab] = useState<Tab>(initialTab ?? 'overview');

    const switchTab = (next: Tab) => {
        setTab(next);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', next);
        window.history.replaceState(window.history.state, '', url);
    };

    const download = (path: string, extra: Record<string, string> = {}) => {
        window.location.href = `/reports/profit/${path}?${filterParams(filters, extra)}`;
    };

    const activeTab = TABS.find((t) => t.value === tab) ?? TABS[0];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Profit Report · ${filters.label}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/dashboard" label="Back" />
                    <div className="flex items-center gap-3">
                        <BarChart3 className="text-primary h-6 w-6" />
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Profit Report</h1>
                            <p className="text-muted-foreground text-sm">
                                {filters.label}
                                {filters.branch_name ? ` · ${filters.branch_name}` : ' · All branches'}
                            </p>
                        </div>
                    </div>

                    <div className="ml-auto flex flex-wrap gap-2">
                        <Button onClick={() => download('pdf', { section: tab })} className="gap-2 bg-red-600 hover:bg-red-700">
                            <FileText className="h-4 w-4" /> PDF: {activeTab.pdfTitle}
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" className="gap-2">
                                    <Download className="h-4 w-4" /> More downloads <ChevronDown className="h-3.5 w-3.5" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-64">
                                <DropdownMenuLabel className="flex items-center gap-2">
                                    <FileText className="h-3.5 w-3.5 text-red-600" /> PDF reports
                                </DropdownMenuLabel>
                                <DropdownMenuItem onClick={() => download('pdf', { section: 'all' })} className="font-semibold">
                                    Complete report (all sections)
                                </DropdownMenuItem>
                                {TABS.map((t) => (
                                    <DropdownMenuItem key={t.value} onClick={() => download('pdf', { section: t.value })}>
                                        {t.pdfTitle}
                                    </DropdownMenuItem>
                                ))}
                                <DropdownMenuItem onClick={() => download('store/pdf')}>Store-owned goods</DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel className="flex items-center gap-2">
                                    <FileSpreadsheet className="h-3.5 w-3.5 text-emerald-600" /> Excel (CSV)
                                </DropdownMenuLabel>
                                {CSV_EXPORTS.map((c) => (
                                    <DropdownMenuItem key={c.path} onClick={() => download(c.path)}>
                                        {c.label}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <ReportPeriodFilter url="/reports/profit" filters={filters} branches={branches} extra={{ tab }} />

                <div className="bg-muted/60 flex flex-wrap gap-1 rounded-xl p-1">
                    {TABS.map(({ value, label, icon: Icon }) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => switchTab(value)}
                            className={`flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold whitespace-nowrap transition-colors ${
                                tab === value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <Icon className="h-4 w-4" />
                            {label}
                        </button>
                    ))}
                </div>

                {tab === 'overview' && (
                    <ProfitOverviewTab summary={summary} breakdown={breakdown} expenses={expenses} exchangeRates={exchangeRates} filters={filters} />
                )}
                {tab === 'sales' && <ProfitSalesTab summary={summary} sales={sales} filters={filters} />}
                {tab === 'products' && <ProfitProductsTab summary={summary} products={products} />}
                {tab === 'investors' && <ProfitInvestorsTab investors={investors} filters={filters} />}
            </div>
        </AppLayout>
    );
}
