<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AiTool;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\User;

/**
 * Read-only low-stock listing for the AI assistant.
 * Uses Inventory::LOW_STOCK_THRESHOLD and the same rules as InventoryController::lowStock.
 */
final class ListLowStockTool implements AiTool
{
    public const NAME = 'list_low_stock';

    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 50;

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): string
    {
        return 'List products currently at low stock using the ERP low-stock threshold '
            .'(available quantity greater than 0 and less than or equal to the configured threshold). '
            .'Optional branch_id limits results to one authorized branch. '
            .'Does not return costs, profits, FIFO, supplier, or investor data.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'branch_id' => [
                    'type' => 'integer',
                    'description' => 'Optional branch ID. Must be a branch the authenticated user is authorized to view.',
                    'minimum' => 1,
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum rows to return (1–'.self::MAX_LIMIT.', default '.self::DEFAULT_LIMIT.').',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function authorize(User $user): bool
    {
        return $user->hasPermission('inventory.view');
    }

    /**
     * @param  array{branch_id?: int|null, limit?: int|null}  $arguments
     * @return array<string, mixed>
     */
    public function execute(User $user, array $arguments): array
    {
        $requestedBranchId = array_key_exists('branch_id', $arguments) && $arguments['branch_id'] !== null
            ? (int) $arguments['branch_id']
            : null;

        $limit = (int) ($arguments['limit'] ?? self::DEFAULT_LIMIT);
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        $branchScope = $this->resolveAuthorizedBranchIds($user, $requestedBranchId);
        if (isset($branchScope['error'])) {
            return $branchScope;
        }

        /** @var list<int> $branchIds */
        $branchIds = $branchScope['branch_ids'];

        if ($branchIds === []) {
            return [
                'match_type' => 'no_authorized_branches',
                'message' => 'No authorized branches are available for low-stock listing.',
                'low_stock_threshold' => Inventory::LOW_STOCK_THRESHOLD,
                'threshold_source' => 'App\\Models\\Inventory::LOW_STOCK_THRESHOLD',
                'count' => 0,
                'limit' => $limit,
                'items' => [],
            ];
        }

        // Same business rule as InventoryController::lowStock / Inventory::scopeLowStock.
        $rows = Inventory::query()
            ->with([
                'branch:id,name,code',
                'variant:id,product_id,name,volume_ml,concentration,sku,trash',
                'variant.product:id,name,brand_id,status,trash',
                'variant.product.brand:id,name',
                'variant.barcodes:id,variant_id,barcode,barcode_type,is_primary,status',
            ])
            ->notTrashed()
            ->whereIn('branch_id', $branchIds)
            ->where('available_quantity', '>', 0)
            ->where('available_quantity', '<=', Inventory::LOW_STOCK_THRESHOLD)
            ->whereHas('variant', function ($q) {
                $q->where('trash', false)
                    ->whereHas('product', fn ($p) => $p->where('trash', false));
            })
            ->orderBy('available_quantity')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $items = $rows->map(function (Inventory $inv) {
            $variant = $inv->variant;
            $product = $variant?->product;
            $primary = $variant?->barcodes
                ?->first(fn ($bc) => ($bc->status ?? 'active') === 'active' && $bc->is_primary)
                ?? $variant?->barcodes?->first(fn ($bc) => ($bc->status ?? 'active') === 'active');

            $available = (int) $inv->available_quantity;

            return [
                'product_id' => $product ? (int) $product->id : null,
                'product_name' => $product?->name,
                'brand' => $product?->brand?->name,
                'product_status' => $product?->status,
                'variant_id' => $variant ? (int) $variant->id : null,
                'variant_name' => $variant?->name,
                'sku' => $variant?->sku,
                'barcode' => $primary?->barcode,
                'volume_ml' => $variant?->volume_ml,
                'concentration' => $variant?->concentration,
                'branch_id' => (int) $inv->branch_id,
                'branch_name' => $inv->branch?->name,
                'branch_code' => $inv->branch?->code,
                'stock_quantity' => $available,
                'reorder_threshold' => Inventory::LOW_STOCK_THRESHOLD,
                'stock_status' => 'low_stock',
                'unit' => 'unit',
            ];
        })->values()->all();

        return [
            'match_type' => 'low_stock',
            'message' => null,
            'low_stock_threshold' => Inventory::LOW_STOCK_THRESHOLD,
            'threshold_source' => 'App\\Models\\Inventory::LOW_STOCK_THRESHOLD',
            'branch_ids' => $branchIds,
            'count' => count($items),
            'limit' => $limit,
            'items' => $items,
        ];
    }

    /**
     * @return array{branch_ids: list<int>}|array{error: string, message: string, match_type: string, items: list<empty>}
     */
    private function resolveAuthorizedBranchIds(User $user, ?int $requestedBranchId): array
    {
        $visibleIds = Branch::query()
            ->notTrashed()
            ->visibleTo($user)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($requestedBranchId !== null) {
            if (! $visibleIds->contains($requestedBranchId)) {
                return [
                    'error' => 'unauthorized_branch',
                    'match_type' => 'unauthorized_branch',
                    'message' => 'You are not authorized to view inventory for that branch.',
                    'items' => [],
                ];
            }

            return ['branch_ids' => [$requestedBranchId]];
        }

        if ($user->isBranchScoped() && $user->branch_id) {
            $ownId = (int) $user->branch_id;
            if (! $visibleIds->contains($ownId)) {
                return [
                    'error' => 'unauthorized_branch',
                    'match_type' => 'unauthorized_branch',
                    'message' => 'You are not authorized to view inventory for that branch.',
                    'items' => [],
                ];
            }

            return ['branch_ids' => [$ownId]];
        }

        return ['branch_ids' => $visibleIds->all()];
    }
}
