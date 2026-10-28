<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Models\User;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_INVESTOR)),
            ],
            'source_type' => ['required', Rule::in(['cash', 'goods'])],
            'parent_investment_id' => ['nullable', 'integer', 'exists:investments,id'],
            'currency_id' => [
                Rule::requiredIf(fn () => $this->input('source_type') === 'cash'),
                'nullable',
                'integer',
                Rule::exists('currencies', 'id')->where(
                    fn ($q) => $q->where('trash', false)->where('status', 'active')->whereNull('deleted_at')
                ),
            ],
            'capital_amount' => SafeData::money(false, 0),
            'capital_amount_afn' => SafeData::money(false, 0),
            'investment_date' => ['required', 'date'],
            'notes' => SafeData::plainText(SafeData::NOTES_MAX),
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select an investor.',
            'user_id.exists' => 'Selected user must be an investor account.',
            'investment_date.required' => 'Investment date is required.',
        ];
    }
}