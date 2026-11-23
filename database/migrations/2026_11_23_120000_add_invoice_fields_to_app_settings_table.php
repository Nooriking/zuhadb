<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('app_settings', 'tax_number')) {
                $table->string('tax_number', 50)->nullable()->after('address');
            }
            if (! Schema::hasColumn('app_settings', 'registration_no')) {
                $table->string('registration_no', 50)->nullable()->after('tax_number');
            }
            if (! Schema::hasColumn('app_settings', 'invoice_title')) {
                $table->string('invoice_title', 100)->nullable()->after('registration_no');
            }
            if (! Schema::hasColumn('app_settings', 'invoice_footer_text')) {
                $table->string('invoice_footer_text', 500)->nullable()->after('invoice_title');
            }
            if (! Schema::hasColumn('app_settings', 'thank_you_message')) {
                $table->string('thank_you_message', 255)->nullable()->after('invoice_footer_text');
            }
            if (! Schema::hasColumn('app_settings', 'invoice_show_logo')) {
                $table->boolean('invoice_show_logo')->default(true)->after('thank_you_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $columns = [
                'tax_number',
                'registration_no',
                'invoice_title',
                'invoice_footer_text',
                'thank_you_message',
                'invoice_show_logo',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('app_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
