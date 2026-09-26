<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([
                'query'   => $q,
                'count'   => 0,
                'results' => [],
            ]);
        }

        /* ─── 1) Barcode Search ─── */
        $barcodeMatches = ProductBarcode::query()
            ->where('barcode', 'like', $this->like($q))
            ->with([
                'variant:id,product_id,volume_ml,concentration,sale_price_afn,purchase_price_afn,wholesale_price_afn',
                'variant.product:id,name,brand_id',
                'variant.product.brand:id,name',
            ])
            ->limit(5)
            ->get();

        /* ─── 2) Name Search ─── */
        $nameMatches = Product::query()
            ->whereNull('variant_id')
            ->where('trash', false)
            ->where(function ($sub) use ($q) {
                $sub->where('name', 'like', $this->like($q))
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $this->like($q)));
            })
            ->with([
                'brand:id,name',
                'variants' => fn ($v) => $v
                    ->where('trash', false)
                    ->with([
                        'barcodes:id,variant_id,barcode,barcode_type,is_primary,current_stock',
                    ]),
            ])
            ->limit(5)
            ->get();

        $results = collect();

        /* ─── Barcode Results ─── */
        foreach ($barcodeMatches as $bc) {
            $variant = $bc->variant;
            if (! $variant) {
                continue;
            }

            $stock = Product::where('variant_id', $variant->id)
                ->where('trash', false)
                ->sum('remaining_quantity');

            $results->push([
                'type'            => 'barcode',
                'product_id'      => $variant->product?->id,
                'product_name'    => $variant->product?->name,
                'brand_name'      => $variant->product?->brand?->name,
                'variant_id'      => $variant->id,
                'variant_label'   => $this->variantLabel($variant),
                'barcode'         => $bc->barcode,
                'barcode_type'    => $bc->barcode_type,
                'is_primary'      => (bool) $bc->is_primary,
                'purchase_price'  => (float) $variant->purchase_price_afn,
                'sale_price'      => (float) $variant->sale_price_afn,
                'wholesale_price' => (float) $variant->wholesale_price_afn,
                'stock'           => (int) $stock,
                'url_product'     => $variant->product ? "/products/{$variant->product->id}" : null,
                'url_inventory'   => "/inventory",
                'url_edit'        => $variant->product ? "/products/{$variant->product->id}/edit" : null,
            ]);
        }

        /* ─── Name Results ─── */
        foreach ($nameMatches as $product) {
            $totalStock = 0;
            foreach ($product->variants as $v) {
                $totalStock += (int) Product::where('variant_id', $v->id)
                    ->where('trash', false)
                    ->sum('remaining_quantity');
            }

            $firstVariant = $product->variants->first();
            $primaryBarcode = $firstVariant?->barcodes->firstWhere('is_primary', true)
                ?? $firstVariant?->barcodes->first();

            $results->push([
                'type'            => 'product',
                'product_id'      => $product->id,
                'product_name'    => $product->name,
                'brand_name'      => $product->brand?->name,
                'variant_id'      => $firstVariant?->id,
                'variant_label'   => $firstVariant ? $this->variantLabel($firstVariant) : null,
                'barcode'         => $primaryBarcode?->barcode,
                'barcode_type'    => $primaryBarcode?->barcode_type,
                'is_primary'      => (bool) $primaryBarcode?->is_primary,
                'purchase_price'  => (float) ($firstVariant?->purchase_price_afn ?? 0),
                'sale_price'      => (float) ($firstVariant?->sale_price_afn ?? 0),
                'wholesale_price' => (float) ($firstVariant?->wholesale_price_afn ?? 0),
                'stock'           => (int) $totalStock,
                'variants_count'  => $product->variants->count(),
                'url_product'     => "/products/{$product->id}",
                'url_inventory'   => "/inventory",
                'url_edit'        => "/products/{$product->id}/edit",
            ]);
        }

        $canSeeCost = $request->user()?->hasAnyPermission(['purchases.view', 'products.update']) ?? false;
        $canEdit = $request->user()?->hasPermission('products.update') ?? false;

        $results = $results
            ->unique(fn ($r) => ($r['type'] ?? '') . '-' . ($r['product_id'] ?? 0) . '-' . ($r['barcode'] ?? ''))
            ->take(10)
            ->map(function (array $row) use ($canSeeCost, $canEdit) {
                if (! $canSeeCost) {
                    $row['purchase_price'] = null;
                }
                if (! $canEdit) {
                    $row['url_edit'] = null;
                }

                return $row;
            })
            ->values();

        return response()->json([
            'query'   => $q,
            'count'   => $results->count(),
            'results' => $results,
        ]);
    }

    private function like(string $value): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        return '%'.$escaped.'%';
    }

    private function variantLabel($variant): string
    {
        $parts = [];
        if ($variant->volume_ml) {
            $parts[] = $variant->volume_ml . 'ml';
        }
        if ($variant->concentration) {
            $parts[] = strtoupper($variant->concentration);
        }
        return implode(' · ', $parts) ?: 'Variant';
    }
}