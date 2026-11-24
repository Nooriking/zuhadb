<?php

namespace App\Http\Requests;

use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBoardExchangeRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageExchangeRates() ?? false;
    }

    public function rules(): array
    {
        return [
            'usd_to_afn' => ['required', 'numeric', 'gt:0', 'max:'.SafeData::MONEY_MAX],
            'aed_to_afn' => ['required', 'numeric', 'gt:0', 'max:'.SafeData::MONEY_MAX],
            'effective_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', Rule::in(['manual', 'market', 'bank', 'other', 'sale_board'])],
        ];
    }

    public function messages(): array
    {
        return [
            'usd_to_afn.required' => 'USD→AFN rate is required.',
            'usd_to_afn.gt' => 'USD→AFN must be greater than zero. Example: 1 USD = 70 AFN is entered as 70.',
            'aed_to_afn.required' => 'AED→AFN rate is required.',
            'aed_to_afn.gt' => 'AED→AFN must be greater than zero. Example: 1 AED = 19 AFN is entered as 19.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('source')) {
            $this->merge(['source' => 'sale_board']);
        }
    }
}
