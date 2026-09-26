<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_currency_id' => ['required', 'exists:currencies,id', 'different:to_currency_id'],
            'to_currency_id'   => ['required', 'exists:currencies,id'],
            'rate'             => ['required', 'numeric', 'gt:0'],
            'effective_at'     => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_currency_id.required'  => 'Please select the source currency.',
            'from_currency_id.exists'    => 'The selected source currency does not exist.',
            'from_currency_id.different' => 'Source and target currencies must be different.',
            'to_currency_id.required'    => 'Please select the target currency.',
            'to_currency_id.exists'      => 'The selected target currency does not exist.',
            'rate.required'              => 'Exchange rate is required.',
            'rate.numeric'               => 'Exchange rate must be a number.',
            'rate.gt'                    => 'Exchange rate must be greater than 0.',
            'effective_at.required'      => 'Effective date is required.',
            'effective_at.date'          => 'Effective date must be a valid date.',
        ];
    }
}