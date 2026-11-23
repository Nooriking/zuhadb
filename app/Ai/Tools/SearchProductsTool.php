<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AiTool;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only product catalog search for the AI assistant.
 * Returns identity/descriptive fields only — never stock, costs, or profits.
 */
final class SearchProductsTool implements AiTool
{
    public const NAME = 'search_products';

    public const DEFAULT_LIMIT = 10;

    public const MAX_LIMIT = 25;

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): string
    {
        return 'Search the Zuha Perfume ERP product catalog by product name, brand, SKU/product code, or barcode. '
            .'Returns catalog identity fields only (no inventory quantities, purchase costs, profits, or sales data). '
            .'Use this whenever the user asks about products in the system.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Product name keyword, SKU/product code, or barcode. Optional when listing by brand only.',
                    'maxLength' => 100,
                ],
                'brand' => [
                    'type' => 'string',
                    'description' => 'Optional brand name filter (partial match).',
                    'maxLength' => 100,
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum products to return (1–'.self::MAX_LIMIT.', default '.self::DEFAULT_LIMIT.').',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function authorize(User $user): bool
    {
        return $user->hasPermission('products.view');
    }

    /**
     * @param  array{query?: string|null, brand?: string|null, limit?: int|null}  $arguments
     * @return array{count: int, limit: int, query: string|null, brand: string|null, products: list<array<string, mixed>>}
     */
    public function execute(User $user, array $arguments): array
    {
        $query = isset($arguments['query']) ? trim((string) $arguments['query']) : '';
        $brand = isset($arguments['brand']) ? trim((string) $arguments['brand']) : '';
        $limit = (int) ($arguments['limit'] ?? self::DEFAULT_LIMIT);
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        $productsQuery = Product::query()
            ->with([
                'brand:id,name',
                'variants' => fn ($q) => $q
                    ->notTrashed()
                    ->select('id', 'product_id', 'name', 'sku', 'volume_ml', 'concentration')
                    ->with(['barcodes:id,variant_id,barcode,barcode_type,is_primary,status']),
            ])
            ->notTrashed()
            ->orderByDesc('created_at');

        if ($brand !== '') {
            $like = $this->like($brand);
            $productsQuery->whereHas('brand', function (Builder $b) use ($like) {
                $b->where('name', 'like', $like)->where('trash', false);
            });
        }

        if ($query !== '') {
            $like = $this->like($query);
            $productsQuery->where(function (Builder $q) use ($like) {
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
            });
        }

        $rows = $productsQuery->limit($limit)->get();

        $products = $rows->map(function (Product $product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'display_name' => $product->display_name,
                'brand' => $product->brand?->name,
                'gender' => $product->gender,
                'status' => $product->status,
                'description' => $product->description,
                'variants' => $product->variants->map(function ($variant) {
                    $primary = $variant->barcodes->firstWhere('is_primary', true)
                        ?? $variant->barcodes->first();

                    return [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'sku' => $variant->sku,
                        'volume_ml' => $variant->volume_ml,
                        'concentration' => $variant->concentration,
                        'primary_barcode' => $primary?->barcode,
                        'barcodes' => $variant->barcodes
                            ->filter(fn ($bc) => ($bc->status ?? 'active') === 'active')
                            ->map(fn ($bc) => [
                                'barcode' => $bc->barcode,
                                'barcode_type' => $bc->barcode_type,
                                'is_primary' => (bool) $bc->is_primary,
                            ])
                            ->values()
                            ->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            'count' => count($products),
            'limit' => $limit,
            'query' => $query !== '' ? $query : null,
            'brand' => $brand !== '' ? $brand : null,
            'products' => $products,
        ];
    }

    private function like(string $value): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        return '%'.$escaped.'%';
    }
}
