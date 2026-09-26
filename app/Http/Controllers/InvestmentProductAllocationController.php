<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestmentProductAllocationRequest;
use App\Models\Investment;
use App\Models\InvestmentProductAllocation;
use App\Models\ProductBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentProductAllocationController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $investmentId = $request->query('investment_id');
        $batchId      = $request->query('batch_id');
        $status       = $request->query('status');

        $allocations = InvestmentProductAllocation::query()
            ->with([
                'investment:id,investment_number,investor_id',
                'investment.investor:id,full_name',
                'batch:id,batch_code,branch_id',
                'batch.branch:id,name,code',
                'variant.product:id,name',
                'variant.product.brand:id,name',
            ])
            ->when($investmentId, fn ($q, $v) => $q->where('investment_id', $v))
            ->when($batchId, fn ($q, $v) => $q->where('batch_id', $v))
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('investment-product-allocations/index', [
            'allocations'  => $allocations,
            'investments'  => Investment::notTrashed()->select('id', 'investment_number')->orderByDesc('id')->limit(200)->get(),
            'filters'      => [
                'investment_id' => $investmentId,
                'batch_id'      => $batchId,
                'status'        => $status,
            ],
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(Request $request): Response
    {
        $batches = ProductBatch::query()
            ->with([
                'variant.product:id,name,brand_id',
                'variant.product.brand:id,name',
                'branch:id,name',
                'purchaseItem:id,name,invoice_number,line_total,exchange_rate',
            ])
            ->where('status', 'active')
            ->where('trash', false)
            ->orderByDesc('received_date')
            ->limit(100)
            ->get()
            ->each(function (ProductBatch $batch) {
                $rate = (float) ($batch->purchaseItem?->exchange_rate ?? 0);
                $costAfn = (float) $batch->unit_cost_afn;
                $batch->setAttribute('exchange_rate', $rate);
                $batch->setAttribute('unit_cost_usd', $rate > 0 ? round($costAfn / $rate, 2) : null);
            });

        $investments = Investment::query()
            ->with(['investor:id,full_name'])
            ->notTrashed()
            ->active()
            ->orderByDesc('id')
            ->get();

        return Inertia::render('investment-product-allocations/create', [
            'batches'         => $batches,
            'investments'     => $investments,
            'preInvestmentId' => $request->query('investment_id'),
            'preBatchId'      => $request->query('batch_id'),
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreInvestmentProductAllocationRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $data = $request->validated();

            $batch = ProductBatch::findOrFail($data['batch_id']);

            // بررسی: مجموع funded_quantity نباید از quantity batch بیشتر شود
            $alreadyFunded = InvestmentProductAllocation::where('batch_id', $batch->id)->sum('funded_quantity');
            $remainingInBatch = $batch->quantity - $alreadyFunded;

            if ($data['funded_quantity'] > $remainingInBatch) {
                throw new \Exception(
                    "Only {$remainingInBatch} units available in batch, but you tried to allocate {$data['funded_quantity']}."
                );
            }

            $data['remaining_quantity'] = $data['funded_quantity'];
            $data['sold_quantity']      = 0;
            $data['status']             = 'active';

            InvestmentProductAllocation::create($data);
        });

        return redirect()
            ->route('investment-product-allocations.index')
            ->with('success', 'Product allocation created successfully.');
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(InvestmentProductAllocation $allocation): Response
    {
        $allocation->load([
            'investment.investor:id,full_name,phone',
            'purchaseAllocation.purchase:id,name,invoice_number,line_total',
            'batch.branch:id,name',
            'variant.product:id,name',
            'variant.product.brand:id,name',
        ]);

        return Inertia::render('investment-product-allocations/show', [
            'allocation' => $allocation,
        ]);
    }

    /* =========================================================
     |  DESTROY
     ========================================================= */

    public function destroy(InvestmentProductAllocation $allocation): RedirectResponse
    {
        if ($allocation->sold_quantity > 0) {
            return back()->with('error', 'Cannot delete allocation with sold units.');
        }

        $allocation->delete();

        return redirect()
            ->route('investment-product-allocations.index')
            ->with('success', 'Allocation removed.');
    }
}