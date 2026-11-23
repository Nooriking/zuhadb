<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductBatch;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Database\Seeder;

/**
 * Idempotent fixtures for Playwright Product/Sale E2E.
 *
 *   php artisan db:seed --class=E2eFixtureSeeder
 */
class E2eFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', User::ROLE_SUPER_ADMIN)->first()
            ?? User::query()->where('role', User::ROLE_ADMIN)->first()
            ?? User::query()->first();

        if (! $admin) {
            $this->command?->error('No admin user found. Seed SuperAdminSeeder first.');

            return;
        }

        $branch = Branch::query()->first() ?? Branch::factory()->create([
            'name' => 'E2E Main Shop',
            'code' => 'BR-E2E',
            'status' => 'active',
            'trash' => false,
        ]);

        $afn = Currency::ledger()
            ?? Currency::query()->where('code', 'AFN')->first()
            ?? Currency::factory()->create([
                'code' => 'AFN',
                'name' => 'Afghani',
                'symbol' => '؋',
                'is_base' => true,
                'rate_to_default' => 1,
                'status' => 'active',
            ]);

        $brand = Brand::firstOrCreate(
            ['name' => 'Dior'],
            ['country' => 'France', 'status' => 'active', 'trash' => false]
        );

        $catalog = [
            ['name' => 'Dior Sauvage E2E', 'barcode' => '8900000000001', 'purchase' => 7000, 'sale' => 10000],
            ['name' => 'Chanel Bleu E2E', 'barcode' => '8900000000002', 'purchase' => 8000, 'sale' => 12000],
            ['name' => 'Creed Aventus E2E', 'barcode' => '8900000000003', 'purchase' => 9000, 'sale' => 14000],
            ['name' => 'Tom Ford Oud E2E', 'barcode' => '8900000000004', 'purchase' => 7500, 'sale' => 11000],
            ['name' => 'YSL Libre E2E', 'barcode' => '8900000000005', 'purchase' => 6500, 'sale' => 9500],
        ];

        $purchase = app(PurchaseService::class);

        foreach ($catalog as $row) {
            $product = Product::query()->where('name', $row['name'])->first();
            if (! $product) {
                $product = Product::create([
                    'brand_id' => $brand->id,
                    'name' => $row['name'],
                    'gender' => 'unisex',
                    'status' => 'active',
                    'description' => 'E2E fixture product',
                    'trash' => false,
                    'user_id' => $admin->id,
                ]);
            } else {
                $product->update([
                    'brand_id' => $brand->id,
                    'status' => 'active',
                    'trash' => false,
                ]);
            }

            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('volume_ml', 100)
                ->first();

            if (! $variant) {
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'name' => '100ml',
                    'sku' => 'E2E-'.substr($row['barcode'], -4),
                    'volume_ml' => 100,
                    'concentration' => 'edp',
                    'trash' => false,
                ]);
            }

            ProductBarcode::firstOrCreate(
                ['barcode' => $row['barcode']],
                [
                    'variant_id' => $variant->id,
                    'barcode_type' => 'EAN13',
                    'is_primary' => true,
                    'status' => 'active',
                    'current_stock' => 0,
                ]
            );

            ProductPrice::query()->updateOrCreate(
                [
                    'variant_id' => $variant->id,
                    'currency_id' => $afn->id,
                    'price_type' => 'sale',
                ],
                [
                    'product_id' => $product->id,
                    'currency_code' => $afn->code,
                    'amount' => $row['sale'],
                    'min_qty' => 0,
                    'is_active' => true,
                ]
            );

            ProductPrice::query()->updateOrCreate(
                [
                    'variant_id' => $variant->id,
                    'currency_id' => $afn->id,
                    'price_type' => 'purchase',
                ],
                [
                    'product_id' => $product->id,
                    'currency_code' => $afn->code,
                    'amount' => $row['purchase'],
                    'min_qty' => 0,
                    'is_active' => true,
                ]
            );

            $available = (int) (Inventory::query()
                ->where('branch_id', $branch->id)
                ->where('variant_id', $variant->id)
                ->value('available_quantity') ?? 0);

            $batchRemaining = (int) ProductBatch::query()
                ->where('branch_id', $branch->id)
                ->where('variant_id', $variant->id)
                ->where('trash', false)
                ->sum('remaining_quantity');

            if ($available < 10 || $batchRemaining < 10) {
                $need = max(10 - min($available, $batchRemaining), 10);
                try {
                    $purchase->receiveStock([
                        'variant_id' => $variant->id,
                        'quantity' => $need,
                        'purchase_currency_id' => $afn->id,
                        'purchase_unit_amount' => (string) $row['purchase'],
                        'sale_price_afn' => $row['sale'],
                        'funding_source' => 'office',
                        'purchase_date' => now()->toDateString(),
                        'invoice_number' => 'E2E-'.$row['barcode'],
                    ], $admin, $branch->id);
                } catch (\Throwable $e) {
                    // Fallback: ensure inventory row has stock for UI listing even if batch path fails.
                    $this->command?->warn("receiveStock failed for {$row['name']}: ".$e->getMessage());
                    Inventory::increase(
                        branchId: $branch->id,
                        variantId: $variant->id,
                        qty: $need,
                        type: 'purchase',
                        notes: 'E2E fixture fallback stock',
                        unitCostAfn: $row['purchase']
                    );
                }
            }
        }

        $customers = [
            ['name' => 'Ahmad Walk-in', 'phone' => '0701111001'],
            ['name' => 'Karim Credit', 'phone' => '0701111002'],
            ['name' => 'Sara Cash', 'phone' => '0701111003'],
        ];

        foreach ($customers as $c) {
            Customer::firstOrCreate(
                ['phone' => $c['phone']],
                [
                    'name' => $c['name'],
                    'status' => 'active',
                    'trash' => false,
                    'current_due_afn' => 0,
                    'credit_limit_afn' => 500000,
                ]
            );
        }

        // Reactivate any existing catalog product used by older tests.
        Product::query()->whereKey(1)->update(['status' => 'active', 'trash' => false]);

        $this->command?->info('E2E fixtures ready: 5 products with stock, Dior brand, 3 customers, barcode 8900000000001.');
        $this->command?->info('Login: '.$admin->email.' (use seeded superadmin password).');
    }
}
