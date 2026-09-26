<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'    => ['required', 'string', 'max:10', 'unique:currencies,code'],
            'name'    => ['required', 'string', 'max:255'],
            'symbol'  => ['nullable', 'string', 'max:10'],
            'is_base' => ['boolean'],
            'status'  => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'  => 'Currency code is required (e.g. AFN, USD).',
            'code.unique'    => 'This currency code is already in use.',
            'code.max'       => 'Currency code must not exceed 10 characters.',
            'name.required'  => 'Currency name is required.',
            'status.in'      => 'Status must be either active or inactive.',
        ];
    }
}