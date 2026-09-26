<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockTransferRequest;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\ProductBatch;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $search   = $request->query('search');
        $branchId = $request->query('branch_id');
        $status   = $request->query('status');

        $transfers = StockTransfer::query()
            ->with([
                'fromBranch:id,name,code',
                'toBranch:id,name,code',
                'creator:id,name',
            ])
            ->withCount('items')
            ->notTrashed()
            ->when($search, function ($query, $search) {
                $query->where('transfer_number', 'like', "%{$search}%");
            })
            ->when($branchId, function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->where('from_branch_id', $v)->orWhere('to_branch_id', $v);
                });
            })
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('stock-transfers/index', [
            'transfers' => $transfers,
            'branches'  => Branch::notTrashed()->select('id', 'name', 'code')->get(),
            'filters'   => [
                'search'    => $search,
                'branch_id' => $branchId,
                'status'    => $status,
            ],
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(): Response
    {
        return Inertia::render('stock-transfers/create', [
            'branches' => Branch::notTrashed()->active()->select('id', 'name', 'code')->get(),
            'batches'  => ProductBatch::query()
                ->with([
                    'variant.product:id,name,brand_id',
                    'variant.product.brand:id,name',
                    'branch:id,name',
                ])
                ->where('status', 'active')
                ->where('remaining_quantity', '>', 0)
                ->where('trash', false)
                ->get(),
            'nextNumber' => StockTransfer::generateTransferNumber(),
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreStockTransferRequest $request): RedirectResponse
    {
        $transfer = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $items = $data['items'];
            unset($data['items']);

            $data['transfer_number'] = StockTransfer::generateTransferNumber();
            $data['created_by']      = Auth::id();
            $data['user_id']         = Auth::id();

            $transfer = StockTransfer::create($data);

            foreach ($items as $itemData) {
                $fromBatch = ProductBatch::findOrFail($itemData['from_batch_id']);

                if ($fromBatch->branch_id !== $transfer->from_branch_id) {
                    throw new \Exception('Batch does not belong to source branch.');
                }

                if ($fromBatch->remaining_quantity < $itemData['quantity']) {
                    throw new \Exception("Not enough stock in batch {$fromBatch->batch_code}.");
                }

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'from_batch_id'     => $itemData['from_batch_id'],
                    'variant_id'        => $fromBatch->variant_id,
                    'quantity'          => $itemData['quantity'],
                ]);
            }

            return $transfer;
        });

        return redirect()
            ->route('stock-transfers.show', $transfer->id)
            ->with('success', "Transfer \"{$transfer->transfer_number}\" created successfully.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(StockTransfer $stockTransfer): Response
    {
        if ($stockTransfer->trash) {
            abort(404);
        }

        $stockTransfer->load([
            'fromBranch:id,name,code',
            'toBranch:id,name,code',
            'creator:id,name,email',
            'items.fromBatch:id,batch_code,remaining_quantity,unit_cost_afn',
            'items.toBatch:id,batch_code',
            'items.variant.product:id,name',
            'items.variant.product.brand:id,name',
        ]);

        return Inertia::render('stock-transfers/show', [
            'transfer' => $stockTransfer,
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */

    public function edit(StockTransfer $stockTransfer): Response
    {
        if ($stockTransfer->trash) {
            abort(404);
        }

        if ($stockTransfer->status === 'completed') {
            return redirect()
                ->route('stock-transfers.show', $stockTransfer->id)
                ->with('error', 'Completed transfers cannot be edited.');
        }

        $stockTransfer->load('items');

        return Inertia::render('stock-transfers/edit', [
            'transfer' => $stockTransfer,
            'branches' => Branch::notTrashed()->active()->select('id', 'name', 'code')->get(),
            'batches'  => ProductBatch::query()
                ->with([
                    'variant.product:id,name,brand_id',
                    'variant.product.brand:id,name',
                ])
                ->where('status', 'active')
                ->where('remaining_quantity', '>', 0)
                ->where('trash', false)
                ->get(),
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        if ($stockTransfer->status === 'completed') {
            return back()->with('error', 'Completed transfers cannot be edited.');
        }

        $validated = $request->validate([
            'transfer_date' => ['required', 'date'],
            'status'        => ['required', 'in:draft,in_transit,completed,cancelled'],
            'notes'         => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['user_id'] = Auth::id();
        $stockTransfer->update($validated);

        return redirect()
            ->route('stock-transfers.show', $stockTransfer->id)
            ->with('success', "Transfer \"{$stockTransfer->transfer_number}\" updated successfully.");
    }

    /* =========================================================
     |  DESTROY
     ========================================================= */

    public function destroy(StockTransfer $stockTransfer): RedirectResponse
    {
        if ($stockTransfer->status === 'completed') {
            return back()->with('error', 'Completed transfers cannot be deleted.');
        }

        $number = $stockTransfer->transfer_number;

        $stockTransfer->update([
            'trash'   => true,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('stock-transfers.index')
            ->with('success', "Transfer \"{$number}\" was moved to trash.");
    }

    /* =========================================================
     |  RESTORE
     ========================================================= */

    public function restore(int $id): RedirectResponse
    {
        $transfer = StockTransfer::onlyTrashed()->findOrFail($id);

        $transfer->update([
            'trash'   => false,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('stock-transfers.index')
            ->with('success', "Transfer \"{$transfer->transfer_number}\" was restored.");
    }

    /* =========================================================
     |  FORCE DELETE
     ========================================================= */

    public function forceDelete(int $id): RedirectResponse
    {
        $transfer = StockTransfer::onlyTrashed()->findOrFail($id);
        $number = $transfer->transfer_number;
        $transfer->forceDelete();

        return redirect()
            ->route('stock-transfers.index')
            ->with('success', "Transfer \"{$number}\" was permanently deleted.");
    }

    /* =========================================================
     |  TRASH LIST
     ========================================================= */

    public function trash(): Response
    {
        $transfers = StockTransfer::onlyTrashed()
            ->with(['fromBranch:id,name', 'toBranch:id,name', 'creator:id,name'])
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('stock-transfers/trash', [
            'transfers' => $transfers,
        ]);
    }

    /* =========================================================
     |  MARK AS COMPLETED — انتقال واقعی موجودی
     ========================================================= */

    public function markAsCompleted(StockTransfer $stockTransfer): RedirectResponse
    {
        if ($stockTransfer->status === 'completed') {
            return back()->with('error', 'Already completed.');
        }

        if ($stockTransfer->status === 'cancelled') {
            return back()->with('error', 'Cancelled transfers cannot be completed.');
        }

        DB::transaction(function () use ($stockTransfer) {
            $stockTransfer->load('items');

            foreach ($stockTransfer->items as $item) {
                $fromBatch = ProductBatch::lockForUpdate()->findOrFail($item->from_batch_id);

                if ($fromBatch->remaining_quantity < $item->quantity) {
                    throw new \Exception("Not enough stock in batch {$fromBatch->batch_code}.");
                }

                // ۱. ساخت Batch جدید در شعبه مقصد
                $toBatch = ProductBatch::create([
                    'branch_id'          => $stockTransfer->to_branch_id,
                    'variant_id'         => $fromBatch->variant_id,
                    'purchase_item_id'   => null,
                    'batch_code'         => ProductBatch::generateBatchCode(),
                    'quantity'           => $item->quantity,
                    'remaining_quantity' => $item->quantity,
                    'unit_cost_afn'      => $fromBatch->unit_cost_afn,
                    'received_date'      => now(),
                    'status'             => 'active',
                    'user_id'            => Auth::id(),
                ]);

                // ۲. کم کردن از Batch مبدأ
                $fromBatch->update([
                    'remaining_quantity' => $fromBatch->remaining_quantity - $item->quantity,
                    'status'             => $fromBatch->remaining_quantity - $item->quantity <= 0 ? 'depleted' : 'active',
                ]);

                // ۳. ذخیره to_batch_id
                $item->update(['to_batch_id' => $toBatch->id]);

                // ۴. کم کردن از Inventory مبدأ
                Inventory::decrease(
                    branchId: $stockTransfer->from_branch_id,
                    variantId: $item->variant_id,
                    qty: $item->quantity,
                    type: 'transfer_out',
                    batchId: $fromBatch->id,
                    referenceType: StockTransfer::class,
                    referenceId: $stockTransfer->id,
                    notes: "Transferred out via {$stockTransfer->transfer_number} to {$stockTransfer->toBranch->name}"
                );

                // ۵. افزودن به Inventory مقصد
                Inventory::increase(
                    branchId: $stockTransfer->to_branch_id,
                    variantId: $item->variant_id,
                    qty: $item->quantity,
                    type: 'transfer_in',
                    batchId: $toBatch->id,
                    referenceType: StockTransfer::class,
                    referenceId: $stockTransfer->id,
                    notes: "Transferred in via {$stockTransfer->transfer_number} from {$stockTransfer->fromBranch->name}",
                    unitCostAfn: (float) $fromBatch->unit_cost_afn
                );
            }

            $stockTransfer->update([
                'status'  => 'completed',
                'user_id' => Auth::id(),
            ]);
        });

        return back()->with('success', 'Transfer completed. Inventory updated on both branches.');
    }

    /* =========================================================
     |  CANCEL
     ========================================================= */

    public function cancel(StockTransfer $stockTransfer): RedirectResponse
    {
        if ($stockTransfer->status === 'completed') {
            return back()->with('error', 'Completed transfers cannot be cancelled.');
        }

        $stockTransfer->update([
            'status'  => 'cancelled',
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Transfer cancelled.');
    }
}