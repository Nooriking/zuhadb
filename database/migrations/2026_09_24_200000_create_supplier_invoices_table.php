<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('invoice_no', 50);
            $table->date('purchase_date');
            $table->decimal('exchange_rate', 10, 2)->default(0);
            $table->decimal('total_afn', 15, 2)->default(0);
            $table->decimal('paid_afn', 15, 2)->default(0);
            $table->decimal('due_afn', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'purchase_date']);
            $table->index('due_afn');
        });

        $rows = DB::table('products')
            ->whereNotNull('variant_id')
            ->whereNotNull('supplier_id')
            ->where('trash', false)
            ->whereIn('payment_status', ['credit', 'partial', 'unpaid'])
            ->get(['id', 'supplier_id', 'invoice_number', 'purchase_date', 'exchange_rate', 'line_total', 'notes', 'user_id', 'created_at', 'updated_at']);

        foreach ($rows as $row) {
            $total = round((float) $row->line_total, 2);
            DB::table('supplier_invoices')->insert([
                'supplier_id' => $row->supplier_id,
                'invoice_no' => mb_substr($row->invoice_number ?: ('P-'.$row->id), 0, 50),
                'purchase_date' => $row->purchase_date ?: now()->toDateString(),
                'exchange_rate' => round((float) ($row->exchange_rate ?? 0), 2),
                'total_afn' => $total,
                'paid_afn' => 0,
                'due_afn' => $total,
                'notes' => $row->notes,
                'created_by' => $row->user_id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
