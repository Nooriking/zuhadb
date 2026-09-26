<?php

namespace Database\Seeders;

use App\Models\ActivityNotification;
use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Investment;
use App\Models\InvestmentProductAllocation;
use App\Models\InvestmentPurchaseAllocation;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\InvestorTransaction;
use App\Models\InvestorWallet;
use App\Models\InvestorWithdrawal;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductBatch;
use App\Models\ProductPriceHistory;
use App\Models\ProductVariant;
use App\Models\PurchaseItem;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Setting;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class FactorySampleSeeder extends Seeder
{
    public function run(): void
    {
        if (Sale::query()->where('invoice_number', 'FACTORY-0001')->exists()) {
            $this->finishSample();
            $this->command?->info('Factory sample is ready. Sale FACTORY-0001, customer 0701112233, barcode 8901110000001.');

            return;
        }

        $user = User::query()->first();
        $branch = Branch::query()->first() ?? Branch::factory()->create();
        $otherBranch = Branch::query()->whereKeyNot($branch->id)->first() ?? Branch::factory()->create(['name' => 'Factory Branch 2', 'code' => 'BR-FAC2']);

        if (Schema::hasTable('currencies')) {
            $usd = Currency::query()->firstOrCreate(['code' => 'USD'], [
                'name' => 'US Dollar', 'symbol' => '$', 'is_base' => true, 'status' => 'active', 'trash' => false,
            ]);
            $afn = Currency::query()->firstOrCreate(['code' => 'AFN'], [
                'name' => 'Afghani', 'symbol' => 'Af', 'is_base' => false, 'status' => 'active', 'trash' => false,
            ]);
            if (Schema::hasTable('exchange_rates')) {
                ExchangeRate::factory()->create([
                    'from_currency_id' => $usd->id,
                    'to_currency_id' => $afn->id,
                    'rate' => 70,
                    'created_by' => $user?->id,
                ]);
            }
        }
        if (Schema::hasTable('exchange_rate_histories')) {
            ExchangeRateHistory::factory()->create(['usd_to_afn' => 70, 'created_by' => $user?->id]);
        }

        $customer = Customer::query()->firstOrCreate(['phone' => '0701112233'], Customer::factory()->make([
            'name' => 'Factory Customer',
            'phone' => '0701112233',
            'current_due_afn' => 1500,
            'created_by' => $user?->id,
        ])->getAttributes());
        $supplier = Supplier::factory()->create([
            'name' => 'Factory Supplier',
            'phone' => '0702223344',
            'user_id' => $user?->id,
        ]);

        $existingBarcode = ProductBarcode::query()->where('barcode', '8901110000001')->first();
        if ($existingBarcode) {
            $variant = ProductVariant::query()->findOrFail($existingBarcode->variant_id);
            $product = Product::query()->findOrFail($variant->product_id);
        } else {
            $product = Product::factory()->create(['name' => 'Factory Rose', 'user_id' => $user?->id]);
            $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => '100ml']);
            ProductBarcode::factory()->create(['variant_id' => $variant->id, 'barcode' => '8901110000001']);
        }
        $inventory = Inventory::query()->firstOrCreate(
            ['branch_id' => $branch->id, 'variant_id' => $variant->id],
            Inventory::factory()->make([
                'branch_id' => $branch->id,
                'variant_id' => $variant->id,
                'quantity' => 8,
                'user_id' => $user?->id,
            ])->getAttributes()
        );
        $batch = ProductBatch::query()->firstOrCreate(
            ['batch_code' => 'BATCH-FACTORY-1'],
            ProductBatch::factory()->make([
                'branch_id' => $branch->id,
                'variant_id' => $variant->id,
                'batch_code' => 'BATCH-FACTORY-1',
                'quantity' => 8,
                'remaining_quantity' => 7,
                'user_id' => $user?->id,
            ])->getAttributes()
        );
        InventoryTransaction::factory()->create([
            'branch_id' => $branch->id,
            'variant_id' => $variant->id,
            'batch_id' => $batch->id,
            'created_by' => $user?->id,
        ]);
        ProductPriceHistory::factory()->create(['variant_id' => $variant->id, 'changed_by' => $user?->id]);
        $purchase = Product::factory()->create([
            'name' => 'Factory Rose',
            'brand_id' => $product->brand_id,
            'variant_id' => $variant->id,
            'quantity' => 8,
            'user_id' => $user?->id,
        ]);
        if (Schema::hasTable('purchase_items')) {
            PurchaseItem::factory()->create(['purchase_id' => $purchase->id, 'variant_id' => $variant->id]);
        }

        $sale = Sale::factory()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'FACTORY-0001',
            'receipt_no' => 'FACTORY-RCP-0001',
            'customer_phone_snapshot' => $customer->phone,
        ]);
        $item = SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'variant_id' => $variant->id,
            'batch_id' => $batch->id,
            'barcode_snapshot' => '8901110000001',
        ]);
        $payment = SalePayment::factory()->create([
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'received_by' => $user?->id,
        ]);
        Receipt::factory()->create([
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'sale_payment_id' => $payment->id,
            'receipt_number' => 'FACTORY-RCT-0001',
            'created_by' => $user?->id,
        ]);
        CustomerTransaction::factory()->create([
            'customer_id' => $customer->id,
            'reference_id' => $sale->id,
            'created_by' => $user?->id,
        ]);

        $return = SaleReturn::factory()->create([
            'sale_id' => $sale->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'return_number' => 'FACTORY-RET-0001',
            'created_by' => $user?->id,
        ]);
        SaleReturnItem::factory()->create([
            'sale_return_id' => $return->id,
            'sale_item_id' => $item->id,
            'variant_id' => $variant->id,
            'batch_id' => $batch->id,
        ]);

        Expense::factory()->create([
            'branch_id' => $branch->id,
            'expense_number' => 'FACTORY-EXP-0001',
            'title' => 'Factory shop expense',
            'created_by' => $user?->id,
            'user_id' => $user?->id,
        ]);

        $transfer = StockTransfer::factory()->create([
            'from_branch_id' => $branch->id,
            'to_branch_id' => $otherBranch->id,
            'transfer_number' => 'FACTORY-TRF-0001',
            'created_by' => $user?->id,
            'user_id' => $user?->id,
        ]);
        StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'variant_id' => $variant->id,
            'from_batch_id' => $batch->id,
        ]);

        $investor = Investor::factory()->create([
            'full_name' => 'Factory Investor',
            'phone' => '0703334455',
            'email' => 'factory-investor@zuha.test',
            'created_by' => $user?->id,
        ]);
        $investment = Investment::factory()->create([
            'investor_id' => $investor->id,
            'investment_number' => 'FACTORY-INV-0001',
            'created_by' => $user?->id,
            'user_id' => $user?->id,
        ]);
        InvestorWallet::factory()->create(['investor_id' => $investor->id]);
        InvestorTransaction::factory()->create([
            'investor_id' => $investor->id,
            'investment_id' => $investment->id,
            'created_by' => $user?->id,
        ]);
        InvestorWithdrawal::factory()->create([
            'investor_id' => $investor->id,
            'investment_id' => $investment->id,
            'withdrawal_number' => 'FACTORY-WD-0001',
            'created_by' => $user?->id,
        ]);
        if (Schema::hasTable('investment_purchase_allocations')) {
            $purchaseAllocation = InvestmentPurchaseAllocation::factory()->create([
                'investment_id' => $investment->id,
                'purchase_id' => $purchase->id,
                'created_by' => $user?->id,
            ]);
            if (Schema::hasTable('investment_product_allocations')) {
                $this->insertRow(InvestmentProductAllocation::class, [
                    'investment_id' => $investment->id,
                    'batch_id' => $batch->id,
                ], [
                    'investment_id' => $investment->id,
                    'investment_purchase_allocation_id' => $purchaseAllocation->id,
                    'batch_id' => $batch->id,
                    'variant_id' => $variant->id,
                    'funded_quantity' => 2,
                    'sold_quantity' => 0,
                    'remaining_quantity' => 2,
                    'cost_per_unit_afn' => 3000,
                    'profit_per_unit_afn' => 10,
                    'capital_recovered_afn' => 0,
                    'profit_earned_afn' => 0,
                    'status' => 'active',
                ]);
            }
        }
        InvestorProfitEntry::factory()->create([
            'investor_id' => $investor->id,
            'investment_id' => $investment->id,
            'sale_id' => $sale->id,
            'sale_item_id' => $item->id,
        ]);

        $bill = SupplierInvoice::factory()->create([
            'supplier_id' => $supplier->id,
            'invoice_no' => 'FACTORY-BILL-0001',
            'created_by' => $user?->id,
        ]);
        $supplierPayment = SupplierPayment::factory()->create([
            'supplier_id' => $supplier->id,
            'receipt_number' => 'FACTORY-SPAY-0001',
            'user_id' => $user?->id,
        ]);
        if (Schema::hasTable('supplier_payment_allocations')) {
            SupplierPaymentAllocation::factory()->create([
                'supplier_payment_id' => $supplierPayment->id,
                'product_id' => $purchase->id,
            ]);
        }

        if ($user) {
            ActivityNotification::factory()->create([
                'user_id' => $user->id,
                'sale_id' => $sale->id,
                'title' => 'Factory sale FACTORY-0001',
            ]);
        }

        if (Schema::hasTable('settings')) {
            Setting::query()->firstOrCreate(['key' => 'factory.sample'], Setting::factory()->make([
                'key' => 'factory.sample', 'value' => '1', 'updated_by' => $user?->id,
            ])->getAttributes());
        }

        if (Schema::hasTable('app_settings') && ! AppSetting::query()->exists()) {
            AppSetting::factory()->create();
        }

        unset($inventory, $bill);

        $this->command?->info('Factory sample is ready. Sale FACTORY-0001, customer 0701112233, barcode 8901110000001.');
    }

    private function finishSample(): void
    {
        $sale = Sale::query()->where('invoice_number', 'FACTORY-0001')->first();
        $item = SaleItem::query()->where('sale_id', $sale?->id)->first();
        $investment = Investment::query()->where('investment_number', 'FACTORY-INV-0001')->first();
        $supplier = Supplier::query()->where('phone', '0702223344')->first();
        $user = User::query()->first();
        $variantId = $item?->variant_id;
        $batchId = $item?->batch_id;
        $purchase = Product::query()->where('variant_id', $variantId)->latest('id')->first();

        if ($investment && $variantId && $batchId && Schema::hasTable('investment_product_allocations')) {
            $this->insertRow(InvestmentProductAllocation::class, [
                'investment_id' => $investment->id,
                'batch_id' => $batchId,
            ], [
                'investment_id' => $investment->id,
                'batch_id' => $batchId,
                'variant_id' => $variantId,
                'funded_quantity' => 2,
                'sold_quantity' => 0,
                'remaining_quantity' => 2,
                'cost_per_unit_afn' => 3000,
                'profit_per_unit_afn' => 10,
                'capital_recovered_afn' => 0,
                'profit_earned_afn' => 0,
                'status' => 'active',
            ]);
        }

        if ($investment && $sale && $item) {
            InvestorProfitEntry::query()->firstOrCreate([
                'sale_id' => $sale->id,
                'sale_item_id' => $item->id,
            ], InvestorProfitEntry::factory()->make([
                'investor_id' => $investment->investor_id,
                'investment_id' => $investment->id,
                'sale_id' => $sale->id,
                'sale_item_id' => $item->id,
            ])->only(Schema::getColumnListing('investor_profit_entries')));
        }

        if ($supplier) {
            $this->insertRow(SupplierInvoice::class, ['invoice_no' => 'FACTORY-BILL-0001'], array_merge(
                SupplierInvoice::factory()->make([
                    'supplier_id' => $supplier->id,
                    'invoice_no' => 'FACTORY-BILL-0001',
                    'created_by' => $user?->id,
                ])->getAttributes(),
                ['supplier_id' => $supplier->id, 'invoice_no' => 'FACTORY-BILL-0001', 'created_by' => $user?->id]
            ));
            $payment = SupplierPayment::query()->firstOrCreate(['receipt_number' => 'FACTORY-SPAY-0001'], array_intersect_key(
                array_merge(
                    SupplierPayment::factory()->make([
                        'supplier_id' => $supplier->id,
                        'receipt_number' => 'FACTORY-SPAY-0001',
                        'user_id' => $user?->id,
                    ])->getAttributes(),
                    ['supplier_id' => $supplier->id, 'receipt_number' => 'FACTORY-SPAY-0001', 'user_id' => $user?->id]
                ),
                array_flip(Schema::getColumnListing('supplier_payments'))
            ));
            if ($purchase && Schema::hasTable('supplier_payment_allocations')) {
                $this->insertRow(SupplierPaymentAllocation::class, [
                    'supplier_payment_id' => $payment->id,
                    'product_id' => $purchase->id,
                ], [
                    'supplier_payment_id' => $payment->id,
                    'product_id' => $purchase->id,
                    'amount_afn' => 2000,
                ]);
            }
        }

        if ($user && $sale && Schema::hasTable('activity_notifications')) {
            ActivityNotification::query()->firstOrCreate([
                'user_id' => $user->id,
                'title' => 'Factory sale FACTORY-0001',
            ], array_intersect_key(
                ActivityNotification::factory()->make([
                    'user_id' => $user->id,
                    'sale_id' => $sale->id,
                    'title' => 'Factory sale FACTORY-0001',
                ])->getAttributes(),
                array_flip(Schema::getColumnListing('activity_notifications'))
            ));
        }

        if (Schema::hasTable('settings')) {
            Setting::query()->firstOrCreate(['key' => 'factory.sample'], array_intersect_key(
                Setting::factory()->make([
                    'key' => 'factory.sample', 'value' => '1', 'updated_by' => $user?->id,
                ])->getAttributes(),
                array_flip(Schema::getColumnListing('settings'))
            ));
        }

        if (Schema::hasTable('app_settings') && ! AppSetting::query()->exists()) {
            AppSetting::factory()->create();
        }
    }

    /**
     * @param  class-string  $model
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $attributes
     */
    private function insertRow(string $model, array $match, array $attributes): void
    {
        $table = (new $model)->getTable();
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing($table));
        $match = array_intersect_key($match, $columns);
        $attributes = array_intersect_key($attributes, $columns);
        unset($attributes['id']);

        if ($match !== [] && $model::query()->where($match)->exists()) {
            return;
        }

        $model::query()->create($attributes);
    }
}
