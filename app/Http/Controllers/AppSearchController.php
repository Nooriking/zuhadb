<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);

        if (mb_strlen($q) < 2) {
            return response()->json([
                'query'    => $q,
                'pages'    => [],
                'products' => [],
            ]);
        }

        return response()->json([
            'query'    => $q,
            'pages'    => $this->pages($user, $q),
            'products' => $this->canSearchProducts($user) ? $this->products($user, $q) : [],
        ]);
    }

    private function pages(User $user, string $q): array
    {
        $needle = mb_strtolower($q);

        return collect($this->pageCatalog())
            ->filter(fn (array $page) => $user->canAccessModule($page['module']))
            ->filter(function (array $page) use ($needle) {
                return str_contains(mb_strtolower($page['title']), $needle)
                    || str_contains(mb_strtolower($page['category']), $needle);
            })
            ->take(8)
            ->map(fn (array $page) => [
                'title'    => $page['title'],
                'url'      => $page['url'],
                'category' => $page['category'],
            ])
            ->values()
            ->all();
    }

    private function products(User $user, string $q): array
    {
        $like = $this->like($q);
        $canSeeCost = $user->hasAnyPermission(['purchases.view', 'products.update']);
        $canOpenProduct = $user->canAccessModule('products');
        $canEdit = $user->hasPermission('products.update');

        $results = collect();

        $barcodes = ProductBarcode::query()
            ->where('barcode', 'like', $like)
            ->where('status', 'active')
            ->with([
                'variant:id,product_id,volume_ml,concentration,sale_price_afn,purchase_price_afn',
                'variant.product:id,name,brand_id',
                'variant.product.brand:id,name',
            ])
            ->limit(6)
            ->get();

        foreach ($barcodes as $barcode) {
            $variant = $barcode->variant;
            $product = $variant?->product;
            if (! $variant || ! $product) {
                continue;
            }

            $results->push($this->productRow(
                $user,
                $product->id,
                $product->name,
                $product->brand?->name,
                $variant,
                $barcode->barcode,
                $canSeeCost,
                $canOpenProduct,
                $canEdit,
            ));
        }

        $products = Product::query()
            ->whereNull('variant_id')
            ->where('trash', false)
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', $like));
            })
            ->with([
                'brand:id,name',
                'variants' => fn ($variants) => $variants->where('trash', false)->limit(1),
                'variants.barcodes' => fn ($barcodes) => $barcodes->where('status', 'active')->orderByDesc('is_primary'),
            ])
            ->limit(6)
            ->get();

        foreach ($products as $product) {
            $variant = $product->variants->first();
            $results->push($this->productRow(
                $user,
                $product->id,
                $product->name,
                $product->brand?->name,
                $variant,
                $variant?->barcodes->first()?->barcode,
                $canSeeCost,
                $canOpenProduct,
                $canEdit,
            ));
        }

        return $results
            ->unique(fn (array $row) => $row['product_id'].'-'.($row['barcode'] ?? ''))
            ->take(8)
            ->values()
            ->all();
    }

    private function productRow(
        User $user,
        int $productId,
        ?string $name,
        ?string $brand,
        $variant,
        ?string $barcode,
        bool $canSeeCost,
        bool $canOpenProduct,
        bool $canEdit,
    ): array {
        $stockQuery = Inventory::notTrashed();
        if ($variant) {
            $stockQuery->where('variant_id', $variant->id);
        } else {
            $stockQuery->whereRaw('1 = 0');
        }

        if ($user->branch_id && ! $user->isSuperAdmin() && ! $user->isAdmin()) {
            $stockQuery->where('branch_id', $user->branch_id);
        }

        return [
            'product_id'     => $productId,
            'product_name'   => $name,
            'brand_name'     => $brand,
            'variant_label'  => $variant ? $this->variantLabel($variant) : null,
            'barcode'        => $barcode,
            'sale_price'     => (float) ($variant?->sale_price_afn ?? 0),
            'purchase_price' => $canSeeCost ? (float) ($variant?->purchase_price_afn ?? 0) : null,
            'stock'          => (int) $stockQuery->sum('quantity'),
            'url'            => $canOpenProduct ? "/products/{$productId}" : null,
            'url_edit'       => $canEdit ? "/products/{$productId}/edit" : null,
        ];
    }

    private function canSearchProducts(User $user): bool
    {
        return $user->canAccessModule('products')
            || $user->canAccessModule('sales')
            || $user->canAccessModule('inventory');
    }

    private function like(string $value): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        return '%'.$escaped.'%';
    }

    private function variantLabel($variant): string
    {
        return implode(' · ', array_filter([
            $variant->volume_ml ? $variant->volume_ml.'ml' : null,
            $variant->concentration ? strtoupper($variant->concentration) : null,
        ])) ?: 'Variant';
    }

    private function pageCatalog(): array
    {
        return [
            ['title' => 'Dashboard', 'url' => '/dashboard', 'category' => 'Main', 'module' => 'dashboard'],
            ['title' => 'Branches', 'url' => '/branches', 'category' => 'Main', 'module' => 'branches'],
            ['title' => 'Users', 'url' => '/users', 'category' => 'Main', 'module' => 'users'],
            ['title' => 'Products', 'url' => '/products', 'category' => 'Products', 'module' => 'products'],
            ['title' => 'Brands', 'url' => '/brands', 'category' => 'Products', 'module' => 'brands'],
            ['title' => 'Inventory', 'url' => '/inventory', 'category' => 'Products', 'module' => 'inventory'],
            ['title' => 'Stock Transfers', 'url' => '/stock-transfers', 'category' => 'Products', 'module' => 'stock-transfers'],
            ['title' => 'Sales', 'url' => '/sales', 'category' => 'Sales', 'module' => 'sales'],
            ['title' => 'New Sale', 'url' => '/sales/create', 'category' => 'Sales', 'module' => 'sales'],
            ['title' => 'Sale Returns', 'url' => '/sale-returns', 'category' => 'Sales', 'module' => 'sale-returns'],
            ['title' => 'Receipts', 'url' => '/receipts', 'category' => 'Sales', 'module' => 'sales'],
            ['title' => 'Customers', 'url' => '/customers', 'category' => 'Customers', 'module' => 'customers'],
            ['title' => 'Customer Ledger', 'url' => '/customer-transactions', 'category' => 'Customers', 'module' => 'customers'],
            ['title' => 'Suppliers', 'url' => '/suppliers', 'category' => 'Sales', 'module' => 'suppliers'],
            ['title' => 'Investors', 'url' => '/investors', 'category' => 'Investments', 'module' => 'investors'],
            ['title' => 'Investments', 'url' => '/investments', 'category' => 'Investments', 'module' => 'investments'],
            ['title' => 'Currencies', 'url' => '/currencies', 'category' => 'Financial', 'module' => 'currencies'],
            ['title' => 'Expenses', 'url' => '/expenses', 'category' => 'Financial', 'module' => 'expenses'],
            ['title' => 'Settings', 'url' => '/app-settings', 'category' => 'Settings', 'module' => 'settings'],
        ];
    }
}
