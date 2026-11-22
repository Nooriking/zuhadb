<?php

namespace Database\Seeders\Qa;

use App\Models\Currency;
use App\Services\CurrencyService;
use Database\Seeders\Qa\Concerns\GuardsQaDatabase;
use Database\Seeders\Qa\Concerns\QaContext;
use Illuminate\Database\Seeder;

class QaCurrencyExchangeSeeder extends Seeder
{
    use GuardsQaDatabase;

    public function __construct(private readonly QaContext $ctx) {}

    public function run(): void
    {
        $this->assertQaDatabase();

        $afn = Currency::query()->updateOrCreate(
            ['code' => 'AFN'],
            [
                'name' => 'Afghan Afghani',
                'symbol' => '؋',
                'decimal_places' => 2,
                'rate_to_default' => '1',
                'is_base' => true,
                'status' => 'active',
                'trash' => false,
            ]
        );

        $usd = Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'rate_to_default' => '70',
                'is_base' => false,
                'status' => 'active',
                'trash' => false,
            ]
        );

        $aed = Currency::query()->updateOrCreate(
            ['code' => 'AED'],
            [
                'name' => 'UAE Dirham',
                'symbol' => 'د.إ',
                'decimal_places' => 2,
                'rate_to_default' => '19',
                'is_base' => false,
                'status' => 'active',
                'trash' => false,
            ]
        );

        $fx = app(CurrencyService::class);
        $fx->postRate($usd, $afn, '70', '2026-01-01 08:00:00', null, 'qa-seed');
        $fx->postRate($aed, $afn, '19', '2026-01-01 08:00:00', null, 'qa-seed');
        $fx->postRate($usd, $afn, '71', '2026-02-01 08:00:00', null, 'qa-seed');
        $fx->postRate($aed, $afn, '19.2', '2026-02-01 08:00:00', null, 'qa-seed');
        $fx->postRate($usd, $afn, '72', '2026-03-01 08:00:00', null, 'qa-seed');
        $fx->postRate($aed, $afn, '19.5', '2026-03-01 08:00:00', null, 'qa-seed');

        $this->ctx->afn = $afn->fresh();
        $this->ctx->usd = $usd->fresh();
        $this->ctx->aed = $aed->fresh();

        $this->command?->info('Currencies AFN/USD/AED + historical FX board ready.');
    }
}
