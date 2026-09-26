<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','admin','branch_manager','cashier','accountant','investor','salesperson','stock_keeper') NOT NULL DEFAULT 'salesperson'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','admin','branch_manager','cashier','accountant','investor','salesperson') NOT NULL DEFAULT 'salesperson'");
    }
};
