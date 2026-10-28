<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'purchase_currency_id')) {
                    $table->foreignId('purchase_currency_id')->nullable()->after('exchange_rate')
                        ->constrained('currencies')->nullOnDelete();
                }
                if (! Schema::hasColumn('products', 'purchase_currency_code')) {
                    $table->string('purchase_currency_code', 10)->nullable()->after('purchase_currency_id');
                }
                if (! Schema::hasColumn('products', 'purchase_currency_symbol')) {
                    $table->string('purchase_currency_symbol', 16)->nullable()->after('purchase_currency_code');
                }
                if (! Schema::hasColumn('products', 'purchase_unit_amount')) {
                    $table->decimal('purchase_unit_amount', 18, 2)->nullable()->after('purchase_currency_symbol');
                }
                if (! Schema::hasColumn('products', 'purchase_line_amount')) {
                    $table->decimal('purchase_line_amount', 18, 2)->nullable()->after('purchase_unit_amount');
                }
                if (! Schema::hasColumn('products', 'rate_to_ledger')) {
                    $table->decimal('rate_to_ledger', 18, 8)->nullable()->after('purchase_line_amount');
                }
                if (! Schema::hasColumn('products', 'rate_kind')) {
                    $table->string('rate_kind', 20)->nullable()->after('rate_to_ledger');
                }
            });

            $this->backfillPurchaseSnapshots();
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (! Schema::hasColumn('sales', 'usd_to_ledger_rate')) {
                    $table->decimal('usd_to_ledger_rate', 18, 8)->nullable()->after('ledger_decimal_places');
                }
                if (! Schema::hasColumn('sales', 'usd_equivalent_amount')) {
                    $table->decimal('usd_equivalent_amount', 18, 2)->nullable()->after('usd_to_ledger_rate');
                }
                if (! Schema::hasColumn('sales', 'fx_rate_kind')) {
                    $table->string('fx_rate_kind', 20)->nullable()->after('usd_equivalent_amount');
                }
            });
        }

        if (Schema::hasTable('sale_payments')) {
            Schema::table('sale_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('sale_payments', 'currency_id')) {
                    $table->foreignId('currency_id')->nullable()->after('amount_afn')
                        ->constrained('currencies')->nullOnDelete();
                }
                if (! Schema::hasColumn('sale_payments', 'currency_code')) {
                    $table->string('currency_code', 10)->nullable()->after('currency_id');
                }
                if (! Schema::hasColumn('sale_payments', 'amount_currency')) {
                    $table->decimal('amount_currency', 18, 2)->nullable()->after('currency_code');
                }
                if (! Schema::hasColumn('sale_payments', 'rate_to_ledger')) {
                    $table->decimal('rate_to_ledger', 18, 8)->nullable()->after('amount_currency');
                }
                if (! Schema::hasColumn('sale_payments', 'rate_kind')) {
                    $table->string('rate_kind', 20)->nullable()->after('rate_to_ledger');
                }
            });
        }
    }

    public function down(): void
    {
        // Keep snapshot columns; dropping them would erase historical originals.
    }

    /**
     * Preserve historical purchase rates only when they already exist on the row.
     * Do not invent currencies, rates, or converted values for incomplete legacy data.
     */
    private function backfillPurchaseSnapshots(): void
    {
        if (! Schema::hasColumn('products', 'exchange_rate') || ! Schema::hasColumn('products', 'rate_to_ledger')) {
            return;
        }

        // Copy the immutable applied purchase rate into rate_to_ledger when present.
        DB::table('products')
            ->whereNotNull('variant_id')
            ->where('exchange_rate', '>', 0)
            ->whereNull('rate_to_ledger')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('products')->where('id', $row->id)->update([
                        'rate_to_ledger' => $row->exchange_rate,
                        'rate_kind' => 'transaction',
                    ]);
                }
            });

        // Only restore original USD money fields when both USD amount and rate already exist.
        if (! Schema::hasColumn('products', 'purchase_price_usd') || ! Schema::hasColumn('products', 'purchase_line_amount')) {
            return;
        }

        $usd = DB::table('currencies')->where('code', 'USD')->where('trash', false)->first();
        if (! $usd) {
            return;
        }

        DB::table('products')
            ->whereNotNull('variant_id')
            ->where('purchase_price_usd', '>', 0)
            ->where('exchange_rate', '>', 0)
            ->whereNull('purchase_line_amount')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($usd) {
                foreach ($rows as $row) {
                    $qty = max(1, (int) $row->quantity);
                    $line = (string) $row->purchase_price_usd;
                    $unit = number_format(((float) $line) / $qty, 2, '.', '');

                    DB::table('products')->where('id', $row->id)->update([
                        'purchase_currency_id' => $usd->id,
                        'purchase_currency_code' => 'USD',
                        'purchase_currency_symbol' => $usd->symbol,
                        'purchase_unit_amount' => $unit,
                        'purchase_line_amount' => $line,
                        'rate_to_ledger' => $row->exchange_rate,
                        'rate_kind' => 'transaction',
                    ]);
                }
            });
    }
};
