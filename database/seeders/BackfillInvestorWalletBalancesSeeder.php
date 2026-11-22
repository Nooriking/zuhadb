<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillInvestorWalletBalancesSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('investor_wallet_balances') || ! Schema::hasTable('investor_wallets')) {
            return;
        }

        $afn = Currency::query()->notTrashed()->where('code', 'AFN')->first();
        if (! $afn) {
            return;
        }

        $now = now();

        DB::table('investor_wallets')
            ->orderBy('id')
            ->select([
                'id',
                'investor_id',
                'capital_balance_afn',
                'profit_balance_afn',
            ])
            ->chunkById(200, function ($wallets) use ($afn, $now) {
                foreach ($wallets as $wallet) {
                    $capital = round((float) $wallet->capital_balance_afn, 2);
                    $profit = round((float) $wallet->profit_balance_afn, 2);

                    DB::table('investor_wallet_balances')->updateOrInsert(
                        [
                            'investor_id' => $wallet->investor_id,
                            'currency_id' => $afn->id,
                        ],
                        [
                            'currency_code' => 'AFN',
                            'capital_balance_currency' => $capital,
                            'capital_balance_afn' => $capital,
                            'profit_balance_currency' => $profit,
                            'profit_balance_afn' => $profit,
                            'last_rate' => 1,
                            'last_rate_at' => $now,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            });
    }
}
