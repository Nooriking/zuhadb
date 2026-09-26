<?php

namespace App\Http\Controllers;

use App\Models\ProductPriceHistory;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductPriceHistoryController extends Controller
{
    /* =========================================================
     |  INDEX — همه تغییرات قیمت
     ========================================================= */

    public function index(Request $request): Response
    {
        $variantId = $request->query('variant_id');
        $changeType = $request->query('change_type');
        $from = $request->query('from');
        $to = $request->query('to');

        $histories = ProductPriceHistory::query()
            ->with([
                'variant.product:id,name,brand_id',
                'variant.product.brand:id,name',
                'changer:id,name',
            ])
            ->when($variantId, fn ($q, $v) => $q->where('variant_id', $v))
            ->when($changeType, fn ($q, $v) => $q->where('change_type', $v))
            ->when($from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('product-price-histories/index', [
            'histories' => $histories,
            'filters'   => [
                'variant_id'  => $variantId,
                'change_type' => $changeType,
                'from'        => $from,
                'to'          => $to,
            ],
        ]);
    }

    /* =========================================================
     |  BY VARIANT — تاریخچه یک واریانت
     ========================================================= */

    public function byVariant(ProductVariant $variant): Response
    {
        $variant->load('product.brand:id,name');

        $histories = ProductPriceHistory::query()
            ->with(['changer:id,name'])
            ->where('variant_id', $variant->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        return Inertia::render('product-price-histories/by-variant', [
            'variant'   => $variant,
            'histories' => $histories,
        ]);
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(ProductPriceHistory $history): Response
    {
        $history->load([
            'variant.product:id,name,brand_id',
            'variant.product.brand:id,name',
            'changer:id,name,email',
        ]);

        return Inertia::render('product-price-histories/show', [
            'history' => $history,
        ]);
    }
}