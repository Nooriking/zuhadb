<?php

namespace App\Support;

use App\Models\User;

/**
 * Canonical reporting navigation catalog.
 *
 * Sidebar shows only items with a real href the user can access.
 * The Reports hub lists the full planned tree and marks availability.
 */
class ReportsCatalog
{
    /**
     * @return list<array{
     *   key: string,
     *   title: string,
     *   description: string,
     *   reports: list<array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     href: string|null,
     *     module: string|null,
     *     status: 'available'|'not_implemented',
     *     accessible: bool
     *   }>
     * }>
     */
    public static function forUser(?User $user): array
    {
        $can = static function (?string $module) use ($user): bool {
            if ($module === null) {
                return true;
            }
            if (! $user) {
                return false;
            }
            if ($user->isSuperAdmin()) {
                return true;
            }

            return $user->canAccessModule($module);
        };

        $categories = self::definition();

        return array_map(static function (array $category) use ($can): array {
            $reports = array_map(static function (array $report) use ($can): array {
                $implemented = filled($report['href'] ?? null);
                $accessible = $implemented && $can($report['module'] ?? null);

                return [
                    'key' => $report['key'],
                    'title' => $report['title'],
                    'description' => $report['description'],
                    'href' => $accessible ? $report['href'] : null,
                    'module' => $report['module'] ?? null,
                    'status' => $implemented ? 'available' : 'not_implemented',
                    'accessible' => $accessible,
                ];
            }, $category['reports']);

            return [
                'key' => $category['key'],
                'title' => $category['title'],
                'description' => $category['description'],
                'reports' => $reports,
            ];
        }, $categories);
    }

