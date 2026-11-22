<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_prices') || ! Schema::hasTable('product_variants')) {
            return;
        }

        $currencies = DB::table('currencies')
            ->whereIn('code', ['AFN', 'USD', 'AED'])
            ->where('trash', false)
            ->get()
            ->keyBy('code');

        $afn = $currencies->get('AFN');
        $usd = $currencies->get('USD');
        $aed = $currencies->get('AED');

        if (! $afn) {
            return;
        }

        $usdRate = $usd && (float) $usd->rate_to_default > 0
            ? (float) $usd->rate_to_default
            : 56000.0;
        $aedRate = $aed && (float) $aed->rate_to_default > 0
            ? (float) $aed->rate_to_default
            : 15250.0;

        $now = now();

        DB::table('product_variants')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($variants) use ($afn, $usd, $aed, $usdRate, $aedRate, $now) {
                foreach ($variants as $variant) {
                    $productId = (int) $variant->product_id;
                    $variantId = (int) $variant->id;

                    if ((float) $variant->sale_price_afn > 0) {
                        $this->upsertPrice(
                            $productId,
                            $variantId,
                            'sale',
                            (int) $afn->id,
                            'AFN',
                            (float) $variant->sale_price_afn,
                            $now
                        );
                    }

                    if ((float) $variant->purchase_price_afn > 0) {
                        $this->upsertPrice(
                            $productId,
                            $variantId,
                            'purchase',
                            (int) $afn->id,
                            'AFN',
                            (float) $variant->purchase_price_afn,
                            $now
                        );
                    }

                    if (
                        isset($variant->sale_amount)
                        && (float) $variant->sale_amount > 0
                        && ! empty($variant->sale_currency_id)
                    ) {
                        $saleCurrency = DB::table('currencies')->where('id', $variant->sale_currency_id)->first();
                        if ($saleCurrency) {
                            $this->upsertPrice(
                                $productId,
                                $variantId,
                                'sale',
                                (int) $saleCurrency->id,
                                (string) $saleCurrency->code,
                                (float) $variant->sale_amount,
                                $now
                            );
                        }
                    }

                    if (
                        isset($variant->purchase_amount)
                        && (float) $variant->purchase_amount > 0
                        && ! empty($variant->purchase_currency_id)
                    ) {
                        $purchaseCurrency = DB::table('currencies')->where('id', $variant->purchase_currency_id)->first();
                        if ($purchaseCurrency) {
                            $this->upsertPrice(
                                $productId,
                                $variantId,
                                'purchase',
                                (int) $purchaseCurrency->id,
                                (string) $purchaseCurrency->code,
                                (float) $variant->purchase_amount,
                                $now
                            );
                        }
                    }

                    if ((float) $variant->sale_price_afn > 0) {
                        $hasUsd = DB::table('product_prices')
                            ->where('variant_id', $variantId)
                            ->where('price_type', 'sale')
                            ->where('currency_code', 'USD')
                            ->where('min_qty', 0)
                            ->exists();

                        if (! $hasUsd && $usd) {
                            $this->upsertPrice(
                                $productId,
                                $variantId,
                                'sale',
                                (int) $usd->id,
                                'USD',
                                round((float) $variant->sale_price_afn / $usdRate, 4),
                                $now
                            );
                        }

                        $hasAed = DB::table('product_prices')
                            ->where('variant_id', $variantId)
                            ->where('price_type', 'sale')
                            ->where('currency_code', 'AED')
                            ->where('min_qty', 0)
                            ->exists();

                        if (! $hasAed && $aed) {
                            $this->upsertPrice(
                                $productId,
                                $variantId,
                                'sale',
                                (int) $aed->id,
                                'AED',
                                round((float) $variant->sale_price_afn / $aedRate, 4),
                                $now
                            );
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_prices')) {
            return;
        }

        // Backfill only — leave manually maintained rows alone on rollback.
        DB::table('product_prices')->where('min_qty', 0)->delete();
    }

    private function upsertPrice(
        int $productId,
        int $variantId,
        string $priceType,
        int $currencyId,
        string $currencyCode,
        float $amount,
        $now
    ): void {
        $existing = DB::table('product_prices')
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->where('price_type', $priceType)
            ->where('currency_id', $currencyId)
            ->where('min_qty', 0)
            ->first();

        $payload = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'price_type' => $priceType,
            'currency_id' => $currencyId,
            'currency_code' => $currencyCode,
            'amount' => $amount,
            'min_qty' => 0,
            'is_active' => 1,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('product_prices')->where('id', $existing->id)->update($payload);

            return;
        }

        $payload['created_at'] = $now;
        DB::table('product_prices')->insert($payload);
    }
};
