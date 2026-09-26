<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentPurchaseAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_id'              => ['required', 'exists:purchases,id'],
            'investment_id'            => ['required', 'exists:investments,id'],
            'allocated_amount_afn' => ['required', 'numeric', 'min:0.01'],
            'notes'                    => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_id.required'              => 'Please select a purchase.',
            'investment_id.required'            => 'Please select an investment.',
            'allocated_amount_afn.required' => 'Allocated amount is required.',
            'allocated_amount_afn.min'      => 'Allocated amount must be greater than 0.',
        ];
    }
}