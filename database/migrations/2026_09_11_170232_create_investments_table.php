<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('investor_id')
                ->constrained('investors')
                ->cascadeOnDelete();

            $table->foreignId('parent_investment_id')
                ->nullable()
                ->constrained('investments')
                ->nullOnDelete()
                ->comment('If this is a renewal or extension of a previous investment');

            $table->string('investment_number')->unique();

            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();

            $table->decimal('exchange_rate', 18, 6)->default(1);

            // مبلغ سرمایه
            $table->decimal('capital_amount_currency', 18, 2);
            $table->decimal('capital_amount_afn', 18, 2);

            // موجودی قابل استفاده (بعد از تخصیص به خریدها کم می‌شود)
            $table->decimal('available_amount_currency', 18, 2)->default(0);
            $table->decimal('available_amount_afn', 18, 2)->default(0);

            // تخصیص یافته
            $table->decimal('allocated_amount_afn', 18, 2)->default(0);

            // سود
            $table->decimal('profit_amount_currency', 18, 2)->default(0);
            $table->decimal('profit_amount_afn', 18, 2)->default(0);

            // برداشت‌ها
            $table->decimal('withdrawn_amount_afn', 18, 2)->default(0);

            $table->date('investment_date');

            $table->enum('status', [
                'active',          // سرمایه فعال، هنوز تخصیص نیافته
                'partially_used',  // بخشی از پول به خرید تخصیص یافته
                'fully_used',      // تمام پول به خریدها تخصیص یافته
                'withdrawn',       // برداشت شده
                'closed',          // بسته شده (تمام فروش تمام شد)
            ])->default('active');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('trash')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index('investor_id');
            $table->index('parent_investment_id');
            $table->index('currency_id');
            $table->index('status');
            $table->index('trash');
            $table->index('investment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};