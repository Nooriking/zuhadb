<?php

namespace Database\Seeders;

use App\Models\ActivityNotification;
use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
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
        ]);
        $investment = Investment::factory()->create([
            'investor_id' => $investor->id,
            'notes' => 'FACTORY-INV-0001',
            'created_by' => $user?->id,
        ]);
        $investor->getOrCreateWallet();
        InvestorTransaction::factory()->capitalDeposit()->create([
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
        $investment = Investment::query()->where('notes', 'FACTORY-INV-0001')->first();
        $supplier = Supplier::query()->where('phone', '0702223344')->first();
        $user = User::query()->first();

        $investor = Investor::query()->firstOrCreate(
            ['email' => 'factory-investor@zuha.test'],
            [
                'name' => 'Factory Investor',
                'username' => 'factory_investor',
                'phone' => '0703334455',
                'password' => bcrypt('FactoryInvestor@123'),
                'status' => 'active',
                'trash' => false,
            ]
        );
        $investment = Investment::query()->firstOrCreate(
            ['notes' => 'FACTORY-INV-0001'],
            [
                'investor_id' => $investor->id,
                'currency_id' => Currency::ledger()?->id,
                'exchange_rate' => 1,
                'amount_currency' => 10000,
                'amount_afn' => 10000,
                'investment_date' => now()->toDateString(),
                'status' => 'active',
                'created_by' => $user?->id,
                'trash' => false,
            ]
        );
        $investor->getOrCreateWallet();

        InvestorTransaction::query()->firstOrCreate(
            ['description' => 'Factory capital deposit'],
            [
                'investor_id' => $investor->id,
                'investment_id' => $investment->id,
                'type' => 'capital_deposit',
                'amount_afn' => 10000,
                'balance_type' => 'capital',
                'balance_before_afn' => 0,
                'balance_after_afn' => 10000,
                'transaction_date' => now()->toDateString(),
            ]
        );

        if (! InvestorWithdrawal::query()->where('withdrawal_number', 'FACTORY-WD-0001')->exists()) {
            InvestorWithdrawal::factory()->create([
                'investor_id' => $investor->id,
                'withdrawal_number' => 'FACTORY-WD-0001',
                'created_by' => $user?->id,
            ]);
        }

        if ($investment && $sale && $item) {
            InvestorTransaction::query()->firstOrCreate(
                [
                    'investor_id' => $investor->id,
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'type' => 'profit_earned',
                ],
                [
                    'investment_id' => $investment->id,
                    'currency_code' => 'AFN',
                    'currency_symbol' => '؋',
                    'exchange_rate' => 1,
                    'amount_currency' => 10,
                    'amount_afn' => 10,
                    'balance_type' => 'profit',
                    'balance_before_afn' => 0,
                    'balance_after_afn' => 10,
                    'description' => 'Sample sale profit',
                    'transaction_date' => now()->toDateString(),
                    'created_by' => $user?->id,
                ]
            );
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
