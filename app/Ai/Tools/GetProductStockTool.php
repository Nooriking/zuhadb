<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AiTool;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only product stock lookup for the AI assistant.
 * Uses inventories.available_quantity (ERP source of truth) and enforces branch scope.
 */
final class GetProductStockTool implements AiTool
{
    public const NAME = 'get_product_stock';

    public const MAX_CANDIDATES = 10;

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): string
    {
        return 'Look up real inventory stock for a product by name, SKU/product code, or barcode. '
            .'Optional branch_id limits results to one authorized branch; otherwise returns stock for all branches the user may access. '
            .'If multiple products match ambiguously, returns candidates for clarification instead of guessing. '
            .'Does not return costs, profits, FIFO, supplier, or investor data.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Product name keyword, SKU/product code, or barcode.',
                    'minLength' => 1,
                    'maxLength' => 100,
                ],
                'branch_id' => [
                    'type' => 'integer',
                    'description' => 'Optional branch ID. Must be a branch the authenticated user is authorized to view.',
                    'minimum' => 1,
                ],
            ],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    public function authorize(User $user): bool
    {
        return $user->hasPermission('inventory.view');
    }

    /**
     * @param  array{query?: string, branch_id?: int|null}  $arguments
     * @return array<string, mixed>
     */
    public function execute(User $user, array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        $requestedBranchId = array_key_exists('branch_id', $arguments) && $arguments['branch_id'] !== null
            ? (int) $arguments['branch_id']
            : null;

        $branchScope = $this->resolveAuthorizedBranchIds($user, $requestedBranchId);
        if (isset($branchScope['error'])) {
            return $branchScope;
        }

        /** @var list<int> $branchIds */
        $branchIds = $branchScope['branch_ids'];

        if ($query === '') {
            return [
                'match_type' => 'invalid_query',
                'message' => 'A non-empty product query is required.',
                'query' => $query,
                'items' => [],
            ];
        }

        if ($branchIds === []) {
            return [
                'match_type' => 'no_authorized_branches',
                'message' => 'No authorized branches are available for inventory lookup.',
                'query' => $query,
                'low_stock_threshold' => Inventory::LOW_STOCK_THRESHOLD,
                'items' => [],
            ];
        }

        $exactVariants = $this->findExactMatchVariants($query);

        if ($exactVariants->isNotEmpty()) {
            return $this->stockPayload(
                matchType: 'exact',
                query: $query,
                branchIds: $branchIds,
                variants: $exactVariants,
                message: null,
            );
        }

        $products = $this->fuzzySearchProducts($query);

        if ($products->isEmpty()) {
            return [
                'match_type' => 'not_found',
                'message' => 'No products matched that query.',
                'query' => $query,
                'branch_ids' => $branchIds,
                'low_stock_threshold' => Inventory::LOW_STOCK_THRESHOLD,
                'items' => [],
            ];
        }

        if ($products->count() > 1) {
            return [
                'match_type' => 'ambiguous',
                'message' => 'Multiple products matched. Ask the user to clarify with a more specific name, SKU, or barcode.',
                'query' => $query,
                'branch_ids' => $branchIds,
                'low_stock_threshold' => Inventory::LOW_STOCK_THRESHOLD,
                'candidates' => $products->map(fn (Product $product) => $this->candidateProduct($product))->values()->all(),
                'items' => [],
            ];
        }

        /** @var Product $product */
        $product = $products->first();
        $variants = $product->variants;

        if ($variants->isEmpty()) {
            return [
                'match_type' => 'not_found',
                'message' => 'The matched product has no active variants.',
                'query' => $query,
                'branch_ids' => $branchIds,
                'low_stock_threshold' => Inventory::LOW_STOCK_THRESHOLD,
                'items' => [],
            ];
        }

        return $this->stockPayload(
            matchType: 'product',
            query: $query,
            branchIds: $branchIds,
            variants: $variants,
            message: null,
        );
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

        // Mirror InventoryController: branch-scoped users are limited to their branch.
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

    /**
     * @return Collection<int, ProductVariant>
     */
    private function findExactMatchVariants(string $query): Collection
    {
        $barcodeMatches = ProductVariant::query()
            ->notTrashed()
            ->whereHas('product', fn (Builder $p) => $p->notTrashed())
            ->whereHas('barcodes', function (Builder $b) use ($query) {
                $b->where('barcode', $query)
                    ->where(function (Builder $status) {
                        $status->where('status', 'active')->orWhereNull('status');
                    });
            })
            ->with($this->variantRelations())
            ->orderBy('id')
            ->limit(self::MAX_CANDIDATES)
            ->get();

        if ($barcodeMatches->isNotEmpty()) {
            return $barcodeMatches;
        }

        return ProductVariant::query()
            ->notTrashed()
            ->whereHas('product', fn (Builder $p) => $p->notTrashed())
            ->where('sku', $query)
            ->with($this->variantRelations())
            ->orderBy('id')
            ->limit(self::MAX_CANDIDATES)
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    private function fuzzySearchProducts(string $query): Collection
    {
        $like = $this->like($query);

        return Product::query()
            ->with([
                'brand:id,name',
                'variants' => fn ($q) => $q
                    ->notTrashed()
                    ->select('id', 'product_id', 'name', 'sku', 'volume_ml', 'concentration')
                    ->with(['barcodes:id,variant_id,barcode,barcode_type,is_primary,status']),
            ])
            ->notTrashed()
            ->where(function (Builder $q) use ($like) {
                $q->where('products.name', 'like', $like)
                    ->orWhereHas('brand', function (Builder $b) use ($like) {
                        $b->where('name', 'like', $like)->where('trash', false);
                    })
                    ->orWhereHas('variants', function (Builder $v) use ($like) {
                        $v->where('trash', false)
                            ->where(function (Builder $inner) use ($like) {
                                $inner->where('sku', 'like', $like)
                                    ->orWhere('name', 'like', $like);
                            });
                    })
                    ->orWhereHas('barcodes', function (Builder $b) use ($like) {
                        $b->where('barcode', 'like', $like);
                    });
            })
            ->orderByDesc('created_at')
            ->limit(self::MAX_CANDIDATES)
            ->get();
    }

    /**
     * @param  list<int>  $branchIds
     * @param  Collection<int, ProductVariant>  $variants
     * @return array<string, mixed>
     */
    private function stockPayload(
        string $matchType,
        string $query,
        array $branchIds,
        Collection $variants,
        ?string $message,
    ): array {
        $variantIds = $variants->pluck('id')->map(fn ($id) => (int) $id)->all();

        $branches = Branch::query()
            ->whereIn('id', $branchIds)
            ->get(['id', 'name', 'code'])
            ->keyBy('id');

        $inventoryRows = Inventory::query()
            ->notTrashed()
            ->whereIn('variant_id', $variantIds)
            ->whereIn('branch_id', $branchIds)
            ->get(['variant_id', 'branch_id', 'available_quantity', 'quantity', 'reserved_quantity'])
            ->groupBy('variant_id');

        $items = [];

        foreach ($variants as $variant) {
            $product = $variant->product;
            if (! $product || $product->trash) {
                continue;
            }

            $primary = $variant->barcodes
                ->first(fn ($bc) => ($bc->status ?? 'active') === 'active' && $bc->is_primary)
                ?? $variant->barcodes->first(fn ($bc) => ($bc->status ?? 'active') === 'active');

            $byBranch = [];
            $rows = $inventoryRows->get($variant->id, collect())->keyBy('branch_id');

            foreach ($branchIds as $branchId) {
                $branch = $branches->get($branchId);
                if (! $branch) {
                    continue;
                }

                /** @var Inventory|null $row */
                $row = $rows->get($branchId);
                $available = $row ? (int) $row->available_quantity : 0;
                $hasRecord = $row !== null;

                $byBranch[] = [
                    'branch_id' => (int) $branch->id,
                    'branch_name' => $branch->name,
                    'branch_code' => $branch->code,
                    'stock_quantity' => $available,
                    'inventory_record_exists' => $hasRecord,
                    'stock_status' => $this->stockStatus($available),
                ];
            }

            $items[] = [
                'product_id' => (int) $product->id,
                'product_name' => $product->name,
                'brand' => $product->brand?->name,
                'product_status' => $product->status,
                'variant_id' => (int) $variant->id,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'barcode' => $primary?->barcode,
                'volume_ml' => $variant->volume_ml,
                'concentration' => $variant->concentration,
                'unit' => 'unit',
                'low_stock_threshold' => Inventory::LOW_STOCK_THRESHOLD,
                'branches' => $byBranch,
                'total_stock_quantity' => array_sum(array_column($byBranch, 'stock_quantity')),
            ];
        }

        return [
            'match_type' => $matchType,
            'message' => $message,
            'query' => $query,
            'branch_ids' => $branchIds,
            'low_stock_threshold' => Inventory::LOW_STOCK_THRESHOLD,
            'count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function candidateProduct(Product $product): array
    {
        return [
            'product_id' => (int) $product->id,
            'product_name' => $product->name,
            'brand' => $product->brand?->name,
            'product_status' => $product->status,
            'variants' => $product->variants->map(function (ProductVariant $variant) {
                $primary = $variant->barcodes
                    ->first(fn ($bc) => ($bc->status ?? 'active') === 'active' && $bc->is_primary)
                    ?? $variant->barcodes->first(fn ($bc) => ($bc->status ?? 'active') === 'active');

                return [
                    'variant_id' => (int) $variant->id,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'barcode' => $primary?->barcode,
                    'volume_ml' => $variant->volume_ml,
                    'concentration' => $variant->concentration,
                ];
            })->values()->all(),
        ];
    }

    private function stockStatus(int $available): string
    {
        if ($available <= 0) {
            return 'out_of_stock';
        }

        if ($available <= Inventory::LOW_STOCK_THRESHOLD) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    /**
     * @return array<string, mixed>
     */
    private function variantRelations(): array
    {
        return [
            'product:id,name,brand_id,status,trash',
            'product.brand:id,name',
            'barcodes:id,variant_id,barcode,barcode_type,is_primary,status',
        ];
    }

    private function like(string $value): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        return '%'.$escaped.'%';
    }
}
