<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10);
                $table->string('name');
                $table->string('symbol', 16)->nullable();
                $table->unsignedTinyInteger('decimal_places')->default(2);
                $table->decimal('rate_to_default', 18, 8)->default(1);
                $table->boolean('is_base')->default(false);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->boolean('trash')->default(false);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique('code');
                $table->index('status');
                $table->index('trash');
                $table->index('is_base');
            });
        } else {
            Schema::table('currencies', function (Blueprint $table) {
                if (! Schema::hasColumn('currencies', 'decimal_places')) {
                    $table->unsignedTinyInteger('decimal_places')->default(2)->after('symbol');
                }
                if (! Schema::hasColumn('currencies', 'rate_to_default')) {
                    $table->decimal('rate_to_default', 18, 8)->default(1)->after('decimal_places');
                }
            });
        }

        $exists = DB::table('currencies')->where('code', 'AFN')->exists();

        if (! $exists) {
            DB::table('currencies')->insert([
                'code' => 'AFN',
                'name' => 'Afghan Afghani',
                'symbol' => '؋',
                'decimal_places' => 2,
                'rate_to_default' => 1,
                'is_base' => true,
                'status' => 'active',
                'trash' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif (! DB::table('currencies')->where('is_base', true)->where('trash', false)->exists()) {
            DB::table('currencies')
                ->where('code', 'AFN')
                ->update([
                    'is_base' => true,
                    'status' => 'active',
                    'rate_to_default' => 1,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Keep the catalog if this migration is rolled back after data exists.
    }
};
