<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReceiveStockRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Product::class) ?? false;
    }

    public function rules(): array
    {
        $activeCurrency = Rule::exists('currencies', 'id')->where(
            fn ($q) => $q->where('trash', false)->where('status', 'active')
        );

        return [
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => SafeData::quantity(true, 1),
            'purchase_currency_id' => ['nullable', 'integer', $activeCurrency],
            'purchase_unit_amount' => SafeData::money(false, 0),
            // Legacy AFN field kept for backward-compatible clients / tests.
            'purchase_price_afn' => SafeData::money(false, 0),
            'sale_price_afn' => SafeData::money(true, 0),
            'funding_source' => ['required', Rule::in(['office', 'investor'])],
            'investor_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('funding_source') === 'investor'),
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q
                    ->where('role', \App\Models\User::ROLE_INVESTOR)
                    ->where('trash', false)
                    ->whereNull('deleted_at')),
            ],
            'investment_id' => [
                'nullable',
                'integer',
                'exists:investments,id',
            ],
            'usd_to_afn_rate' => ['nullable', 'numeric', 'gt:0', 'max:'.SafeData::MONEY_MAX],
            'aed_to_afn_rate' => ['nullable', 'numeric', 'gt:0', 'max:'.SafeData::MONEY_MAX],
            'rate_source' => ['nullable', 'string', 'max:40'],
            'exchange_rate' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:'.SafeData::MONEY_MAX,
            ],
            'profit_per_unit' => SafeData::money(false, 0),
            'profit_currency_id' => ['nullable', 'integer', $activeCurrency],
            'purchase_date' => ['required', 'date'],
            'invoice_number' => SafeData::plainText(100),
            'notes' => SafeData::plainText(SafeData::NOTES_MAX),
            'paid_now' => SafeData::money(false, 0),
            'payment_status' => ['nullable', Rule::in(['paid', 'unpaid', 'partial', 'credit'])],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasUnit = $this->filled('purchase_unit_amount');
            $hasAfn = $this->filled('purchase_price_afn');
            if (! $hasUnit && ! $hasAfn) {
                $validator->errors()->add('purchase_unit_amount', 'Purchase unit price is required.');
            }

            $user = $this->user();
            // Explicit board overrides require FX management permission.
            // Legacy single exchange_rate remains allowed for receive compatibility.
            $hasBoardOverride = $this->filled('usd_to_afn_rate') || $this->filled('aed_to_afn_rate');

            if ($hasBoardOverride && $user && ! $user->canManageExchangeRates()) {
                $validator->errors()->add('usd_to_afn_rate', 'You are not authorized to override exchange rates.');
            }

            foreach (['usd_to_afn_rate', 'aed_to_afn_rate', 'exchange_rate'] as $field) {
                if ($this->filled($field) && (float) $this->input($field) <= 0) {
                    $validator->errors()->add($field, 'Exchange rate must be greater than zero.');
                }
            }

            if ($this->input('funding_source') === 'investor' && $this->filled('investment_id')) {
                $investment = \App\Models\Investment::query()
                    ->whereKey((int) $this->input('investment_id'))
                    ->where('trash', false)
                    ->first();

                if (! $investment) {
                    $validator->errors()->add('investment_id', 'The selected capital record was not found.');
                } elseif ((int) $investment->investor_id !== (int) $this->input('investor_id')) {
                    $validator->errors()->add(
                        'investment_id',
                        'That capital record does not belong to the selected investor.'
                    );
                } elseif (! in_array($investment->status, ['active', 'partially_used'], true)) {
                    $validator->errors()->add('investment_id', 'That capital record is not available for allocation.');
                }
            }

            if ($this->input('funding_source') !== 'investor' && $this->filled('investment_id')) {
                $validator->errors()->add('investment_id', 'Capital allocation requires investor funding.');
            }
        });
    }

    protected function afterSanitize(): void
    {
        if ($this->input('funding_source') === 'office') {
            $this->merge([
                'investor_id' => null,
                'investment_id' => null,
                'profit_per_unit' => 0,
            ]);
        }
    }
}
