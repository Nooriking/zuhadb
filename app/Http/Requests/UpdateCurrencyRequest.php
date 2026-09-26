<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currencyId = $this->route('currency')->id ?? null;

        return [
            'code'    => ['required', 'string', 'max:10', Rule::unique('currencies', 'code')->ignore($currencyId)],
            'name'    => ['required', 'string', 'max:255'],
            'symbol'  => ['nullable', 'string', 'max:10'],
            'is_base' => ['boolean'],
            'status'  => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'  => 'Currency code is required.',
            'code.unique'    => 'This currency code is already in use.',
            'name.required'  => 'Currency name is required.',
            'status.in'      => 'Status must be either active or inactive.',
        ];
    }
}