    /**
     * @return list<array{key: string, title: string, description: string, reports: list<array{key: string, title: string, description: string, href: ?string, module: ?string}>}>
     */
    public static function definition(): array
    {
        return [
            [
                'key' => 'sales',
                'title' => 'Sales Reports',
                'description' => 'Sales performance, returns, discounts, and payments.',
                'reports' => [
                    [
                        'key' => 'sales-overview',
                        'title' => 'Sales Overview',
                        'description' => 'Filtered sales totals, profit summary, and charts.',
                        'href' => '/sales/report',
                        'module' => 'sales',
                    ],
                    ['key' => 'sales-by-product', 'title' => 'Sales by Product', 'description' => 'Product-level sales breakdown.', 'href' => null, 'module' => 'sales'],
                    ['key' => 'sales-by-brand', 'title' => 'Sales by Brand', 'description' => 'Brand-level sales breakdown.', 'href' => null, 'module' => 'sales'],
                    ['key' => 'sales-by-category', 'title' => 'Sales by Category', 'description' => 'Category-level sales breakdown.', 'href' => null, 'module' => 'sales'],
                    ['key' => 'sales-by-customer', 'title' => 'Sales by Customer', 'description' => 'Customer purchase analysis.', 'href' => null, 'module' => 'sales'],
                    ['key' => 'sales-by-cashier', 'title' => 'Sales by Cashier', 'description' => 'Cashier / salesperson performance.', 'href' => null, 'module' => 'sales'],
                    ['key' => 'sales-discounts', 'title' => 'Discounts', 'description' => 'Discount usage and impact.', 'href' => null, 'module' => 'sales'],
                    ['key' => 'sales-returns', 'title' => 'Sales Returns', 'description' => 'Return volumes and reasons.', 'href' => null, 'module' => 'sale-returns'],
                    ['key' => 'sales-payments', 'title' => 'Sales Payments', 'description' => 'Payment collection by method and status.', 'href' => null, 'module' => 'sales'],
                ],
            ],
            [
                'key' => 'financial',
                'title' => 'Financial Reports',
                'description' => 'Profitability, cash flow, receivables, and payables.',
                'reports' => [
                    ['key' => 'profit-loss', 'title' => 'Profit & Loss', 'description' => 'Period P&L statement.', 'href' => null, 'module' => 'reports'],
                    ['key' => 'store-profit', 'title' => 'Store Profit', 'description' => 'Store-retained profit analysis.', 'href' => null, 'module' => 'reports'],
                    ['key' => 'investor-profit', 'title' => 'Investor Profit', 'description' => 'Investor profit allocation summary.', 'href' => null, 'module' => 'reports'],
                    ['key' => 'expenses', 'title' => 'Expenses', 'description' => 'Expense totals by category and branch.', 'href' => null, 'module' => 'expenses'],
                    ['key' => 'cash-flow', 'title' => 'Cash Flow', 'description' => 'Cash movement over time.', 'href' => null, 'module' => 'reports'],
                    ['key' => 'cash-in-out', 'title' => 'Cash In / Cash Out', 'description' => 'Cash drawer in/out activity.', 'href' => null, 'module' => 'reports'],
                    ['key' => 'receivables', 'title' => 'Receivables', 'description' => 'Outstanding customer balances.', 'href' => null, 'module' => 'reports'],
                    ['key' => 'payables', 'title' => 'Payables', 'description' => 'Outstanding supplier balances.', 'href' => null, 'module' => 'reports'],
                    ['key' => 'payment-methods', 'title' => 'Payment Methods', 'description' => 'Collections by payment method.', 'href' => null, 'module' => 'reports'],
                ],
            ],
            [
                'key' => 'inventory',
                'title' => 'Inventory Reports',
                'description' => 'Stock valuation, movement, aging, and velocity.',
                'reports' => [
                    ['key' => 'inventory-valuation', 'title' => 'Inventory Valuation', 'description' => 'Cost and sale value of on-hand stock.', 'href' => null, 'module' => 'inventory'],
                    ['key' => 'stock-movements', 'title' => 'Stock Movements', 'description' => 'Inbound and outbound stock history.', 'href' => null, 'module' => 'inventory'],
                    ['key' => 'stock-aging', 'title' => 'Stock Aging', 'description' => 'Age of unsold inventory.', 'href' => null, 'module' => 'inventory'],
                    ['key' => 'low-stock', 'title' => 'Low Stock', 'description' => 'Items at or below reorder level.', 'href' => null, 'module' => 'inventory'],
                    ['key' => 'out-of-stock', 'title' => 'Out of Stock', 'description' => 'Items with zero remaining quantity.', 'href' => null, 'module' => 'inventory'],
                    ['key' => 'fast-moving', 'title' => 'Fast-Moving Products', 'description' => 'Highest velocity products.', 'href' => null, 'module' => 'inventory'],
                    ['key' => 'slow-moving', 'title' => 'Slow-Moving Products', 'description' => 'Lowest velocity products.', 'href' => null, 'module' => 'inventory'],
                    ['key' => 'batch-valuation', 'title' => 'Batch Valuation', 'description' => 'Valuation by purchase batch.', 'href' => null, 'module' => 'inventory'],
                    ['key' => 'investor-owned-inventory', 'title' => 'Investor-Owned Inventory', 'description' => 'Stock still owned by investors.', 'href' => null, 'module' => 'investors'],
                ],
            ],
            [
                'key' => 'purchase',
                'title' => 'Purchase Reports',
                'description' => 'Purchasing history, suppliers, and outstanding buys.',
                'reports' => [
                    ['key' => 'purchase-overview', 'title' => 'Purchase Overview', 'description' => 'Purchase totals and trends.', 'href' => null, 'module' => 'purchases'],
                    ['key' => 'purchase-history', 'title' => 'Purchase History', 'description' => 'Detailed purchase ledger.', 'href' => null, 'module' => 'purchases'],
                    ['key' => 'purchases-by-supplier', 'title' => 'Purchases by Supplier', 'description' => 'Supplier spend analysis.', 'href' => null, 'module' => 'purchases'],
                    ['key' => 'purchases-by-product', 'title' => 'Purchases by Product', 'description' => 'Product purchasing volume.', 'href' => null, 'module' => 'purchases'],
                    ['key' => 'purchases-by-branch', 'title' => 'Purchases by Branch', 'description' => 'Branch purchasing comparison.', 'href' => null, 'module' => 'purchases'],
                    ['key' => 'purchase-payments', 'title' => 'Purchase Payments', 'description' => 'Payments made against purchases.', 'href' => null, 'module' => 'purchases'],
                    ['key' => 'outstanding-purchases', 'title' => 'Outstanding Purchases', 'description' => 'Unpaid or partially paid purchases.', 'href' => null, 'module' => 'purchases'],
                ],
            ],
            [
                'key' => 'investor',
                'title' => 'Investor Reports',
                'description' => 'Capital, profit allocation, withdrawals, and ownership.',
                'reports' => [
                    [
                        'key' => 'investor-overview',
                        'title' => 'Investor Overview',
                        'description' => 'Investor capital, profit, and inventory KPIs.',
                        'href' => '/investors/dashboard',
                        'module' => 'investors',
                    ],
                    ['key' => 'investor-statements', 'title' => 'Investor Statements', 'description' => 'Per-investor statement export.', 'href' => null, 'module' => 'investors'],
                    ['key' => 'capital-contributions', 'title' => 'Capital Contributions', 'description' => 'Capital inflows by investor.', 'href' => null, 'module' => 'investors'],
                    ['key' => 'profit-allocation', 'title' => 'Profit Allocation', 'description' => 'How profit was allocated.', 'href' => null, 'module' => 'investors'],
                    ['key' => 'earned-vs-withdrawn', 'title' => 'Earned vs Withdrawn Profit', 'description' => 'Earned profit compared to withdrawals.', 'href' => null, 'module' => 'investors'],
                    ['key' => 'outstanding-profit', 'title' => 'Outstanding Profit', 'description' => 'Profit still payable to investors.', 'href' => null, 'module' => 'investors'],
                    ['key' => 'withdrawals', 'title' => 'Withdrawals', 'description' => 'Withdrawal history and status.', 'href' => null, 'module' => 'investor-withdrawals'],
                    ['key' => 'investor-owned-batches', 'title' => 'Investor-Owned Batches', 'description' => 'Batches still tied to investors.', 'href' => null, 'module' => 'investors'],
                ],
            ],
            [
                'key' => 'customer',
                'title' => 'Customer Reports',
                'description' => 'Customer sales, receivables, and top accounts.',
                'reports' => [
                    ['key' => 'customer-overview', 'title' => 'Customer Overview', 'description' => 'Customer base snapshot.', 'href' => null, 'module' => 'customers'],
                    ['key' => 'customer-sales', 'title' => 'Customer Sales', 'description' => 'Sales by customer.', 'href' => null, 'module' => 'customers'],
                    ['key' => 'customer-receivables', 'title' => 'Customer Receivables', 'description' => 'Customer outstanding balances.', 'href' => null, 'module' => 'customers'],
                    ['key' => 'customer-outstanding', 'title' => 'Outstanding Balances', 'description' => 'Customers with due amounts.', 'href' => null, 'module' => 'customers'],
                    ['key' => 'top-customers', 'title' => 'Top Customers', 'description' => 'Highest-value customers.', 'href' => null, 'module' => 'customers'],
                ],
            ],
            [
                'key' => 'supplier',
                'title' => 'Supplier Reports',
                'description' => 'Supplier transactions, payments, and balances.',
                'reports' => [
                    ['key' => 'supplier-overview', 'title' => 'Supplier Overview', 'description' => 'Supplier base snapshot.', 'href' => null, 'module' => 'suppliers'],
                    ['key' => 'supplier-transactions', 'title' => 'Supplier Transactions', 'description' => 'Purchase and payment activity.', 'href' => null, 'module' => 'suppliers'],
                    ['key' => 'supplier-payments', 'title' => 'Supplier Payments', 'description' => 'Payments made to suppliers.', 'href' => null, 'module' => 'suppliers'],
                    ['key' => 'supplier-outstanding', 'title' => 'Outstanding Balances', 'description' => 'Amounts owed to suppliers.', 'href' => null, 'module' => 'suppliers'],
                ],
            ],
            [
                'key' => 'branch-staff',
                'title' => 'Branch & Staff Reports',
                'description' => 'Branch comparison and staff performance.',
                'reports' => [
                    ['key' => 'branch-performance', 'title' => 'Branch Performance', 'description' => 'Per-branch KPI summary.', 'href' => null, 'module' => 'reports'],
                    ['key' => 'branch-comparison', 'title' => 'Branch Comparison', 'description' => 'Side-by-side branch metrics.', 'href' => null, 'module' => 'reports'],
                    ['key' => 'branch-profitability', 'title' => 'Branch Profitability', 'description' => 'Profit by branch.', 'href' => null, 'module' => 'reports'],
                    ['key' => 'staff-performance', 'title' => 'Staff Performance', 'description' => 'Staff activity and results.', 'href' => null, 'module' => 'reports'],
                    ['key' => 'cashier-performance', 'title' => 'Cashier Performance', 'description' => 'Cashier sales and collections.', 'href' => null, 'module' => 'reports'],
                ],
            ],
        ];
    }
}
