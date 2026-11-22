<?php

use App\Support\ProductBatchPurchaseCurrencyBackfill;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill purchase_currency_id on legacy product_batches created before the slim schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        ProductBatchPurchaseCurrencyBackfill::run();
    }

    public function down(): void
    {
        // Irreversible data repair — intentional no-op.
    }
};
