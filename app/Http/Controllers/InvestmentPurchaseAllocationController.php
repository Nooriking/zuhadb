<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestmentPurchaseAllocationRequest;
use App\Models\Investment;
use App\Models\InvestmentPurchaseAllocation;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentPurchaseAllocationController extends Controller
{
    /* =========================================================
     |  INDEX — لیست همه تخصیص‌ها
     ========================================================= */

    public function index(Request $request): Response
    {
        $investmentId = $request->query('investment_id');
        $purchaseId   = $request->query('purchase_id');

        $allocations = InvestmentPurchaseAllocation::query()
            ->with([
                'investment:id,investment_number,investor_id,capital_amount_afn,available_amount_afn',
                'investment.investor:id,full_name',
                'purchase:id,name,invoice_number,line_total,purchase_date',
                'creator:id,name',
            ])
            ->when($investmentId, fn ($q, $v) => $q->where('investment_id', $v))
            ->when($purchaseId, fn ($q, $v) => $q->where('purchase_id', $v))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('investment-purchase-allocations/index', [
            'allocations' => $allocations,
            'filters'     => [
                'investment_id' => $investmentId,
                'purchase_id'   => $purchaseId,
            ],
        ]);
    }

    /* =========================================================
     |  CREATE — فرم تخصیص
     ========================================================= */

    public function create(Request $request): Response
    {
        // Investments با موجودی قابل استفاده
        $availableInvestments = Investment::query()
            ->with(['investor:id,full_name'])
            ->notTrashed()
            ->whereIn('status', ['active', 'partially_used'])
            ->where('available_amount_afn', '>', 0)
            ->orderByDesc('investment_date')
            ->get();

        // Purchases (draft یا received) که به سرمایه‌گذاری نیاز دارند
        $availablePurchases = Product::query()
            ->with('supplier:id,name')
            ->whereNotNull('variant_id')
            ->where('trash', false)
            ->orderByDesc('purchase_date')
            ->limit(100)
            ->get()
            ->map(function (Product $product) {
                $rate = (float) ($product->exchange_rate ?? 0);
                $totalAfn = (float) $product->line_total;

                return [
                    'id'              => $product->id,
                    'purchase_number' => $product->invoice_number ?: ('ROW-'.$product->id),
                    'invoice_number'  => $product->invoice_number,
                    'total_afn'       => $totalAfn,
                    'exchange_rate'   => $rate,
                    'total_usd'       => $rate > 0 ? round($totalAfn / $rate, 2) : null,
                    'purchase_date'   => optional($product->purchase_date)->toDateString(),
                    'supplier'        => $product->supplier,
                ];
            });

        return Inertia::render('investment-purchase-allocations/create', [
            'investments'   => $availableInvestments,
            'purchases'     => $availablePurchases,
            'preInvestmentId' => $request->query('investment_id'),
            'prePurchaseId'   => $request->query('purchase_id'),
        ]);
    }

    /* =========================================================
     |  STORE — ذخیره تخصیص
     ========================================================= */

    public function store(StoreInvestmentPurchaseAllocationRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $data = $request->validated();

            $investment = Investment::lockForUpdate()->findOrFail($data['investment_id']);

            // بررسی موجودی
            $availableAfn = (float) $investment->available_amount_afn;
            $allocatedAfn = (float) $data['allocated_amount_afn'];

            if ($allocatedAfn > $availableAfn + 0.01) {
                throw new \Exception(
                    "Investment available balance is {$availableAfn} USD, but you tried to allocate {$allocatedAfn} USD."
                );
            }

            InvestmentPurchaseAllocation::create([
                'investment_id'        => $investment->id,
                'purchase_id'          => $data['purchase_id'],
                'allocated_amount_afn' => $allocatedAfn,
                'notes'                => $data['notes'] ?? null,
                'created_by'           => Auth::id(),
            ]);

            $investment->update([
                'available_amount_afn' => $availableAfn - $allocatedAfn,
                'allocated_amount_afn' => (float) $investment->allocated_amount_afn + $allocatedAfn,
                'user_id'              => Auth::id(),
            ]);

            $investment->refreshStatus();
        });

        return redirect()
            ->route('investment-purchase-allocations.index')
            ->with('success', 'Allocation created successfully.');
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(InvestmentPurchaseAllocation $allocation): Response
    {
        $allocation->load([
            'investment.investor:id,full_name,phone',
            'purchase:id,name,invoice_number,line_total,purchase_date,supplier_id',
            'purchase.supplier:id,name',
            'creator:id,name,email',
        ]);

        return Inertia::render('investment-purchase-allocations/show', [
            'allocation' => $allocation,
        ]);
    }

    /* =========================================================
     |  DESTROY — حذف تخصیص (فقط برای اصلاح)
     ========================================================= */

    public function destroy(InvestmentPurchaseAllocation $allocation): RedirectResponse
    {
        DB::transaction(function () use ($allocation) {
            $investment = Investment::lockForUpdate()->findOrFail($allocation->investment_id);

            $allocatedAfn = (float) $allocation->allocated_amount_afn;

            $investment->update([
                'available_amount_afn' => (float) $investment->available_amount_afn + $allocatedAfn,
                'allocated_amount_afn' => max(0, (float) $investment->allocated_amount_afn - $allocatedAfn),
                'user_id'                   => Auth::id(),
            ]);

            $investment->refreshStatus();

            $allocation->delete();
        });

        return redirect()
            ->route('investment-purchase-allocations.index')
            ->with('success', 'Allocation removed successfully.');
    }
}