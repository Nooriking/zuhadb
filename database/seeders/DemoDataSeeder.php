<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::firstOrCreate(
            ['code' => 'BR-DEMO'],
            Branch::factory()->make([
                'name' => 'Demo Kabul Store',
                'code' => 'BR-DEMO',
                'email' => 'demo-store@zuha.test',
            ])->getAttributes()
        );

        $supplier = Supplier::firstOrCreate(
            ['email' => 'demo-supplier@zuha.test'],
            Supplier::factory()->make([
                'name' => 'Demo Perfume Supplier',
                'email' => 'demo-supplier@zuha.test',
            ])->getAttributes()
        );

        $catalog = [
            ['brand' => 'Dior', 'country' => 'France', 'product' => 'Dior Sauvage', 'barcode' => '8900000000001'],
            ['brand' => 'Chanel', 'country' => 'France', 'product' => 'Chanel Bleu', 'barcode' => '8900000000002'],
            ['brand' => 'Creed', 'country' => 'France', 'product' => 'Creed Aventus', 'barcode' => '8900000000003'],
        ];

        foreach ($catalog as $row) {
            $brand = Brand::firstOrCreate(
                ['name' => $row['brand']],
                Brand::factory()->make([
                    'name' => $row['brand'],
                    'country' => $row['country'],
                ])->getAttributes()
            );

            $product = Product::firstOrCreate(
                ['name' => $row['product'], 'variant_id' => null],
                Product::factory()->make([
                    'brand_id' => $brand->id,
                    'name' => $row['product'],
                    'gender' => 'male',
                ])->getAttributes()
            );

            $variant = ProductVariant::firstOrCreate(
                ['product_id' => $product->id, 'volume_ml' => 100],
                ProductVariant::factory()->make([
                    'product_id' => $product->id,
                    'name' => '100ml',
                    'sku' => 'DEMO-'.strtoupper(str_replace(' ', '-', $row['product'])),
                ])->getAttributes()
            );

            ProductBarcode::firstOrCreate(
                ['barcode' => $row['barcode']],
                ProductBarcode::factory()->make([
                    'variant_id' => $variant->id,
                    'barcode' => $row['barcode'],
                ])->getAttributes()
            );

            Inventory::firstOrCreate(
                ['branch_id' => $branch->id, 'variant_id' => $variant->id],
                Inventory::factory()->make([
                    'branch_id' => $branch->id,
                    'variant_id' => $variant->id,
                ])->getAttributes()
            );
        }

        Customer::firstOrCreate(
            ['phone' => '0700000001'],
            Customer::factory()->make([
                'name' => 'Ahmad Demo',
                'phone' => '0700000001',
                'current_due_afn' => 5000,
            ])->getAttributes()
        );

        Expense::firstOrCreate(
            ['expense_number' => 'EXP-DEMO-0001'],
            Expense::factory()->make([
                'branch_id' => $branch->id,
                'expense_number' => 'EXP-DEMO-0001',
                'title' => 'Demo shop rent',
            ])->getAttributes()
        );

        $investorUser = User::where('email', 'investor@example.com')->first();

        $investor = Investor::firstOrCreate(
            ['email' => 'investor@example.com'],
            [
                'user_id' => $investorUser?->id,
                'full_name' => 'Demo Investor',
                'phone' => '0700000006',
                'address' => 'Kabul',
                'status' => 'active',
                'trash' => false,
            ]
        );

        if ($investorUser && $investor->user_id !== $investorUser->id) {
            $investor->update(['user_id' => $investorUser->id]);
        }

        $investment = Investment::firstOrCreate(
            ['investment_number' => 'INV-DEMO-0001'],
            [
                'investor_id' => $investor->id,
                'capital_amount_afn' => 100000,
                'available_amount_afn' => 100000,
                'allocated_amount_afn' => 0,
                'profit_amount_afn' => 0,
                'withdrawn_amount_afn' => 0,
                'investment_date' => now()->toDateString(),
                'status' => 'active',
                'notes' => 'Demo capital',
                'trash' => false,
            ]
        );

        if ($investment->wasRecentlyCreated) {
            InvestorTransaction::record(
                $investor->id,
                'capital_deposit',
                100000,
                'capital',
                [
                    'investment_id' => $investment->id,
                    'description' => 'Capital deposit INV-DEMO-0001',
                    'transaction_date' => now()->toDateString(),
                ]
            );

            $wallet = $investor->getOrCreateWallet();
            $wallet->update([
                'capital_balance_afn' => (float) $wallet->capital_balance_afn + 100000,
                'total_invested_afn' => (float) $wallet->total_invested_afn + 100000,
                'last_transaction_at' => now(),
            ]);
        } else {
            $investor->getOrCreateWallet();
        }

        $this->command?->info('Demo data is ready: branch BR-DEMO, 3 perfumes, customer Ahmad Demo, investor Demo Investor.');
    }
}
