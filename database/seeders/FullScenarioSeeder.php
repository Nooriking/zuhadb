<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Database\Seeder;

class FullScenarioSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->where('name', 'Test Perfume 001')->exists()) {
            $this->command?->info('Full scenario data already exists.');

            return;
        }

        $admin = User::query()->where('role', 'super_admin')->first()
            ?? User::query()->first();
        $branch = Branch::query()->first() ?? Branch::create([
            'name' => 'Main Shop',
            'code' => 'BR-MAIN',
            'status' => 'active',
            'trash' => false,
        ]);

        $brand = Brand::firstOrCreate(
            ['name' => 'Test House'],
            ['country' => 'France', 'status' => 'active', 'trash' => false]
        );

        $investors = collect(['Ahmad Investor', 'Karim Investor', 'Shop Partner'])->map(function (string $name, int $index) use ($admin) {
            $investor = Investor::firstOrCreate(
                ['email' => 'investor'.($index + 1).'@zuha.test'],
                [
                    'full_name' => $name,
                    'phone' => '07000000'.($index + 1),
                    'status' => 'active',
                    'trash' => false,
                    'created_by' => $admin?->id,
                ]
            );
            Investment::firstOrCreate(
                ['investment_number' => 'INV-TEST-'.($index + 1)],
                [
                    'investor_id' => $investor->id,
                    'capital_amount_afn' => 5000,
                    'available_amount_afn' => 5000,
                    'allocated_amount_afn' => 0,
                    'profit_amount_afn' => 0,
                    'withdrawn_amount_afn' => 0,
                    'investment_date' => now()->toDateString(),
                    'status' => 'active',
                    'notes' => 'Test capital in USD stored on the investor account.',
                ]
            );

            return $investor;
        });

        $suppliers = collect(['Ahmad Supplier', 'Kabul Wholesale'])->map(function (string $name, int $index) use ($admin) {
            return Supplier::firstOrCreate(
                ['email' => 'supplier'.($index + 1).'@zuha.test'],
                [
                    'name' => $name,
                    'phone' => '07800000'.($index + 1),
                    'status' => 'active',
                    'trash' => false,
                    'opening_balance' => 0,
                    'user_id' => $admin?->id,
                ]
            );
        });

        $customers = collect(['Walk Credit Customer', 'Cash Customer'])->map(function (string $name, int $index) {
            return Customer::firstOrCreate(
                ['phone' => '07900000'.($index + 1)],
                ['name' => $name, 'status' => 'active', 'trash' => false, 'current_due_afn' => 0]
            );
        });

        $variants = [];

        for ($i = 1; $i <= 100; $i++) {
            $number = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $investor = $i % 3 === 0 ? null : $investors[$i % 3];
            $supplier = $suppliers[$i % 2];
            $qty = $i <= 8 ? 2 : 12;
            $purchase = 2000 + ($i * 10);
            $salePrice = $purchase + 800;

            $product = Product::create([
                'brand_id' => $brand->id,
                'name' => "Test Perfume {$number}",
                'gender' => $i % 2 === 0 ? 'female' : 'male',
                'status' => 'active',
                'description' => 'Seeded test perfume',
                'trash' => false,
                'user_id' => $admin?->id,
            ]);

            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'name' => '100ml',
                'sku' => 'TEST-'.$number,
                'volume_ml' => 100,
                'concentration' => 'edp',
                'purchase_price_afn' => $purchase,
                'sale_price_afn' => $salePrice,
                'wholesale_price_afn' => $salePrice - 200,
                'wholesale_min_qty' => 6,
                'profit_per_unit' => 200,
                'stock_quantity' => $qty,
                'minimum_stock' => 2,
                'trash' => false,
            ]);

            ProductBarcode::create([
                'variant_id' => $variant->id,
                'barcode' => '9900000000'.$number,
                'barcode_type' => 'CODE128',
                'is_primary' => true,
                'status' => 'active',
                'current_stock' => $qty,
            ]);

            $paidNow = $i % 4 === 0 ? 0 : ($i % 4 === 1 ? $purchase * $qty : (int) round($purchase * $qty / 2));
            $lineTotal = $purchase * $qty;

            $purchaseRow = Product::create([
                'name' => $product->name,
                'brand_id' => $brand->id,
                'gender' => $product->gender,
                'status' => 'active',
                'trash' => false,
                'user_id' => $admin?->id,
                'variant_id' => $variant->id,
                'investor_id' => $investor?->id,
                'supplier_id' => $supplier->id,
                'invoice_number' => 'BILL-'.$number,
                'purchase_date' => now()->subDays($i % 20)->toDateString(),
                'exchange_rate' => 70 + ($i % 5),
                'quantity' => $qty,
                'remaining_quantity' => $qty,
                'purchase_price_afn' => $lineTotal,
                'line_total' => $lineTotal,
                'profit_per_unit' => 200,
                'line_profit' => 200 * $qty,
                'payment_status' => $paidNow >= $lineTotal ? 'paid' : ($paidNow > 0 ? 'partial' : 'unpaid'),
            ]);

            $invoice = SupplierInvoice::query()
                ->where('supplier_id', $supplier->id)
                ->where('invoice_no', 'BILL-'.$number)
                ->first();
            $invoiceData = [
                'exchange_rate' => $purchaseRow->exchange_rate,
                'total_afn' => $lineTotal,
                'paid_afn' => min($paidNow, $lineTotal),
                'due_afn' => max(0, $lineTotal - $paidNow),
                'notes' => $paidNow >= $lineTotal ? 'Paid in full on arrival' : 'Credit purchase',
                'created_by' => $admin?->id,
            ];
            if ($invoice) {
                $invoice->update($invoiceData);
            } else {
                SupplierInvoice::create(array_merge($invoiceData, [
                    'supplier_id' => $supplier->id,
                    'invoice_no' => 'BILL-'.$number,
                    'purchase_date' => $purchaseRow->purchase_date,
                ]));
            }

            Inventory::increase(
                branchId: $branch->id,
                variantId: $variant->id,
                qty: $qty,
                type: 'purchase',
                notes: 'Add stock seed',
                unitCostAfn: $purchase
            );

            $variants[] = $variant;
        }

        foreach ($customers as $index => $customer) {
            for ($n = 1; $n <= 10; $n++) {
                $variant = $variants[($index * 10 + $n) % 90];
                $credit = $index === 0;
                $total = (float) $variant->sale_price_afn;
                $paid = $credit ? 0 : $total;
                $sale = Sale::create([
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'customer_name_snapshot' => $customer->name,
                    'customer_phone_snapshot' => $customer->phone,
                    'invoice_number' => 'S-TEST-'.$customer->id.'-'.$n,
                    'subtotal_afn' => $total,
                    'discount_afn' => 0,
                    'total_afn' => $total,
                    'new_sale_amount_afn' => $total,
                    'grand_total_due_afn' => $credit ? $total : 0,
                    'received_now_afn' => $paid,
                    'remaining_due_afn' => $credit ? $total : 0,
                    'payment_method' => $credit ? 'credit' : 'cash',
                    'paid_afn' => $paid,
                    'due_afn' => $credit ? $total : 0,
                    'sale_date' => now()->subDays($n)->toDateString(),
                    'payment_status' => $credit ? 'unpaid' : 'paid',
                    'status' => 'completed',
                    'created_by' => $admin?->id,
                    'trash' => false,
                ]);
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'variant_id' => $variant->id,
                    'product_name_snapshot' => $variant->product?->name ?? $variant->sku,
                    'sku_snapshot' => $variant->sku,
                    'quantity' => 1,
                    'cost_price_afn' => $variant->purchase_price_afn,
                    'sale_price_afn' => $total,
                    'discount_afn' => 0,
                    'line_total_afn' => $total,
                    'store_profit_afn' => 600,
                    'investor_profit_afn' => 200,
                ]);
                if ($credit) {
                    $customer->increment('current_due_afn', $total);
                }
            }
        }

        $open = SupplierInvoice::query()->where('due_afn', '>', 0)->first();
        if ($open) {
            $pay = round(min(500, (float) $open->due_afn), 2);
            $open->update([
                'paid_afn' => round((float) $open->paid_afn + $pay, 2),
                'due_afn' => round((float) $open->due_afn - $pay, 2),
            ]);
            SupplierPayment::create([
                'supplier_id' => $open->supplier_id,
                'receipt_number' => 'SP-TEST-0001',
                'amount_afn' => $pay,
                'balance_after' => $open->due_afn,
                'payment_date' => now()->toDateString(),
                'exchange_rate' => 72,
                'payment_method' => 'cash',
                'notes' => $open->invoice_no,
                'user_id' => $admin?->id,
            ]);
        }

        $this->command?->info('Seeded 100 products, investors, supplier credit, sales, and low-stock alerts.');
    }
}
