<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Models\User;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvestorRequest extends FormRequest
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

            // ✅ در ویرایش هم اجازه می‌دهیم (اختیاری)
            'capital_amount' => ['nullable', 'numeric', 'min:0'],
            'exchange_rate'  => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Investor name is required.',
            'user_id.exists'     => 'Linked user must be an investor account.',
            'status.in'          => 'Status must be either active or inactive.',
        ];
    }
}