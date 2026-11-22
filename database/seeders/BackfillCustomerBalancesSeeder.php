<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerBalance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class BackfillCustomerBalancesSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('customer_balances') || ! Schema::hasTable('customers')) {
            return;
        }

        Customer::query()
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(200, function ($customers) {
                foreach ($customers as $customer) {
                    CustomerBalance::recalculateFor((int) $customer->id);
                }
            });
    }
}
