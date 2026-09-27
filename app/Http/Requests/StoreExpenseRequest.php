<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Models\Expense;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
            'amount_afn' => SafeData::money(true, 0.01),
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(array_keys(Expense::PAYMENT_METHODS))],
            'description' => SafeData::plainText(SafeData::NOTES_MAX),
        ];
    }
}
