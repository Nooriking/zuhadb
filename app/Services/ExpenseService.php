<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Expense;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Expense recording with immutable original-currency + AFN ledger snapshots.
 *
 * Architecture note: this app has no separate cash/bank ledger tables for expenses.
 * Expenses are recorded as incurred+paid documents. AFN (`amount_afn`) is the
 * authoritative ledger amount used by dashboards and profit reports.
 */
class ExpenseService
{
    public function __construct(
        private readonly CurrencyService $currencies,
    ) {}

    /**
     * @param  array{
     *   branch_id:int,
     *   title:string,
     *   category:string,
     *   currency_id?:int|null,
     *   amount?:float|string|null,
     *   amount_afn?:float|string|null,
     *   expense_date:string,
     *   payment_method:string,
     *   description?:string|null,
     * }  $data
     */
    public function create(array $data, ?int $userId = null): Expense
    {
        return DB::transaction(function () use ($data, $userId) {
            $money = $this->resolveMoneySnapshot($data);

            return Expense::create([
                'branch_id' => (int) $data['branch_id'],
                'expense_number' => Expense::generateExpenseNumber(),
                'title' => $data['title'],
                'category' => $data['category'],
                'currency_id' => $money['currency_id'],
                'currency_code' => $money['currency_code'],
                'currency_symbol' => $money['currency_symbol'],
                'amount_currency' => $money['amount_currency'],
                'exchange_rate' => $money['exchange_rate'],
                'rate_effective_at' => $money['rate_effective_at'],
                'amount_afn' => $money['amount_afn'],
                'expense_date' => $data['expense_date'],
                'payment_method' => $data['payment_method'],
                'description' => $data['description'] ?? null,
                'created_by' => $userId ?? Auth::id(),
                'user_id' => $userId ?? Auth::id(),
                'trash' => false,
            ]);
        });
    }

    /**
     * @param  array{
     *   branch_id:int,
     *   title:string,
     *   category:string,
     *   currency_id?:int|null,
     *   amount?:float|string|null,
     *   amount_afn?:float|string|null,
     *   expense_date:string,
     *   payment_method:string,
     *   description?:string|null,
     * }  $data
     */
    public function update(Expense $expense, array $data, ?int $userId = null): Expense
    {
        return DB::transaction(function () use ($expense, $data, $userId) {
            $money = $this->resolveMoneySnapshot($data);

            $expense->update([
                'branch_id' => (int) $data['branch_id'],
                'title' => $data['title'],
                'category' => $data['category'],
                'currency_id' => $money['currency_id'],
                'currency_code' => $money['currency_code'],
                'currency_symbol' => $money['currency_symbol'],
                'amount_currency' => $money['amount_currency'],
                'exchange_rate' => $money['exchange_rate'],
                'rate_effective_at' => $money['rate_effective_at'],
                'amount_afn' => $money['amount_afn'],
                'expense_date' => $data['expense_date'],
                'payment_method' => $data['payment_method'],
                'description' => $data['description'] ?? null,
                'user_id' => $userId ?? Auth::id(),
            ]);

            return $expense->fresh();
        });
    }

    /**
     * Soft-trash (void) an expense. Historical AFN snapshot stays intact for audit;
     * trashed rows are excluded from dashboard/report aggregates via notTrashed().
     */
    public function void(Expense $expense, ?int $userId = null): Expense
    {
        $expense->update([
            'trash' => true,
            'user_id' => $userId ?? Auth::id(),
        ]);

        return $expense;
    }

    /**
     * Create an AFN-only expense for internal system call sites (sale returns, price drops).
     */
    public function createLedgerOnly(array $data, ?int $userId = null): Expense
    {
        $ledger = Currency::ledger() ?? Currency::query()->notTrashed()->where('code', 'AFN')->first();
        $amountAfn = Money::round((string) ($data['amount_afn'] ?? 0), 2);

        if ((float) $amountAfn <= 0) {
            throw ValidationException::withMessages([
                'amount_afn' => 'Expense amount must be greater than zero.',
            ]);
        }

        $payload = array_merge($data, [
            'currency_id' => $ledger?->id,
            'amount' => $amountAfn,
            'amount_afn' => $amountAfn,
        ]);

        return $this->create($payload, $userId);
    }

    /**
     * @return array{
     *   currency_id:int|null,
     *   currency_code:string,
     *   currency_symbol:string|null,
     *   amount_currency:string,
     *   exchange_rate:string,
     *   rate_effective_at:string|null,
     *   amount_afn:string
     * }
     */
    private function resolveMoneySnapshot(array $data): array
    {
        $at = $data['expense_date'] ?? now();

        $currency = isset($data['currency_id']) && $data['currency_id']
            ? $this->currencies->requireActive((int) $data['currency_id'])
            : (Currency::ledger() ?? Currency::query()->notTrashed()->where('code', 'AFN')->first());

        if (! $currency) {
            throw ValidationException::withMessages([
                'currency_id' => 'No active ledger currency is configured.',
            ]);
        }

        $entered = $data['amount'] ?? $data['amount_currency'] ?? $data['amount_afn'] ?? null;
        if ($entered === null || $entered === '' || (float) $entered <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Expense amount must be greater than zero.',
            ]);
        }

        $amountCurrency = Money::round((string) $entered, 2);
        $resolved = $this->currencies->resolveAt(
            $currency,
            Currency::ledger() ?? $currency,
            $at,
            allowCatalogFallback: true,
        );

        if ((float) $resolved['rate'] <= 0) {
            throw ValidationException::withMessages([
                'currency_id' => 'A valid exchange rate is required for '.$currency->code.'. Configure the Central Exchange Board first.',
            ]);
        }

        $rate = Money::round((string) $resolved['rate'], 8);
        $amountAfn = $this->currencies->toLedger($amountCurrency, $currency, $at);
        $snap = $this->currencies->snapshot($currency);

        return [
            'currency_id' => $snap['id'],
            'currency_code' => $snap['code'],
            'currency_symbol' => $snap['symbol'],
            'amount_currency' => $amountCurrency,
            'exchange_rate' => $rate,
            'rate_effective_at' => $resolved['effective_at'] ?? (string) $at,
            'amount_afn' => Money::round($amountAfn, 2),
        ];
    }
}
