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

    protected function prepareForValidation(): void
    {
        $investor = $this->route('investor');
        if ($investor && is_object($investor) && isset($investor->id)) {
            $this->merge(['user_id' => $investor->id]);
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q
                    ->where('role', User::ROLE_INVESTOR)
                    ->where('status', 'active')
                    ->where('trash', false)
                    ->whereNull('deleted_at')),
            ],
            'source_type' => ['required', Rule::in(['cash', 'goods'])],
            'parent_investment_id' => ['nullable', 'integer', 'exists:investments,id'],
            'currency_id' => [
                Rule::requiredIf(fn () => (float) ($this->input('capital_amount') ?? $this->input('capital_amount_afn') ?? 0) > 0
                    || $this->input('source_type') === 'goods'),
                'nullable',
                'integer',
                Rule::exists('currencies', 'id')->where(
                    fn ($q) => $q->where('trash', false)->where('status', 'active')->whereNull('deleted_at')
                ),
            ],
            'capital_amount' => [
                Rule::requiredIf(fn () => $this->input('source_type') === 'goods'
                    || $this->input('source_type') === 'cash'),
                'nullable',
                'numeric',
                'gt:0',
                'max:'.SafeData::MONEY_MAX,
            ],
            'capital_amount_afn' => SafeData::money(false, 0),
            'investment_date' => ['required', 'date'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:'.SafeData::MONEY_MAX],
            'effective_at' => ['nullable', 'date'],
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