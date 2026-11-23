<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\InvestorWalletBalance;
use App\Models\InvestorWithdrawal;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Period business report builder for ZUHA POS.
 *
 * All amounts are ledger AFN unless noted. Profit uses FIFO COGS already
 * snapshotted on sales (total_cost_afn / sale_items), never purchases − sales.
 */
class BusinessReportService
{
    public const SECTIONS = [
        'summary',
        'sales',
        'purchases',
        'profit',
        'expenses',
        'inventory',
        'best_sellers',
        'returns',
        'investors',
        'cash_flow',
        'suppliers',
        'customers',
    ];

    public const REPORT_PRESETS = [
        'full' => self::SECTIONS,
        'sales' => ['summary', 'sales'],
        'purchases' => ['summary', 'purchases'],
        'profit_expense' => ['summary', 'profit', 'expenses'],
        'inventory' => ['summary', 'inventory'],
        'best_sellers' => ['summary', 'best_sellers'],
        'returns' => ['summary', 'returns'],
        'investors' => ['summary', 'investors'],
        'cash_flow' => ['summary', 'cash_flow', 'suppliers', 'customers'],
        'suppliers_customers' => ['summary', 'suppliers', 'customers'],
    ];

    /**
     * @param  array{
     *   quick?: string|null,
     *   month?: int|string|null,
     *   year?: int|string|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   branch_id?: int|string|null,
     *   report?: string|null,
     *   include_all_products?: bool,
     *   include_all_parties?: bool,
     * }  $input
     * @return array{from: Carbon, to: Carbon, label: string, quick: string}
     */
    public function resolvePeriod(array $input): array
    {
        $quick = (string) ($input['quick'] ?? 'this_month');
        $tz = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        return match ($quick) {
            'this_month' => [
                'from' => $now->copy()->startOfMonth()->startOfDay(),
                'to' => $now->copy()->endOfMonth()->endOfDay(),
                'label' => $now->format('F Y'),
                'quick' => $quick,
            ],
            'last_month' => [
                'from' => $now->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                'to' => $now->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay(),
                'label' => $now->copy()->subMonthNoOverflow()->format('F Y'),
                'quick' => $quick,
            ],
            'this_year' => [
                'from' => $now->copy()->startOfYear()->startOfDay(),
                'to' => $now->copy()->endOfYear()->endOfDay(),
                'label' => 'Year '.$now->year,
                'quick' => $quick,
            ],
            'month' => $this->periodFromMonthYear($input, $tz),
            'custom' => $this->periodFromCustom($input, $tz),
            default => throw ValidationException::withMessages([
                'quick' => 'Invalid period filter. Use this_month, last_month, this_year, month, or custom.',
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(array $input, User $user): array
    {
        $period = $this->resolvePeriod($input);
        $from = $period['from'];
        $to = $period['to'];

        $branchId = $input['branch_id'] ?? null;
        if ($user->isBranchScoped()) {
            $branchId = $user->branch_id;
        }
        $branchId = $branchId !== null && $branchId !== '' ? (int) $branchId : null;

        $reportKey = (string) ($input['report'] ?? 'full');
        if (! isset(self::REPORT_PRESETS[$reportKey])) {
            throw ValidationException::withMessages([
                'report' => 'Invalid report type.',
            ]);
        }

        $sections = self::REPORT_PRESETS[$reportKey];
        $includeAllProducts = (bool) ($input['include_all_products'] ?? false);
        $includeAllParties = (bool) ($input['include_all_parties'] ?? false);

        $canViewProfit = $user->isSuperAdmin()
            || $user->isAdmin()
            || $user->isBranchManager()
            || $user->isAccountant()
            || $user->hasPermission('reports.view');

        $settings = AppSetting::current();
        $company = $settings?->display_name
            ?: ($settings?->company_name ?: (string) config('app.name', 'ZUHA'));
        $currency = (string) config('business.default_currency', 'AFN');

        $data = [
            'meta' => [
                'company' => $company,
                'currency' => $currency,
                'generated_at' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'period_label' => $period['label'],
                'quick' => $period['quick'],
                'report' => $reportKey,
                'report_label' => $this->reportLabel($reportKey),
                'branch_id' => $branchId,
                'branch_name' => $branchId
                    ? Branch::query()->whereKey($branchId)->value('name')
                    : 'All branches',
                'can_view_profit' => $canViewProfit,
                'formulas' => [
                    'net_sales' => 'SUM(sales.total_afn) for completed sales in period (post-return adjusted)',
                    'gross_sales' => 'Net sales + completed returns on those sales (no double-count)',
                    'cogs' => 'SUM(sales.total_cost_afn) — FIFO batch cost snapshotted at sale',
                    'gross_profit' => 'SUM(sales.total_profit_afn) = store_profit + investor_profit',
                    'net_profit_store' => 'store_profit − operating expenses in period',
                    'inventory_value' => 'remaining_qty × unit_cost_afn (FIFO batch cost)',
                ],
            ],
            'sections' => $sections,
        ];

        $salesBundle = in_array('sales', $sections, true)
            || in_array('summary', $sections, true)
            || in_array('profit', $sections, true)
            || in_array('cash_flow', $sections, true)
            ? $this->salesBundle($from, $to, $branchId, $canViewProfit)
            : null;

        $expenseBundle = in_array('expenses', $sections, true)
            || in_array('summary', $sections, true)
            || in_array('profit', $sections, true)
            || in_array('cash_flow', $sections, true)
            ? $this->expenseBundle($from, $to, $branchId)
            : null;

        $purchaseBundle = in_array('purchases', $sections, true)
            || in_array('summary', $sections, true)
            || in_array('cash_flow', $sections, true)
            ? $this->purchaseBundle($from, $to, $branchId)
            : null;

        $investorBundle = in_array('investors', $sections, true)
            || in_array('summary', $sections, true)
            || in_array('cash_flow', $sections, true)
            ? $this->investorBundle($from, $to)
            : null;

        $returnsBundle = in_array('returns', $sections, true)
            || in_array('summary', $sections, true)
            ? $this->returnsBundle($from, $to, $branchId)
            : null;

        if (in_array('summary', $sections, true)) {
            $data['summary'] = $this->buildSummary(
                $salesBundle,
                $purchaseBundle,
                $expenseBundle,
                $investorBundle,
                $returnsBundle,
                $branchId,
                $canViewProfit,
                $to,
            );
        }

        if (in_array('sales', $sections, true) && $salesBundle) {
            $data['sales'] = $salesBundle;
        }

        if (in_array('purchases', $sections, true) && $purchaseBundle) {
            $data['purchases'] = $purchaseBundle;
        }

        if (in_array('profit', $sections, true) && $salesBundle && $expenseBundle) {
            $data['profit'] = $this->buildProfit($salesBundle, $expenseBundle, $canViewProfit);
        }

        if (in_array('expenses', $sections, true) && $expenseBundle) {
            $data['expenses'] = $expenseBundle;
        }

        if (in_array('inventory', $sections, true)) {
            $data['inventory'] = $this->inventoryBundle($to, $branchId, $includeAllProducts);
        }

        if (in_array('best_sellers', $sections, true)) {
            $data['best_sellers'] = $this->bestSellersBundle($from, $to, $branchId, $canViewProfit, $includeAllProducts);
        }

        if (in_array('returns', $sections, true) && $returnsBundle) {
            $data['returns'] = $returnsBundle;
        }

        if (in_array('investors', $sections, true) && $investorBundle) {
            $data['investors'] = $investorBundle;
        }

        if (in_array('cash_flow', $sections, true)) {
            $data['cash_flow'] = $this->cashFlowBundle(
                $from,
                $to,
                $branchId,
                $salesBundle,
                $purchaseBundle,
                $expenseBundle,
                $investorBundle,
                $returnsBundle,
            );
        }

        if (in_array('suppliers', $sections, true)) {
            $data['suppliers'] = $this->suppliersBundle($from, $to, $includeAllParties);
        }

        if (in_array('customers', $sections, true)) {
            $data['customers'] = $this->customersBundle($from, $to, $branchId, $includeAllParties);
        }

        return $data;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function reportOptions(): array
    {
        return [
            ['value' => 'full', 'label' => 'Full Business Report'],
            ['value' => 'sales', 'label' => 'Sales Report'],
            ['value' => 'purchases', 'label' => 'Purchase Report'],
            ['value' => 'profit_expense', 'label' => 'Profit & Expense Report'],
            ['value' => 'inventory', 'label' => 'Inventory Report'],
            ['value' => 'best_sellers', 'label' => 'Best-Selling Products'],
            ['value' => 'returns', 'label' => 'Returns Report'],
            ['value' => 'investors', 'label' => 'Investor Report'],
            ['value' => 'cash_flow', 'label' => 'Financial & Cash Flow'],
            ['value' => 'suppliers_customers', 'label' => 'Suppliers & Customers'],
        ];
    }

    private function reportLabel(string $key): string
    {
        foreach ($this->reportOptions() as $opt) {
            if ($opt['value'] === $key) {
                return $opt['label'];
            }
        }

        return 'Business Report';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{from: Carbon, to: Carbon, label: string, quick: string}
     */
    private function periodFromMonthYear(array $input, string $tz): array
    {
        $month = (int) ($input['month'] ?? 0);
        $year = (int) ($input['year'] ?? 0);

        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            throw ValidationException::withMessages([
                'month' => 'Provide a valid month (1–12) and year.',
            ]);
        }

        $from = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfDay();
        $to = $from->copy()->endOfMonth()->endOfDay();

        return [
            'from' => $from,
            'to' => $to,
            'label' => $from->format('F Y'),
            'quick' => 'month',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{from: Carbon, to: Carbon, label: string, quick: string}
     */
    private function periodFromCustom(array $input, string $tz): array
    {
        $fromRaw = $input['date_from'] ?? null;
        $toRaw = $input['date_to'] ?? null;

        if (! $fromRaw || ! $toRaw) {
            throw ValidationException::withMessages([
                'date_from' => 'Custom range requires start and end dates.',
            ]);
        }

        try {
            $from = Carbon::parse((string) $fromRaw, $tz)->startOfDay();
            $to = Carbon::parse((string) $toRaw, $tz)->endOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'date_from' => 'Invalid date format.',
            ]);
        }

        if ($from->gt($to)) {
            throw ValidationException::withMessages([
                'date_from' => 'Start date must be on or before end date.',
            ]);
        }

        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages([
                'date_to' => 'Custom range cannot exceed 366 days.',
            ]);
        }

        return [
            'from' => $from,
            'to' => $to,
            'label' => $from->toDateString().' → '.$to->toDateString(),
            'quick' => 'custom',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salesBundle(Carbon $from, Carbon $to, ?int $branchId, bool $canViewProfit): array
    {
        $salesQ = $this->salesQuery($from, $to, $branchId);

        $row = (clone $salesQ)->selectRaw('
            COUNT(*) as txn_count,
            COALESCE(SUM(sales.total_afn), 0) as net_sales,
            COALESCE(SUM(sales.subtotal_afn), 0) as subtotal,
            COALESCE(SUM(sales.discount_afn), 0) as discounts,
            COALESCE(SUM(sales.total_cost_afn), 0) as cogs,
            COALESCE(SUM(sales.total_profit_afn), 0) as gross_profit,
            COALESCE(SUM(sales.store_profit_afn), 0) as store_profit,
            COALESCE(SUM(sales.total_investor_profit_afn), 0) as investor_profit,
            COALESCE(SUM(GREATEST(sales.remaining_due_afn, 0)), 0) as credit_outstanding
        ')->first();

        $saleIds = (clone $salesQ)->select('sales.id');

        $returnsOnSales = (float) SaleReturn::query()
            ->notTrashed()
            ->completed()
            ->whereIn('sale_id', $saleIds)
            ->sum('total_afn');

        $netSales = round((float) ($row->net_sales ?? 0), 2);
        $grossSales = round($netSales + $returnsOnSales, 2);
        $discounts = round((float) ($row->discounts ?? 0), 2);
        $txnCount = (int) ($row->txn_count ?? 0);

        $qtySold = (int) DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($q, $v) => $q->where('sales.branch_id', $v))
            ->sum('sale_items.quantity');

        $paidInPeriod = round((float) SalePayment::query()
            ->whereIn('sale_id', $saleIds)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_afn'), 2);

        $byMethod = SalePayment::query()
            ->whereIn('sale_id', $saleIds)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('payment_method, COALESCE(SUM(amount_afn), 0) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'method' => (string) ($r->payment_method ?: 'other'),
                'total' => round((float) $r->total, 2),
                'count' => (int) $r->count,
            ])
            ->values()
            ->all();

        $cashRow = collect($byMethod)->firstWhere('method', 'cash');
        $cashSales = round((float) ($cashRow['total'] ?? 0), 2);
        $creditSales = round((float) ($row->credit_outstanding ?? 0), 2);

        $byBranch = (clone $salesQ)
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->selectRaw('
                sales.branch_id,
                branches.name as branch_name,
                COUNT(*) as count,
                COALESCE(SUM(sales.total_afn), 0) as net_sales,
                COALESCE(SUM(sales.total_profit_afn), 0) as gross_profit
            ')
            ->groupBy('sales.branch_id', 'branches.name')
            ->orderByDesc('net_sales')
            ->get()
            ->map(fn ($r) => [
                'branch_id' => (int) $r->branch_id,
                'branch_name' => (string) $r->branch_name,
                'count' => (int) $r->count,
                'net_sales' => round((float) $r->net_sales, 2),
                'gross_profit' => $canViewProfit ? round((float) $r->gross_profit, 2) : null,
            ])
            ->values()
            ->all();

        return [
            'transaction_count' => $txnCount,
            'quantity_sold' => $qtySold,
            'gross_sales' => $grossSales,
            'discounts' => $discounts,
            'sales_returns' => round($returnsOnSales, 2),
            'net_sales' => $netSales,
            'average_sale' => $txnCount > 0 ? round($netSales / $txnCount, 2) : 0.0,
            'cash_collections' => $cashSales,
            'collections_total' => $paidInPeriod,
            'credit_outstanding' => $creditSales,
            'cogs' => $canViewProfit ? round((float) ($row->cogs ?? 0), 2) : null,
            'gross_profit' => $canViewProfit ? round((float) ($row->gross_profit ?? 0), 2) : null,
            'store_profit' => $canViewProfit ? round((float) ($row->store_profit ?? 0), 2) : null,
            'investor_profit' => $canViewProfit ? round((float) ($row->investor_profit ?? 0), 2) : null,
            'by_payment_method' => $byMethod,
            'by_branch' => $byBranch,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseBundle(Carbon $from, Carbon $to, ?int $branchId): array
    {
        // Purchases = product batches received/purchased in period (authoritative cost source).
        $batches = ProductBatch::query()
            ->where('trash', false)
            ->whereNull('deleted_at')
            ->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v));

        $summary = (clone $batches)->selectRaw('
            COUNT(*) as txn_count,
            COALESCE(SUM(quantity), 0) as total_qty,
            COALESCE(SUM(total_cost_afn), 0) as total_amount
        ')->first();

        $byStatus = (clone $batches)
            ->selectRaw('COALESCE(payment_status, "unpaid") as payment_status, COUNT(*) as count, COALESCE(SUM(total_cost_afn), 0) as total')
            ->groupBy('payment_status')
            ->get()
            ->map(fn ($r) => [
                'status' => (string) $r->payment_status,
                'count' => (int) $r->count,
                'total' => round((float) $r->total, 2),
            ])
            ->values()
            ->all();

        $paidRow = collect($byStatus)->firstWhere('status', 'paid');
        $paid = round((float) ($paidRow['total'] ?? 0), 2);
        $unpaid = round((float) collect($byStatus)
            ->filter(fn ($r) => in_array($r['status'], ['unpaid', 'partial', 'credit'], true))
            ->sum('total'), 2);

        $invoices = SupplierInvoice::query()
            ->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()]);

        $invoicePaid = round((float) (clone $invoices)->sum('paid_afn'), 2);
        $invoiceDue = round((float) (clone $invoices)->sum('due_afn'), 2);
        $invoiceTotal = round((float) (clone $invoices)->sum('total_afn'), 2);

        $bySupplier = SupplierInvoice::query()
            ->join('suppliers', 'supplier_invoices.supplier_id', '=', 'suppliers.id')
            ->whereBetween('supplier_invoices.purchase_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('
                suppliers.name as supplier_name,
                COALESCE(SUM(supplier_invoices.total_afn), 0) as total,
                COALESCE(SUM(supplier_invoices.paid_afn), 0) as paid,
                COALESCE(SUM(supplier_invoices.due_afn), 0) as due,
                COUNT(*) as count
            ')
            ->groupBy('suppliers.name')
            ->orderByDesc('total')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'supplier_name' => (string) $r->supplier_name,
                'total' => round((float) $r->total, 2),
                'paid' => round((float) $r->paid, 2),
                'due' => round((float) $r->due, 2),
                'count' => (int) $r->count,
            ])
            ->values()
            ->all();

        $byProduct = DB::table('product_batches as pb')
            ->join('product_variants as pv', 'pb.variant_id', '=', 'pv.id')
            ->join('products as p', 'pv.product_id', '=', 'p.id')
            ->where('pb.trash', false)
            ->whereNull('pb.deleted_at')
            ->whereBetween('pb.purchase_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($q, $v) => $q->where('pb.branch_id', $v))
            ->selectRaw('
                p.name as product_name,
                pv.sku,
                COALESCE(SUM(pb.quantity), 0) as qty,
                COALESCE(SUM(pb.total_cost_afn), 0) as total,
                COUNT(*) as batches
            ')
            ->groupBy('p.name', 'pv.sku')
            ->orderByDesc('total')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'product_name' => (string) $r->product_name,
                'sku' => (string) ($r->sku ?: '—'),
                'qty' => (int) $r->qty,
                'total' => round((float) $r->total, 2),
                'batches' => (int) $r->batches,
            ])
            ->values()
            ->all();

        return [
            'transaction_count' => (int) ($summary->txn_count ?? 0),
            'total_quantity' => (int) ($summary->total_qty ?? 0),
            'gross_purchases' => round((float) ($summary->total_amount ?? 0), 2),
            'purchase_discounts' => 0.0,
            'purchase_returns' => null,
            'purchase_returns_supported' => false,
            'net_purchases' => round((float) ($summary->total_amount ?? 0), 2),
            'paid_purchases_batch_status' => $paid,
            'unpaid_purchases_batch_status' => $unpaid,
            'supplier_invoices_total' => $invoiceTotal,
            'supplier_invoices_paid' => $invoicePaid,
            'supplier_invoices_due' => $invoiceDue,
            'by_status' => $byStatus,
            'by_supplier' => $bySupplier,
            'by_product' => $byProduct,
            'note' => 'Purchase value is inventory acquisition cost (FIFO batches), not an operating expense. Purchase returns are not supported in this POS.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expenseBundle(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $q = Expense::notTrashed()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($query, $v) => $query->where('branch_id', $v));

        $total = round((float) (clone $q)->sum('amount_afn'), 2);
        $count = (int) (clone $q)->count();

        $byCategory = (clone $q)
            ->selectRaw('COALESCE(category, "other") as category, COUNT(*) as count, COALESCE(SUM(amount_afn), 0) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'category' => Expense::CATEGORIES[$r->category] ?? (string) $r->category,
                'category_key' => (string) $r->category,
                'count' => (int) $r->count,
                'total' => round((float) $r->total, 2),
            ])
            ->values()
            ->all();

        $byDate = (clone $q)
            ->selectRaw('expense_date as date, COUNT(*) as count, COALESCE(SUM(amount_afn), 0) as total')
            ->groupBy('expense_date')
            ->orderBy('expense_date')
            ->get()
            ->map(fn ($r) => [
                'date' => (string) $r->date,
                'count' => (int) $r->count,
                'total' => round((float) $r->total, 2),
            ])
            ->values()
            ->all();

        $byBranch = Expense::query()
            ->from('expenses')
            ->where('expenses.trash', false)
            ->whereNull('expenses.deleted_at')
            ->whereBetween('expenses.expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($query, $v) => $query->where('expenses.branch_id', $v))
            ->leftJoin('branches', 'expenses.branch_id', '=', 'branches.id')
            ->selectRaw('expenses.branch_id, COALESCE(branches.name, "Unassigned") as branch_name, COUNT(*) as count, COALESCE(SUM(expenses.amount_afn), 0) as total')
            ->groupBy('expenses.branch_id', 'branches.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'branch_id' => $r->branch_id ? (int) $r->branch_id : null,
                'branch_name' => (string) $r->branch_name,
                'count' => (int) $r->count,
                'total' => round((float) $r->total, 2),
            ])
            ->values()
            ->all();

        $byMethod = (clone $q)
            ->selectRaw('COALESCE(payment_method, "other") as payment_method, COUNT(*) as count, COALESCE(SUM(amount_afn), 0) as total')
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'method' => Expense::PAYMENT_METHODS[$r->payment_method] ?? (string) $r->payment_method,
                'count' => (int) $r->count,
                'total' => round((float) $r->total, 2),
            ])
            ->values()
            ->all();

        return [
            'total' => $total,
            'count' => $count,
            'by_category' => $byCategory,
            'largest_categories' => array_slice($byCategory, 0, 5),
            'by_date' => $byDate,
            'by_branch' => $byBranch,
            'by_payment_method' => $byMethod,
        ];
    }

    /**
     * @param  array<string, mixed>  $sales
     * @param  array<string, mixed>  $expenses
     * @return array<string, mixed>
     */
    private function buildProfit(array $sales, array $expenses, bool $canViewProfit): array
    {
        if (! $canViewProfit) {
            return [
                'restricted' => true,
                'message' => 'Profit details require reports.view or finance role.',
            ];
        }

        $grossSales = (float) $sales['gross_sales'];
        $netSales = (float) $sales['net_sales'];
        $cogs = (float) ($sales['cogs'] ?? 0);
        $grossProfit = (float) ($sales['gross_profit'] ?? 0);
        $storeProfit = (float) ($sales['store_profit'] ?? 0);
        $investorProfit = (float) ($sales['investor_profit'] ?? 0);
        $opex = (float) $expenses['total'];
        $netStore = round($storeProfit - $opex, 2);

        return [
            'restricted' => false,
            'gross_sales' => $grossSales,
            'net_sales' => $netSales,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'investor_profit' => $investorProfit,
            'store_profit' => $storeProfit,
            'operating_expenses' => $opex,
            'other_income' => 0.0,
            'other_income_supported' => false,
            'net_profit_store' => $netStore,
            'formulas' => [
                'Gross Profit = Net Sales − COGS (FIFO snapshotted on sale)',
                'Store Profit = Gross Profit − Investor Profit allocation',
                'Net Profit (store) = Store Profit − Operating Expenses',
                'Purchases and investments are NOT expenses and are excluded from net profit',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryBundle(Carbon $asOf, ?int $branchId, bool $includeAll): array
    {
        $threshold = (int) config('business.default_low_stock_threshold', Inventory::LOW_STOCK_THRESHOLD);
        $end = $asOf->copy()->endOfDay();
        $isCurrent = $end->gte(now()->copy()->startOfDay());

        // Current batch remaining, adjusted by signed inventory_transactions after as-of date.
        $laterMoves = DB::table('inventory_transactions')
            ->selectRaw('batch_id, COALESCE(SUM(quantity), 0) as qty_after')
            ->whereNotNull('batch_id')
            ->where('created_at', '>', $end->toDateTimeString())
            ->groupBy('batch_id');

        $rows = DB::table('product_batches as pb')
            ->join('product_variants as pv', 'pb.variant_id', '=', 'pv.id')
            ->join('products as p', 'pv.product_id', '=', 'p.id')
            ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
            ->leftJoinSub($laterMoves, 'later', 'later.batch_id', '=', 'pb.id')
            ->where('pb.trash', false)
            ->whereNull('pb.deleted_at')
            ->where('pb.purchase_date', '<=', $asOf->toDateString())
            ->when($branchId, fn ($q, $v) => $q->where('pb.branch_id', $v))
            ->selectRaw('
                pb.variant_id,
                p.name as product_name,
                pv.sku,
                COALESCE(b.name, "—") as category,
                COALESCE(SUM(GREATEST(pb.remaining_quantity - COALESCE(later.qty_after, 0), 0)), 0) as qty,
                COALESCE(AVG(pb.unit_cost_afn), 0) as avg_cost,
                COALESCE(AVG(pb.sale_price_afn), 0) as avg_sale,
                COALESCE(SUM(GREATEST(pb.remaining_quantity - COALESCE(later.qty_after, 0), 0) * pb.unit_cost_afn), 0) as cost_value,
                COALESCE(SUM(GREATEST(pb.remaining_quantity - COALESCE(later.qty_after, 0), 0) * pb.sale_price_afn), 0) as retail_value
            ')
            ->groupBy('pb.variant_id', 'p.name', 'pv.sku', 'b.name')
            ->orderBy('p.name')
            ->get();

        $items = $rows->map(function ($r) {
            $qty = (int) $r->qty;
            $cost = round((float) $r->cost_value, 2);
            $retail = round((float) $r->retail_value, 2);

            return [
                'product_name' => (string) $r->product_name,
                'sku' => (string) ($r->sku ?: '—'),
                'category' => (string) $r->category,
                'quantity' => $qty,
                'cost_price' => round((float) $r->avg_cost, 2),
                'selling_price' => round((float) $r->avg_sale, 2),
                'inventory_value' => $cost,
                'retail_value' => $retail,
                'potential_profit' => round($retail - $cost, 2),
            ];
        })->filter(fn ($i) => $i['quantity'] > 0)->values();

        $outOfStock = DB::table('inventories as i')
            ->join('product_variants as pv', 'i.variant_id', '=', 'pv.id')
            ->join('products as p', 'pv.product_id', '=', 'p.id')
            ->where('i.trash', false)
            ->where('i.available_quantity', '<=', 0)
            ->when($branchId, fn ($q, $v) => $q->where('i.branch_id', $v))
            ->selectRaw('p.name as product_name, pv.sku, i.available_quantity as quantity')
            ->orderBy('p.name')
            ->limit($includeAll ? 500 : 25)
            ->get()
            ->map(fn ($r) => [
                'product_name' => (string) $r->product_name,
                'sku' => (string) ($r->sku ?: '—'),
                'quantity' => (int) $r->quantity,
            ])
            ->values()
            ->all();

        $lowStock = $items->filter(fn ($i) => $i['quantity'] > 0 && $i['quantity'] <= $threshold)
            ->take($includeAll ? 500 : 25)
            ->values()
            ->all();

        $listed = $includeAll ? $items : $items->take(100);
        $totalQty = (int) $items->sum('quantity');
        $totalCost = round((float) $items->sum('inventory_value'), 2);
        $totalRetail = round((float) $items->sum('retail_value'), 2);

        return [
            'as_of' => $asOf->toDateString(),
            'historical' => ! $isCurrent,
            'method' => $isCurrent
                ? 'Current remaining batch FIFO cost'
                : 'Batch remaining adjusted by inventory movements after period end',
            'total_quantity' => $totalQty,
            'total_cost_value' => $totalCost,
            'potential_sales_value' => $totalRetail,
            'estimated_potential_profit' => round($totalRetail - $totalCost, 2),
            'low_stock_count' => count($lowStock),
            'out_of_stock_count' => count($outOfStock),
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'items' => $listed->values()->all(),
            'items_truncated' => ! $includeAll && $items->count() > 100,
            'items_total' => $items->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bestSellersBundle(
        Carbon $from,
        Carbon $to,
        ?int $branchId,
        bool $canViewProfit,
        bool $includeAll,
    ): array {
        $limit = $includeAll ? 500 : 10;

        $sold = DB::table('sale_items as si')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->join('product_variants as pv', 'si.variant_id', '=', 'pv.id')
            ->join('products as p', 'pv.product_id', '=', 'p.id')
            ->where('s.trash', false)
            ->whereNull('s.deleted_at')
            ->where('s.status', 'completed')
            ->whereBetween('s.sale_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($q, $v) => $q->where('s.branch_id', $v))
            ->selectRaw('
                si.variant_id,
                p.name as product_name,
                pv.sku,
                COALESCE(SUM(si.quantity), 0) as qty_sold,
                COALESCE(SUM(si.line_total_afn), 0) as revenue,
                COALESCE(SUM(COALESCE(si.gross_profit_afn, si.store_profit_afn + si.investor_profit_afn)), 0) as profit
            ')
            ->groupBy('si.variant_id', 'p.name', 'pv.sku')
            ->get()
            ->keyBy('variant_id');

        $returned = DB::table('sale_return_items as sri')
            ->join('sale_returns as sr', 'sri.sale_return_id', '=', 'sr.id')
            ->where('sr.trash', false)
            ->whereNull('sr.deleted_at')
            ->where('sr.status', SaleReturn::STATUS_COMPLETED)
            ->whereBetween('sr.return_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($q, $v) => $q->where('sr.branch_id', $v))
            ->selectRaw('sri.variant_id, COALESCE(SUM(sri.quantity), 0) as qty_returned')
            ->groupBy('sri.variant_id')
            ->pluck('qty_returned', 'variant_id');

        $ranked = $sold->map(function ($row) use ($returned, $canViewProfit) {
            $ret = (int) ($returned[$row->variant_id] ?? 0);
            $soldQty = (int) $row->qty_sold;
            $net = $soldQty - $ret;

            return [
                'product_name' => (string) $row->product_name,
                'sku' => (string) ($row->sku ?: '—'),
                'quantity_sold' => $soldQty,
                'quantity_returned' => $ret,
                'net_quantity_sold' => $net,
                'sales_revenue' => round((float) $row->revenue, 2),
                'profit_contribution' => $canViewProfit ? round((float) $row->profit, 2) : null,
            ];
        })
            ->sortByDesc('net_quantity_sold')
            ->values();

        return [
            'ranked_by' => 'net_quantity_sold',
            'limit' => $limit,
            'products' => $ranked->take($limit)->values()->all(),
            'truncated' => ! $includeAll && $ranked->count() > $limit,
            'total_products' => $ranked->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function returnsBundle(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $q = SaleReturn::query()
            ->notTrashed()
            ->completed()
            ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($query, $v) => $query->where('branch_id', $v));

        $summary = (clone $q)->selectRaw('
            COUNT(*) as txn_count,
            COALESCE(SUM(total_afn), 0) as refund_total,
            COALESCE(SUM(refund_amount_afn), 0) as cash_refunded
        ')->first();

        $items = DB::table('sale_return_items as sri')
            ->join('sale_returns as sr', 'sri.sale_return_id', '=', 'sr.id')
            ->leftJoin('sales as s', 'sr.sale_id', '=', 's.id')
            ->leftJoin('product_variants as pv', 'sri.variant_id', '=', 'pv.id')
            ->leftJoin('products as p', 'pv.product_id', '=', 'p.id')
            ->where('sr.trash', false)
            ->whereNull('sr.deleted_at')
            ->where('sr.status', SaleReturn::STATUS_COMPLETED)
            ->whereBetween('sr.return_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($query, $v) => $query->where('sr.branch_id', $v))
            ->selectRaw('
                sr.return_number,
                sr.return_date,
                s.invoice_number,
                COALESCE(p.name, "Unknown") as product_name,
                sri.quantity,
                sri.condition,
                sri.reason,
                sri.total_afn,
                sri.cogs_reversal_afn,
                CASE WHEN sri.condition = "good" THEN sri.quantity ELSE 0 END as restocked_qty,
                CASE WHEN sri.condition != "good" THEN sri.quantity ELSE 0 END as non_restocked_qty
            ')
            ->orderByDesc('sr.return_date')
            ->limit(200)
            ->get();

        $qty = (int) $items->sum('quantity');
        $restocked = (int) $items->sum('restocked_qty');
        $nonRestocked = (int) $items->sum('non_restocked_qty');

        return [
            'transaction_count' => (int) ($summary->txn_count ?? 0),
            'returned_quantity' => $qty,
            'restocked_quantity' => $restocked,
            'non_restocked_quantity' => $nonRestocked,
            'refund_total' => round((float) ($summary->refund_total ?? 0), 2),
            'cash_refunded' => round((float) ($summary->cash_refunded ?? 0), 2),
            'cogs_reversed' => round((float) $items->sum('cogs_reversal_afn'), 2),
            'items' => $items->map(fn ($r) => [
                'return_number' => (string) $r->return_number,
                'return_date' => (string) $r->return_date,
                'invoice_number' => (string) ($r->invoice_number ?: '—'),
                'product_name' => (string) $r->product_name,
                'quantity' => (int) $r->quantity,
                'condition' => (string) ($r->condition ?: '—'),
                'reason' => (string) ($r->reason ?: '—'),
                'refund_amount' => round((float) $r->total_afn, 2),
                'restocked_qty' => (int) $r->restocked_qty,
                'non_restocked_qty' => (int) $r->non_restocked_qty,
            ])->values()->all(),
            'note' => 'Sale totals are already reduced on finalize — refund totals here are the return documents themselves (not added again to net sales).',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function investorBundle(Carbon $from, Carbon $to): array
    {
        $investors = Investor::query()
            ->where('trash', false)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        $periodTx = InvestorTransaction::query()
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('
                investor_id,
                COALESCE(SUM(CASE WHEN type IN ("capital_deposit","goods_deposit") THEN amount_afn ELSE 0 END), 0) as capital_in,
                COALESCE(SUM(CASE WHEN type = "capital_withdrawn" THEN ABS(amount_afn) ELSE 0 END), 0) as capital_out,
                COALESCE(SUM(CASE WHEN type = "profit_earned" THEN amount_afn ELSE 0 END), 0) as profit_earned,
                COALESCE(SUM(CASE WHEN type = "profit_withdrawn" THEN ABS(amount_afn) ELSE 0 END), 0) as profit_paid,
                COUNT(*) as txn_count
            ')
            ->groupBy('investor_id')
            ->get()
            ->keyBy('investor_id');

        $wallets = InvestorWalletBalance::query()
            ->selectRaw('
                investor_id,
                COALESCE(SUM(capital_balance_afn), 0) as capital_balance,
                COALESCE(SUM(profit_balance_afn), 0) as profit_balance
            ')
            ->groupBy('investor_id')
            ->get()
            ->keyBy('investor_id');

        $lifetimeInvested = Investment::query()
            ->notTrashed()
            ->selectRaw('investor_id, COALESCE(SUM(amount_afn), 0) as total')
            ->groupBy('investor_id')
            ->pluck('total', 'investor_id');

        $periodInvestments = Investment::query()
            ->notTrashed()
            ->whereBetween('investment_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('investor_id, COALESCE(SUM(amount_afn), 0) as total, COUNT(*) as count')
            ->groupBy('investor_id')
            ->get()
            ->keyBy('investor_id');

        $withdrawals = InvestorWithdrawal::query()
            ->whereBetween('withdrawal_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', ['approved', 'paid'])
            ->selectRaw('investor_id, COALESCE(SUM(amount_afn), 0) as total, COUNT(*) as count')
            ->groupBy('investor_id')
            ->get()
            ->keyBy('investor_id');

        $rows = $investors->map(function (Investor $inv) use (
            $periodTx,
            $wallets,
            $lifetimeInvested,
            $periodInvestments,
            $withdrawals,
        ) {
            $tx = $periodTx->get($inv->id);
            $wallet = $wallets->get($inv->id);
            $periodInv = $periodInvestments->get($inv->id);
            $wd = $withdrawals->get($inv->id);

            $capitalBalance = round((float) ($wallet->capital_balance ?? 0), 2);
            $profitBalance = round((float) ($wallet->profit_balance ?? 0), 2);
            $allocated = round((float) ($tx->profit_earned ?? 0), 2);
            $paid = round((float) ($tx->profit_paid ?? 0), 2);

            return [
                'investor_id' => $inv->id,
                'name' => (string) $inv->name,
                'status' => (string) ($inv->status ?: 'active'),
                'total_investment' => round((float) ($lifetimeInvested[$inv->id] ?? 0), 2),
                'period_investment' => round((float) ($periodInv->total ?? 0), 2),
                'period_investment_count' => (int) ($periodInv->count ?? 0),
                'investment_balance' => $capitalBalance,
                'profit_share_model' => 'Fixed profit per unit on investor-owned FIFO batches (not % of store P&L)',
                'allocated_profit_period' => $allocated,
                'profit_paid_period' => $paid,
                'remaining_profit_payable' => $profitBalance,
                'withdrawals_period' => round((float) ($wd->total ?? 0), 2),
                'withdrawal_count' => (int) ($wd->count ?? 0),
                'period_txn_count' => (int) ($tx->txn_count ?? 0),
            ];
        })->values();

        return [
            'investors' => $rows->all(),
            'combined' => [
                'investor_count' => $rows->count(),
                'total_investment' => round((float) $rows->sum('total_investment'), 2),
                'period_investment' => round((float) $rows->sum('period_investment'), 2),
                'investment_balance' => round((float) $rows->sum('investment_balance'), 2),
                'allocated_profit_period' => round((float) $rows->sum('allocated_profit_period'), 2),
                'profit_paid_period' => round((float) $rows->sum('profit_paid_period'), 2),
                'remaining_profit_payable' => round((float) $rows->sum('remaining_profit_payable'), 2),
                'withdrawals_period' => round((float) $rows->sum('withdrawals_period'), 2),
            ],
            'note' => 'Investment capital is not revenue or profit. Investor profit is allocated from sale-line FIFO ownership rules.',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $sales
     * @param  array<string, mixed>|null  $purchases
     * @param  array<string, mixed>|null  $expenses
     * @param  array<string, mixed>|null  $investors
     * @param  array<string, mixed>|null  $returns
     * @return array<string, mixed>
     */
    private function cashFlowBundle(
        Carbon $from,
        Carbon $to,
        ?int $branchId,
        ?array $sales,
        ?array $purchases,
        ?array $expenses,
        ?array $investors,
        ?array $returns,
    ): array {
        $salesReceipts = (float) ($sales['collections_total'] ?? 0);
        $purchasePayments = round((float) SupplierPayment::query()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount_afn'), 2);
        $expenseOut = (float) ($expenses['total'] ?? 0);
        $investorIn = (float) ($investors['combined']['period_investment'] ?? 0);
        $investorOut = (float) ($investors['combined']['withdrawals_period'] ?? 0)
            + (float) ($investors['combined']['profit_paid_period'] ?? 0);
        $refunds = (float) ($returns['cash_refunded'] ?? 0);

        $inflows = round($salesReceipts + $investorIn, 2);
        $outflows = round($purchasePayments + $expenseOut + $investorOut + $refunds, 2);
        $netCash = round($inflows - $outflows, 2);

        $receivables = round((float) Customer::notTrashed()->sum('current_due_afn'), 2);
        $payables = round((float) SupplierInvoice::query()->sum('due_afn'), 2)
            + round((float) Supplier::notTrashed()->sum('opening_balance'), 2);

        return [
            'opening_balance' => null,
            'opening_balance_tracked' => false,
            'cash_inflows' => $inflows,
            'cash_outflows' => $outflows,
            'sales_receipts' => $salesReceipts,
            'purchase_payments' => $purchasePayments,
            'expenses' => $expenseOut,
            'investor_contributions' => $investorIn,
            'investor_distributions' => $investorOut,
            'refunds' => $refunds,
            'net_cash_flow' => $netCash,
            'closing_cash_balance' => null,
            'closing_cash_tracked' => false,
            'accounts_receivable' => $receivables,
            'accounts_payable' => $payables,
            'note' => 'Cash flow uses actual receipts/payments in the period. Accrual profit is separate (see Profit section). Opening/closing cash drawer balances are not tracked in this POS.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function suppliersBundle(Carbon $from, Carbon $to, bool $includeAll): array
    {
        $limit = $includeAll ? 500 : 10;

        $purchases = SupplierInvoice::query()
            ->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('supplier_id, COALESCE(SUM(total_afn), 0) as purchases, COALESCE(SUM(paid_afn), 0) as paid, COALESCE(SUM(due_afn), 0) as due')
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        $payments = SupplierPayment::query()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('supplier_id, COALESCE(SUM(amount_afn), 0) as payments')
            ->groupBy('supplier_id')
            ->pluck('payments', 'supplier_id');

        $suppliers = Supplier::notTrashed()->get(['id', 'name', 'opening_balance']);

        $rows = $suppliers->map(function (Supplier $s) use ($purchases, $payments) {
            $p = $purchases->get($s->id);

            return [
                'supplier_name' => (string) $s->name,
                'purchases' => round((float) ($p->purchases ?? 0), 2),
                'payments' => round((float) ($payments[$s->id] ?? 0), 2),
                'outstanding_payable' => round((float) ($p->due ?? 0) + (float) $s->opening_balance, 2),
            ];
        })
            ->filter(fn ($r) => $r['purchases'] > 0 || $r['payments'] > 0 || $r['outstanding_payable'] > 0.009)
            ->sortByDesc('purchases')
            ->values();

        return [
            'top' => $rows->take($limit)->values()->all(),
            'truncated' => ! $includeAll && $rows->count() > $limit,
            'total_suppliers' => $rows->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customersBundle(Carbon $from, Carbon $to, ?int $branchId, bool $includeAll): array
    {
        $limit = $includeAll ? 500 : 10;

        $sales = Sale::query()
            ->where('trash', false)
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, COALESCE(SUM(total_afn), 0) as sales, COALESCE(SUM(paid_afn), 0) as paid_on_sale')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $payments = SalePayment::query()
            ->whereBetween('paid_at', [$from, $to])
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, COALESCE(SUM(amount_afn), 0) as payments')
            ->groupBy('customer_id')
            ->pluck('payments', 'customer_id');

        $customers = Customer::notTrashed()->get(['id', 'name', 'current_due_afn']);

        $rows = $customers->map(function (Customer $c) use ($sales, $payments) {
            $s = $sales->get($c->id);

            return [
                'customer_name' => (string) $c->name,
                'sales' => round((float) ($s->sales ?? 0), 2),
                'payments_received' => round((float) ($payments[$c->id] ?? 0), 2),
                'outstanding_receivable' => round((float) $c->current_due_afn, 2),
            ];
        })
            ->filter(fn ($r) => $r['sales'] > 0 || $r['payments_received'] > 0 || $r['outstanding_receivable'] > 0.009)
            ->sortByDesc('sales')
            ->values();

        return [
            'top' => $rows->take($limit)->values()->all(),
            'truncated' => ! $includeAll && $rows->count() > $limit,
            'total_customers' => $rows->count(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $sales
     * @param  array<string, mixed>|null  $purchases
     * @param  array<string, mixed>|null  $expenses
     * @param  array<string, mixed>|null  $investors
     * @param  array<string, mixed>|null  $returns
     * @return array<string, mixed>
     */
    private function buildSummary(
        ?array $sales,
        ?array $purchases,
        ?array $expenses,
        ?array $investors,
        ?array $returns,
        ?int $branchId,
        bool $canViewProfit,
        Carbon $asOf,
    ): array {
        $inventory = ProductBatch::valuationSummary($branchId);
        $receivables = round((float) Customer::notTrashed()->sum('current_due_afn'), 2);
        $payables = round((float) SupplierInvoice::query()->sum('due_afn'), 2);

        $storeProfit = (float) ($sales['store_profit'] ?? 0);
        $opex = (float) ($expenses['total'] ?? 0);
        $grossProfit = (float) ($sales['gross_profit'] ?? 0);
        $netProfit = round($storeProfit - $opex, 2);

        $salesReceipts = (float) ($sales['collections_total'] ?? 0);
        $purchasePayments = round((float) SupplierPayment::query()
            ->whereDate('payment_date', '<=', $asOf->toDateString())
            ->sum('amount_afn'), 2);
        // Period net cash for summary card uses period figures from bundles when present.
        $periodPurchasePay = round((float) ($purchases['supplier_invoices_paid'] ?? 0), 2);
        $investorIn = (float) ($investors['combined']['period_investment'] ?? 0);
        $investorOut = (float) ($investors['combined']['withdrawals_period'] ?? 0)
            + (float) ($investors['combined']['profit_paid_period'] ?? 0);
        $refunds = (float) ($returns['cash_refunded'] ?? 0);
        $netCash = round(
            $salesReceipts + $investorIn - $periodPurchasePay - $opex - $investorOut - $refunds,
            2
        );

        return [
            'total_sales' => (float) ($sales['net_sales'] ?? 0),
            'total_purchases' => (float) ($purchases['net_purchases'] ?? 0),
            'gross_profit' => $canViewProfit ? $grossProfit : null,
            'net_profit' => $canViewProfit ? $netProfit : null,
            'total_expenses' => $opex,
            'sales_returns' => (float) ($returns['refund_total'] ?? $sales['sales_returns'] ?? 0),
            'purchase_returns' => null,
            'purchase_returns_supported' => false,
            'total_investment' => (float) ($investors['combined']['period_investment'] ?? 0),
            'investor_profit' => $canViewProfit ? (float) ($sales['investor_profit'] ?? 0) : null,
            'net_cash_flow' => $netCash,
            'inventory_value' => round((float) $inventory['total_afn'], 2),
            'outstanding_receivables' => $receivables,
            'outstanding_payables' => $payables,
            'distinction' => [
                'profit' => 'Accrual: FIFO COGS on sold goods, minus investor allocation and expenses. Purchases/investments are not profit deductions.',
                'cash_flow' => 'Actual money movement: receipts, supplier payments, expenses, investor capital/profit payouts, refunds.',
            ],
            // silence unused warning for lifetime purchase payments helper
            'supplier_payments_to_date' => $purchasePayments,
        ];
    }

    private function salesQuery(Carbon $from, Carbon $to, ?int $branchId)
    {
        return Sale::query()
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($q, $v) => $q->where('sales.branch_id', $v));
    }
}
