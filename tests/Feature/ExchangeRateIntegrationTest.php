<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Currency $afn;

    private Currency $usd;

    private Currency $aed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afn = Currency::query()->where('code', 'AFN')->firstOrFail();
        $this->afn->update(['name' => 'AFN', 'is_base' => true, 'status' => 'active', 'trash' => false]);

        $this->usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->usd->update(['name' => 'USD', 'status' => 'active', 'trash' => false, 'is_base' => false]);

        $this->aed = Currency::query()->where('code', 'AED')->firstOrFail();
        $this->aed->update(['name' => 'AED', 'status' => 'active', 'trash' => false, 'is_base' => false]);

        ExchangeRate::query()->delete();
        app(ExchangeRateService::class)->forget();
    }

    public function test_board_returns_latest_rates(): void
    {
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->afn->id,
            'rate' => 55000,
            'effective_at' => now()->subDays(2),
            'source' => 'manual',
            'trash' => false,
        ]);
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->afn->id,
            'rate' => 56000,
            'effective_at' => now(),
            'source' => 'manual',
            'trash' => false,
        ]);
        ExchangeRate::create([
            'from_currency_id' => $this->aed->id,
            'to_currency_id' => $this->afn->id,
            'rate' => 15250,
            'effective_at' => now(),
            'source' => 'manual',
            'trash' => false,
        ]);

        app(ExchangeRateService::class)->forget();
        $board = app(ExchangeRateService::class)->board();

        $this->assertEquals(56000, $board['usd_to_afn']['rate']);
        $this->assertEquals(15250, $board['aed_to_afn']['rate']);
    }

    public function test_board_derives_usd_to_aed_when_missing(): void
    {
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->afn->id,
            'rate' => 56000,
            'effective_at' => now(),
            'source' => 'manual',
            'trash' => false,
        ]);
        ExchangeRate::create([
            'from_currency_id' => $this->aed->id,
            'to_currency_id' => $this->afn->id,
            'rate' => 14000,
            'effective_at' => now(),
            'source' => 'manual',
            'trash' => false,
        ]);

        app(ExchangeRateService::class)->forget();
        $board = app(ExchangeRateService::class)->board();

        $this->assertEquals(4.0, round($board['usd_to_aed']['rate'], 2));
        $this->assertEquals('derived', $board['usd_to_aed']['source']);
    }

    public function test_get_latest_rate_returns_correct_rate(): void
    {
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->afn->id,
            'rate' => 56000,
            'effective_at' => now(),
            'source' => 'manual',
            'trash' => false,
        ]);

        $rate = ExchangeRate::getLatestRate($this->usd->id, $this->afn->id);
        $this->assertEquals(56000, $rate);
    }

    public function test_get_latest_rate_returns_1_for_same_currency(): void
    {
        $rate = ExchangeRate::getLatestRate($this->afn->id, $this->afn->id);
        $this->assertEquals(1.0, $rate);
    }

    public function test_get_latest_rate_handles_reverse(): void
    {
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->afn->id,
            'rate' => 56000,
            'effective_at' => now(),
            'source' => 'manual',
            'trash' => false,
        ]);

        $rate = ExchangeRate::getLatestRate($this->afn->id, $this->usd->id);
        $this->assertEqualsWithDelta(1 / 56000, $rate, 0.00001);
    }
}
