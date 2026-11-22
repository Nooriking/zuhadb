<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cash capital deposit for an investor user (role=investor).
 * Auto-investment on purchase is handled in PurchaseService / ProductController.
 */
class InvestmentCapitalService
{
    public function __construct(
        private readonly CurrencyService $currencies,
        private readonly ExchangeService $exchange,
    ) {}

    /**
     * @param  array{
     *     user_id:int,
     *     capital_amount?:float|string|null,
     *     amount_currency?:float|string|null,
     *     currency_id?:int|null,
     *     exchange_rate?:float|string|null,
     *     investment_date:string,
     *     notes?:string|null
     * }  $data
     */
    public function deposit(array $data): Investment
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()
                ->notTrashed()
                ->where('role', User::ROLE_INVESTOR)
                ->where('status', 'active')
                ->findOrFail($data['user_id']);

            $investor = Investor::query()->whereKey($user->id)->firstOrFail();
            $investor->getOrCreateWallet();

            $capitalEntered = Money::round(
                (string) ($data['amount_currency'] ?? $data['capital_amount'] ?? $data['capital_amount_afn'] ?? '0'),
                8
            );

            if ((float) $capitalEntered <= 0) {
                throw ValidationException::withMessages([
                    'capital_amount' => 'Capital amount must be greater than zero.',
                ]);
            }

            $currency = $this->currencies->requireActive((int) ($data['currency_id'] ?? 0));
            $ledger = Currency::ledger() ?? $currency;
            $explicitRate = isset($data['exchange_rate']) && (float) $data['exchange_rate'] > 0
                ? Money::round((string) $data['exchange_rate'], 8)
                : null;

            if ($explicitRate !== null) {
                $rate = $explicitRate;
                $capitalAfn = Money::round(Money::mul($capitalEntered, $rate, 12), 2);
            } else {
                $snap = $this->exchange->snapshot((string) $currency->code, (string) $ledger->code);
                $rate = Money::round((string) $snap['rate'], 8);
                $capitalAfn = Money::round(Money::mul($capitalEntered, $rate, 12), 2);
            }

            $investment = Investment::createFromPurchase([
                'investor_id' => $investor->id,
                'currency_id' => $currency->id,
                'amount_currency' => $capitalEntered,
                'amount_afn' => $capitalAfn,
                'exchange_rate' => $rate,
                'investment_date' => $data['investment_date'],
                'notes' => $data['notes'] ?? 'Cash capital deposit',
                'created_by' => Auth::id(),
            ]);

            InvestorTransaction::record(
                $investment->investor_id,
                'capital_deposit',
                (float) $capitalAfn,
                'capital',
                [
                    'investment_id' => $investment->id,
                    'currency_id' => $currency->id,
                    'currency_code' => $currency->code,
                    'currency_symbol' => $currency->symbol,
                    'exchange_rate' => $rate,
                    'amount_currency' => $capitalEntered,
                    'description' => 'Capital deposit #'.$investment->id,
                    'transaction_date' => $investment->investment_date?->toDateString(),
                ]
            );

            $wallet = $investment->investor->getOrCreateWallet();
            $wallet->adjustCapital(
                (string) $currency->code,
                (float) $capitalEntered,
                (float) $capitalAfn,
                (float) $rate
            );

            return $investment;
        });
    }
}
