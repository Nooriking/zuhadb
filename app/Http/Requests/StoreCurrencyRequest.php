<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_base' => $this->boolean('is_base') || $this->boolean('is_default'),
            'decimal_places' => $this->input('decimal_places', 2),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::unique('currencies', 'code')->where(fn ($query) => $query->where('trash', false)->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:16'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:8'],
            'rate_to_default' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'is_base' => ['boolean'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Currency code is required (e.g. USD, AFN, EUR).',
            'code.size' => 'Currency code must be a 3-letter ISO code.',
            'code.regex' => 'Currency code must be 3 uppercase letters (A–Z).',
            'code.unique' => 'This currency code is already in use.',
            'name.required' => 'Currency name is required.',
            'symbol.required' => 'Currency symbol is required (e.g. $ or ؋).',
            'decimal_places.required' => 'Number of decimal places is required.',
            'decimal_places.min' => 'Decimal places must be between 0 and 8.',
            'decimal_places.max' => 'Decimal places must be between 0 and 8.',
            'rate_to_default.required' => 'Exchange rate is required.',
            'rate_to_default.gt' => 'Exchange rate must be greater than zero.',
            'status.in' => 'Status must be either active or inactive.',
        ];
    }
}
