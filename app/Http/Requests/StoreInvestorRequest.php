<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'   => ['nullable', 'exists:users,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone'     => ['nullable', 'string', 'max:50'],
            'email'     => ['nullable', 'email', 'max:255'],
            'address'   => ['nullable', 'string'],
            'notes'     => ['nullable', 'string'],
            'status'    => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Investor name is required.',
            'status.in'          => 'Status must be either active or inactive.',
        ];
    }
}