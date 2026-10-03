<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Models\User;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestorRequest extends FormRequest
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
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_INVESTOR)),
            ],
            'full_name' => SafeData::title(true),
            'phone'     => SafeData::phone(),
            'email'     => SafeData::email(),
            'address'   => SafeData::plainText(500),
            'notes'     => SafeData::plainText(SafeData::NOTES_MAX),
            'status'    => ['required', Rule::in(['active', 'inactive'])],

            // ✅ سرمایه اولیه — اختیاری (اگر جنسی است خالی بگذارید)
            'capital_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],

            // ✅ نرخ ارز — اختیاری (فقط برای نقدی معنی دارد)
            'exchange_rate'  => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Investor name is required.',
            'user_id.exists'     => 'Linked user must be an investor account.',
            'status.in'          => 'Status must be either active or inactive.',
            'capital_amount.min' => 'Capital amount cannot be negative.',
            'exchange_rate.min'  => 'Exchange rate cannot be negative.',
        ];
    }
}