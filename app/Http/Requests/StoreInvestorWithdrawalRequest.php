<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestorWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'investor_id'       => ['required', 'exists:investors,id'],
            'investment_id'     => ['nullable', 'exists:investments,id'],
            'type'              => ['required', Rule::in(['profit', 'capital'])],
            'amount_afn'        => ['required', 'numeric', 'min:0.01'],
            'withdrawal_date'   => ['required', 'date'],
            'status'            => ['nullable', Rule::in(['pending', 'approved', 'paid', 'rejected'])],
            'notes'             => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'investor_id.required' => 'Select an investor.',
            'investor_id.exists'   => 'Investor was not found.',
            'type.required' => 'Withdrawal type is required.',
            'type.in'       => 'Withdrawal type must be profit or capital.',
            'amount_afn.min' => 'Withdrawal amount must be greater than zero.',
        ];
    }
}