<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * ✅ جستجوی محصولات بر اساس name یا barcode
     */
    public function products(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([
                'query'   => $q,
                'results' => [],
            ]);
        }

        /* ─────────────────────────────────────────────
         |  1) جستجو در barcode ها
         ───────────────────────────────────────────── */
        $barcodeMatches = ProductBarcode::query()
            ->where('barcode', 'like', "%{$q}%")
            ->with([
                'variant:id,product_id,volume_ml,concentration,stock_quantity,sale_price_afn,purchase_price_afn,wholesale_price_afn',
                'variant.product:id,name,brand_id,gender',
                'variant.product.brand:id,name',
            ])
            ->limit(5)
            ->get();

        /* ─────────────────────────────────────────────
         |  2) جستجو در نام محصولات
         ───────────────────────────────────────────── */
        $nameMatches = Product::query()
            ->whereNull('variant_id')
            ->where('trash', false)
            ->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$q}%"));
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

        /* ─────────────────────────────────────────────
         |  3) ساخت نتایج یکپارچه
         ───────────────────────────────────────────── */
        $results = collect();

        // Barcode matches (per barcode + variant)
        foreach ($barcodeMatches as $bc) {
            $variant = $bc->variant;
            if (! $variant) continue;

            $stock = Product::where('variant_id', $variant->id)
                ->where('trash', false)
                ->sum('remaining_quantity');

            $results->push([
                'type'            => 'barcode',
                'product_id'      => $variant->product?->id,
                'product_name'    => $variant->product?->name,
                'brand_name'      => $variant->product?->brand?->name,
                'variant_id'      => $variant->id,
                'variant_label'   => trim(implode(' · ', array_filter([
                    $variant->volume_ml ? "{$variant->volume_ml}ml" : null,
                    $variant->concentration ? strtoupper($variant->concentration) : null,
                ]))),
                'barcode'         => $bc->barcode,
                'barcode_type'    => $bc->barcode_type,
                'is_primary'      => $bc->is_primary,
                'purchase_price'  => (float) $variant->purchase_price_afn,
                'sale_price'      => (float) $variant->sale_price_afn,
                'wholesale_price' => (float) $variant->wholesale_price_afn,
                'stock'           => (int) $stock,
                'image'           => $variant->image,
                'url_product'     => $variant->product ? "/products/{$variant->product->id}" : null,
                'url_inventory'   => "/inventory/variant/{$variant->id}",
                'url_edit'        => $variant->product ? "/products/{$variant->product->id}/edit" : null,
            ]);
        }

        // Name matches
        foreach ($nameMatches as $product) {
            $totalStock = $product->variants->sum(function ($v) {
                return Product::where('variant_id', $v->id)
                    ->where('trash', false)
                    ->sum('remaining_quantity');
            });

            $firstVariant = $product->variants->first();
            $primaryBarcode = $firstVariant?->barcodes->firstWhere('is_primary', true)
                ?? $firstVariant?->barcodes->first();

            $results->push([
                'type'            => 'product',
                'product_id'      => $product->id,
                'product_name'    => $product->name,
                'brand_name'      => $product->brand?->name,
                'variant_id'      => $firstVariant?->id,
                'variant_label'   => $firstVariant
                    ? trim(implode(' · ', array_filter([
                        $firstVariant->volume_ml ? "{$firstVariant->volume_ml}ml" : null,
                        $firstVariant->concentration ? strtoupper($firstVariant->concentration) : null,
                    ])))
                    : null,
                'barcode'         => $primaryBarcode?->barcode,
                'barcode_type'    => $primaryBarcode?->barcode_type,
                'is_primary'      => (bool) $primaryBarcode?->is_primary,
                'purchase_price'  => (float) ($firstVariant?->purchase_price_afn ?? 0),
                'sale_price'      => (float) ($firstVariant?->sale_price_afn ?? 0),
                'wholesale_price' => (float) ($firstVariant?->wholesale_price_afn ?? 0),
                'stock'           => (int) $totalStock,
                'image'           => $firstVariant?->image,
                'variants_count'  => $product->variants->count(),
                'url_product'     => "/products/{$product->id}",
                'url_inventory'   => $firstVariant ? "/inventory/variant/{$firstVariant->id}" : "/inventory",
                'url_edit'        => "/products/{$product->id}/edit",
            ]);
        }

        // حذف تکراری‌ها و ترتیب
        $results = $results
            ->unique(fn ($r) => $r['type'] . '-' . ($r['product_id'] ?? 0) . '-' . ($r['barcode'] ?? ''))
            ->take(10)
            ->values();

        return response()->json([
            'query'   => $q,
            'count'   => $results->count(),
            'results' => $results,
        ]);
    }
}