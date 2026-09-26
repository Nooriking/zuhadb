<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'              => ['required', 'exists:users,id'],
            'parent_investment_id' => ['nullable', 'exists:investments,id'],
            'capital_amount_afn'   => ['nullable', 'numeric', 'min:0'],
            'investment_date'      => ['required', 'date'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select an investor.',
            'investment_date.required'         => 'Investment date is required.',
        ];
    }
}