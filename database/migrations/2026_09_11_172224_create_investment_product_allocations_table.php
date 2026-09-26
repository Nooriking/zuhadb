<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_product_allocations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('investment_id');

            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('variant_id');

            $table->integer('funded_quantity');
            $table->integer('sold_quantity')->default(0);
            $table->integer('remaining_quantity');

            $table->decimal('cost_per_unit_currency', 18, 2)->default(0);
            $table->decimal('cost_per_unit_afn', 18, 2)->default(0);

            $table->decimal('profit_per_unit_currency', 18, 2)->default(0);
            $table->decimal('profit_per_unit_afn', 18, 2)->default(0);

            $table->decimal('capital_recovered_currency', 18, 2)->default(0);
            $table->decimal('capital_recovered_afn', 18, 2)->default(0);

            $table->decimal('profit_earned_currency', 18, 2)->default(0);
            $table->decimal('profit_earned_afn', 18, 2)->default(0);

            $table->enum('status', ['active', 'sold_out', 'cancelled'])->default('active');

            $table->timestamps();

            // ✅ ایندکس‌ها با نام کوتاه (اجباری!)
            $table->index('investment_id', 'idx_ipa_inv');
           
            $table->index('batch_id', 'idx_ipa_batch');
            $table->index('variant_id', 'idx_ipa_variant');
            $table->index('status', 'idx_ipa_status');

            // یونیک با نام کوتاه
            $table->unique(['investment_id', 'batch_id'], 'inv_batch_unique');
        });
        
}
    public function down(): void
    {
        Schema::dropIfExists('investment_product_allocations');
    }
};  