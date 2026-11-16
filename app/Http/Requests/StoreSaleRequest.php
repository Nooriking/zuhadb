<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Models\Currency;
use App\Models\ProductVariant;
use App\Services\CurrencyService;
use App\Support\Money;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Sale::class) ?? false;
    }

    public function rules(): array
    {
        $activeCurrency = Rule::exists('currencies', 'id')->where(
            fn ($q) => $q->where('trash', false)->where('status', 'active')
        );

        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'sale_date' => ['required', 'date'],
            'sale_currency_id' => ['nullable', 'integer', $activeCurrency],
            'usd_to_afn_rate' => ['nullable', 'numeric', 'gt:0', 'max:'.SafeData::MONEY_MAX],
            'aed_to_afn_rate' => ['nullable', 'numeric', 'gt:0', 'max:'.SafeData::MONEY_MAX],
            'rate_source' => ['nullable', 'string', 'max:40'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'bank', 'mobile', 'credit', 'other'])],
            'due_date' => ['nullable', 'date', 'after_or_equal:sale_date'],
            'notes' => SafeData::plainText(SafeData::NOTES_MAX),
            'discount_afn' => SafeData::money(false, 0),
            'discount_amount' => SafeData::money(false, 0),

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => SafeData::quantity(true, 1),
            'items.*.sale_unit_amount' => SafeData::money(false, 0),
            'items.*.sale_price_afn' => SafeData::money(false, 0),
            'items.*.discount_afn' => SafeData::money(false, 0),
            'items.*.discount_amount' => SafeData::money(false, 0),

            'payments' => ['nullable', 'array', 'max:20'],
            'payments.*.amount_afn' => SafeData::money(false, 0.01),
            'payments.*.amount' => SafeData::money(false, 0.01),
            'payments.*.currency_id' => ['nullable', 'integer', $activeCurrency],
            'payments.*.payment_method' => ['required_with:payments', Rule::in(['cash', 'card', 'bank', 'mobile', 'credit', 'other'])],
            'payments.*.reference_number' => SafeData::plainText(100),
            'payments.*.notes' => SafeData::plainText(500),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('payment_method') === 'credit' && ! $this->filled('customer_id')) {
                $validator->errors()->add(
                    'customer_id',
                    'A customer is required when the sale is on credit.',
                );
            }

            $user = $this->user();
            $hasRateOverride = $this->filled('usd_to_afn_rate') || $this->filled('aed_to_afn_rate');
            if ($hasRateOverride && $user && ! $user->canManageExchangeRates()) {
                $validator->errors()->add('usd_to_afn_rate', 'You are not authorized to override exchange rates.');
            }

            $items = $this->input('items', []);
            foreach ($items as $index => $item) {
                $hasUnit = isset($item['sale_unit_amount']) && $item['sale_unit_amount'] !== '' && $item['sale_unit_amount'] !== null;
                $hasAfn = isset($item['sale_price_afn']) && $item['sale_price_afn'] !== '' && $item['sale_price_afn'] !== null;
                if (! $hasUnit && ! $hasAfn) {
                    $validator->errors()->add("items.{$index}.sale_unit_amount", 'Sale unit price is required.');
                }
            }

            try {
                /** @var CurrencyService $fx */
                $fx = app(CurrencyService::class);
                $ledger = Currency::ledger();
                $saleCurrency = $this->filled('sale_currency_id')
                    ? Currency::query()->notTrashed()->find((int) $this->input('sale_currency_id'))
                    : $ledger;

                if (! $saleCurrency || ! $ledger) {
                    return;
                }

                $overrides = [];
                if ($this->filled('usd_to_afn_rate')) {
                    $overrides['usd_to_afn'] = (string) $this->input('usd_to_afn_rate');
                }
                if ($this->filled('aed_to_afn_rate')) {
                    $overrides['aed_to_afn'] = (string) $this->input('aed_to_afn_rate');
                }

                $board = $fx->boardAt($this->input('sale_date'), $overrides, allowCatalogFallback: true);
                $costs = ProductVariant::query()
                    ->whereIn('id', collect($items)->pluck('variant_id')->filter())
                    ->pluck('purchase_price_afn', 'id');

                foreach ($items as $index => $item) {
                    $code = strtoupper((string) $saleCurrency->code);
                    if (isset($item['sale_unit_amount']) && $item['sale_unit_amount'] !== '' && $item['sale_unit_amount'] !== null) {
                        $unitOriginal = Money::round((string) $item['sale_unit_amount'], 4);
                        $priceAfn = (float) $board->equivalents($unitOriginal, $code, 2)['afn'];
                    } else {
                        $priceAfn = (float) ($item['sale_price_afn'] ?? 0);
                    }

                    $cost = (float) ($costs[$item['variant_id'] ?? null] ?? 0);
                    if ($priceAfn < $cost) {
                        $validator->errors()->add(
                            "items.{$index}.sale_unit_amount",
                            'Sale price cannot be less than the purchase price.'
                        );
                    }
                }
            } catch (\Throwable) {
                // Board resolution errors are reported by the service on submit.
            }
        });
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Please select a branch.',
            'items.required' => 'At least one item is required.',
            'items.max' => 'Too many line items on one sale.',
        ];
    }
}
