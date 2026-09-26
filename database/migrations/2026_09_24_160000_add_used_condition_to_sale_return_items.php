<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sale_return_items MODIFY `condition` ENUM('good', 'damaged', 'expired', 'other', 'used') NOT NULL DEFAULT 'good'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sale_return_items MODIFY `condition` ENUM('good', 'damaged', 'expired', 'other') NOT NULL DEFAULT 'good'");
    }
};
