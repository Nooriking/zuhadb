<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvestorDashboardSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->where('name', 'Investor Rose 01')->exists()) {
            $this->command?->info('Investor dashboard sample already exists.');

            return;
        }

        $investor = Investor::query()->where('email', 'investor@example.com')->first()
            ?? Investor::factory()->create([
                'full_name' => 'Demo Investor',
                'email' => 'investor@example.com',
                'user_id' => User::query()->where('email', 'investor@example.com')->value('id'),
            ]);

        $investment = Investment::query()->where('investor_id', $investor->id)->orderBy('id')->first()
            ?? Investment::factory()->create([
                'investor_id' => $investor->id,
                'investment_number' => 'INV-DEMO-0001',
                'capital_amount_afn' => 100000,
                'available_amount_afn' => 100000,
            ]);

        $branch = Branch::query()->first() ?? Branch::factory()->create();
        $brand = Brand::query()->first() ?? Brand::factory()->create(['name' => 'Investor House']);
        $customer = Customer::query()->first() ?? Customer::factory()->create(['name' => 'Investor Sale Customer']);
        $adminId = User::query()->where('role', 'super_admin')->value('id');

        $spentUsd = 0.0;
        $sold = [];

        foreach ([
            ['Investor Rose 01', 70, 4, 0],
            ['Investor Oud 02', 71, 6, 1],
            ['Investor Musk 03', 72, 5, 2],
            ['Investor Amber 04', 73, 8, 0],
            ['Investor Citrus 05', 74, 3, 1],
            ['Investor Vanilla 06', 70, 10, 0],
        ] as $index => [$name, $rate, $qty, $soldQty]) {
            $unitAfn = 2800 + ($index * 200);
            $profitAfn = 700;
            $profitUsd = round($profitAfn / $rate, 2);
            $lineAfn = $unitAfn * $qty;
            $spentUsd += round($lineAfn / $rate, 2);

            $product = Product::factory()->create([
                'brand_id' => $brand->id,
                'name' => $name,
                'gender' => $index % 2 === 0 ? 'female' : 'male',
            ]);

            $variant = ProductVariant::factory()->create([
                'product_id' => $product->id,
                'sku' => 'INV-DASH-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'purchase_price_afn' => $unitAfn,
                'sale_price_afn' => $unitAfn + 1200,
                'profit_per_unit' => $profitAfn,
                'stock_quantity' => $qty - $soldQty,
                'minimum_stock' => 2,
            ]);

            ProductBarcode::create([
                'variant_id' => $variant->id,
                'barcode' => '880000000'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'barcode_type' => 'CODE128',
                'is_primary' => true,
                'status' => 'active',
                'current_stock' => $qty - $soldQty,
            ]);

            $purchase = Product::factory()->create([
                'brand_id' => $brand->id,
                'name' => $name,
                'gender' => $product->gender,
                'variant_id' => $variant->id,
                'investor_id' => $investor->id,
                'invoice_number' => 'INV-BILL-'.($index + 1),
                'purchase_date' => now()->subDays(12 - $index)->toDateString(),
                'exchange_rate' => $rate,
                'quantity' => $qty,
                'remaining_quantity' => $qty - $soldQty,
                'purchase_price_afn' => $lineAfn,
                'profit_per_unit' => $profitAfn,
                'line_total' => $lineAfn,
                'line_profit' => $profitAfn * $qty,
                'payment_status' => 'paid',
            ]);

            $batch = ProductBatch::factory()->create([
                'branch_id' => $branch->id,
                'variant_id' => $variant->id,
                'purchase_item_id' => $purchase->id,
                'investor_id' => $investor->id,
                'batch_code' => 'B-INV-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'quantity' => $qty,
                'remaining_quantity' => $qty - $soldQty,
                'unit_cost_afn' => $unitAfn,
                'total_cost_afn' => $lineAfn,
                'profit_per_unit_afn' => $profitUsd,
                'received_date' => $purchase->purchase_date,
                'status' => ($qty - $soldQty) > 0 ? 'active' : 'depleted',
            ]);

            Inventory::increase(
                branchId: $branch->id,
                variantId: $variant->id,
                qty: $qty - $soldQty,
                type: 'purchase',
                notes: 'Investor dashboard seed',
                unitCostAfn: $unitAfn,
            );

            if ($soldQty > 0) {
                $sold[] = [$variant, $batch, $soldQty, $profitUsd, $unitAfn];
            }
        }

        foreach ($sold as $index => [$variant, $batch, $soldQty, $profitUsd, $unitAfn]) {
            $price = (float) $variant->sale_price_afn;
            $total = round($price * $soldQty, 2);
            $sale = Sale::create([
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'customer_name_snapshot' => $customer->name,
                'customer_phone_snapshot' => $customer->phone,
                'invoice_number' => 'S-INV-'.$investor->id.'-'.($index + 1),
                'subtotal_afn' => $total,
                'discount_afn' => 0,
                'total_afn' => $total,
                'new_sale_amount_afn' => $total,
                'grand_total_due_afn' => $total,
                'received_now_afn' => $total,
                'remaining_due_afn' => 0,
                'payment_method' => 'cash',
                'paid_afn' => $total,
                'due_afn' => 0,
                'total_cost_afn' => $unitAfn * $soldQty,
                'total_profit_afn' => round($total - ($unitAfn * $soldQty), 2),
                'total_investor_profit_afn' => round($profitUsd * $soldQty, 2),
                'store_profit_afn' => round($total - ($unitAfn * $soldQty) - ($profitUsd * $soldQty), 2),
                'sale_date' => now()->subDays($index + 1)->toDateString(),
                'payment_status' => 'paid',
                'status' => 'completed',
                'created_by' => $adminId,
                'trash' => false,
            ]);

            $item = SaleItem::create([
                'sale_id' => $sale->id,
                'variant_id' => $variant->id,
                'batch_id' => $batch->id,
                'product_name_snapshot' => $variant->product?->name ?? $variant->sku,
                'sku_snapshot' => $variant->sku,
                'quantity' => $soldQty,
                'cost_price_afn' => $unitAfn,
                'sale_price_afn' => $price,
                'discount_afn' => 0,
                'line_total_afn' => $total,
                'store_profit_afn' => round($total - ($unitAfn * $soldQty), 2),
                'investor_profit_afn' => round($profitUsd * $soldQty, 2),
            ]);

            InvestorProfitEntry::recordProfit(
                investorId: $investor->id,
                investmentId: $investment->id,
                productAllocationId: null,
                saleId: $sale->id,
                saleItemId: $item->id,
                quantity: $soldQty,
                profitPerUnitAfn: $profitUsd,
                notes: 'Profit from '.$variant->sku,
            );
        }

        $spentUsd = round($spentUsd, 2);
        $investment->update([
            'allocated_amount_afn' => $spentUsd,
            'available_amount_afn' => max(0, round((float) $investment->capital_amount_afn - $spentUsd, 2)),
            'status' => 'partially_used',
        ]);

        $this->command?->info('Demo Investor now has stock purchases and profit entries.');
    }
}
