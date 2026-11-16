<?php

namespace Tests;

use App\Models\Currency;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        putenv('DB_DATABASE=zuha_testing');
        $_ENV['DB_DATABASE'] = 'zuha_testing';
        $_SERVER['DB_DATABASE'] = 'zuha_testing';

        $app = parent::createApplication();
        $app['config']->set('database.connections.mysql.database', 'zuha_testing');
        $app['db']->purge();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.'.config('database.default').'.database');
        if ($database !== 'zuha_testing') {
            $this->fail("Refusing to run tests against database [{$database}]. Expected zuha_testing.");
        }

        // Ensure feature POSTs are not blocked by CSRF during automated tests.
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->ensureLedgerCurrencies();
    }

    /**
     * Keep AFN/USD/AED available for three-currency purchase/sale flows.
     */
    protected function ensureLedgerCurrencies(): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        $afn = Currency::query()->where('code', 'AFN')->first()
            ?? Currency::factory()->create([
                'code' => 'AFN',
                'name' => 'Afghan Afghani',
                'symbol' => '؋',
                'is_base' => true,
                'rate_to_default' => '1',
                'status' => 'active',
            ]);
        $afn->update(['is_base' => true, 'rate_to_default' => '1', 'status' => 'active', 'trash' => false]);

        $usd = Currency::query()->where('code', 'USD')->first()
            ?? Currency::factory()->create([
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'is_base' => false,
                'rate_to_default' => '70',
                'status' => 'active',
            ]);
        if ((float) $usd->rate_to_default <= 0) {
            $usd->update(['rate_to_default' => '70', 'status' => 'active', 'trash' => false, 'is_base' => false]);
        }

        $aed = Currency::query()->where('code', 'AED')->first()
            ?? Currency::factory()->create([
                'code' => 'AED',
                'name' => 'UAE Dirham',
                'symbol' => 'د.إ',
                'is_base' => false,
                'rate_to_default' => '19',
                'status' => 'active',
            ]);
        if ((float) $aed->rate_to_default <= 0) {
            $aed->update(['rate_to_default' => '19', 'status' => 'active', 'trash' => false, 'is_base' => false]);
        }
    }
}
