<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestorWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'investor_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q
                    ->where('role', User::ROLE_INVESTOR)
                    ->where('trash', false)
                    ->whereNull('deleted_at')),
            ],
            'investment_id' => ['nullable', 'exists:investments,id'],
            'type' => ['required', Rule::in(['profit', 'capital'])],
            'currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where(
                    fn ($q) => $q->where('trash', false)->where('status', 'active')->whereNull('deleted_at')
                ),
            ],
            'amount' => SafeData::money(true, 0.01),
            'withdrawal_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'paid'])],
            'notes' => SafeData::plainText(SafeData::NOTES_MAX),
        ];
    }

    public function messages(): array
    {
        return [
            'investor_id.required' => 'Select an investor.',
            'investor_id.exists' => 'Investor was not found.',
            'type.required' => 'Withdrawal type is required.',
            'type.in' => 'Withdrawal type must be profit or capital.',
            'currency_id.required' => 'Select a withdrawal currency.',
            'amount.required' => 'Enter the withdrawal amount.',
            'amount.min' => 'Withdrawal amount must be greater than zero.',
        ];
    }
}
