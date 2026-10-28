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
        return [
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => SafeData::quantity(true, 1),
            'quantity_unit' => ['nullable', Rule::in(['base', 'pack'])],
            'purchase_price_afn' => SafeData::money(true, 0),
            'sale_price_afn' => SafeData::money(true, 0),
            'funding_source' => ['required', Rule::in(['office', 'investor'])],
            'investor_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('funding_source') === 'investor'),
                'integer',
                'exists:investors,id',
            ],
            'investment_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('funding_source') === 'investor'),
                'integer',
                'exists:investments,id',
            ],
            'exchange_rate' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('funding_source') === 'investor'),
                'numeric',
                'min:0.01',
                'max:'.SafeData::MONEY_MAX,
            ],
            'profit_per_unit' => SafeData::money(false, 0),
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
            $purchase = (float) $this->input('purchase_price_afn', 0);
            $sale = (float) $this->input('sale_price_afn', 0);

            if ($sale < $purchase) {
                $validator->errors()->add('sale_price_afn', 'Sale price cannot be less than the purchase price.');
            }

            if ($this->input('funding_source') === 'investor') {
                $investmentId = (int) $this->input('investment_id');
                $investorId = (int) $this->input('investor_id');

                if ($investmentId > 0 && $investorId > 0) {
                    $matches = \App\Models\Investment::query()
                        ->whereKey($investmentId)
                        ->where('investor_id', $investorId)
                        ->exists();

                    if (! $matches) {
                        $validator->errors()->add('investment_id', 'That investment does not belong to the selected investor.');
                    }
                }
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
