<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')?->id ?? $this->route('customer');

        return [
            'name'    => ['required', 'string', 'max:255'],
            'phone'   => [
                'required',
                'string',
                'max:50',
                Rule::unique('customers', 'phone')
                    ->ignore($customerId)
                    ->where(fn ($q) => $q->where('trash', false)),
            ],
            'address' => ['nullable', 'string'],
            'notes'   => ['nullable', 'string'],
            'status'  => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Customer name is required.',
            'phone.required' => 'Customer phone is required.',
            'phone.unique'   => 'This phone number is already registered.',
            'status.in'      => 'Status must be either active or inactive.',
        ];
    }
}