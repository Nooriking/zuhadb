<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Models\Expense;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'title' => SafeData::title(true),
            'category' => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where(
                    fn ($q) => $q->where('trash', false)->where('status', 'active')->whereNull('deleted_at')
                ),
            ],
            'amount' => SafeData::money(true, 0.01),
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(array_keys(Expense::PAYMENT_METHODS))],
            'description' => SafeData::plainText(SafeData::NOTES_MAX),
        ];
    }

    public function messages(): array
    {
        return [
            'currency_id.required' => 'Select a currency.',
            'currency_id.exists' => 'The selected currency is invalid or inactive.',
            'amount.required' => 'Enter the expense amount.',
            'amount.min' => 'Expense amount must be greater than zero.',
        ];
    }
}
