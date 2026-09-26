<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                        => ['required', 'string', 'max:255'],
            'phone'                       => ['nullable', 'string', 'max:50'],
            'email'                       => ['nullable', 'email', 'max:255'],
            'contact_person'              => ['nullable', 'string', 'max:255'],
            'address'                     => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric'],
            'notes'                       => ['nullable', 'string'],
            'status'                      => ['required', 'in:active,inactive'],
            'invoice_no'                  => ['nullable', 'string', 'max:50'],
            'purchase_date'               => ['nullable', 'date'],
            'exchange_rate'               => ['nullable', 'numeric', 'min:0'],
            'total_afn'                   => ['nullable', 'numeric', 'min:0'],
            'paid_now'                    => ['nullable', 'numeric', 'min:0'],
            'bill_image'                  => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Supplier name is required.',
            'email.email'   => 'Please enter a valid email address.',
            'status.in'     => 'Status must be either active or inactive.',
        ];
    }
}