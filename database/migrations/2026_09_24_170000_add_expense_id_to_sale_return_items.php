<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->foreignId('expense_id')
                ->nullable()
                ->after('reason')
                ->constrained('expenses')
                ->nullOnDelete();
        });

        $expenses = DB::table('expenses')
            ->where('description', 'like', '%sale_return_item:%')
            ->get(['id', 'description']);

        foreach ($expenses as $expense) {
            if (! preg_match('/sale_return_item:(\d+)/', (string) $expense->description, $matches)) {
                continue;
            }

            DB::table('sale_return_items')
                ->where('id', (int) $matches[1])
                ->whereNull('expense_id')
                ->update(['expense_id' => $expense->id]);
        }
    }

    public function down(): void
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
        });
    }
};
