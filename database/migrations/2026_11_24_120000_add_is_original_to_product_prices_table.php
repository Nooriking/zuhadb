<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mark which product_prices row is the merchant-entered original currency
 * for each price_type. Equivalents (AFN/USD/AED) remain for PriceResolver;
 * only is_original=true drives catalog UI hydrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_prices')) {
            return;
        }

        Schema::table('product_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('product_prices', 'is_original')) {
                $table->boolean('is_original')->default(false)->after('is_active');
                $table->index(['variant_id', 'price_type', 'is_original'], 'pp_variant_type_original_idx');
            }
        });

        // Best-effort backfill: prefer non-AFN active base rows, else AFN.
        if (Schema::hasColumn('product_prices', 'is_original')) {
            $pairs = DB::table('product_prices')
                ->select('variant_id', 'price_type')
                ->where('is_active', true)
                ->whereNotNull('variant_id')
                ->groupBy('variant_id', 'price_type')
                ->get();

            foreach ($pairs as $pair) {
                $preferred = DB::table('product_prices')
                    ->where('variant_id', $pair->variant_id)
                    ->where('price_type', $pair->price_type)
                    ->where('is_active', true)
                    ->where('min_qty', 0)
                    ->where('currency_code', '!=', 'AFN')
                    ->orderByDesc('id')
                    ->first();

                if (! $preferred) {
                    $preferred = DB::table('product_prices')
                        ->where('variant_id', $pair->variant_id)
                        ->where('price_type', $pair->price_type)
                        ->where('is_active', true)
                        ->orderBy('min_qty')
                        ->orderByDesc('id')
                        ->first();
                }

                if (! $preferred) {
                    continue;
                }

                DB::table('product_prices')
                    ->where('variant_id', $pair->variant_id)
                    ->where('price_type', $pair->price_type)
                    ->update(['is_original' => false]);

                DB::table('product_prices')
                    ->where('id', $preferred->id)
                    ->update(['is_original' => true]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_prices') || ! Schema::hasColumn('product_prices', 'is_original')) {
            return;
        }

        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropIndex('pp_variant_type_original_idx');
            $table->dropColumn('is_original');
        });
    }
};
