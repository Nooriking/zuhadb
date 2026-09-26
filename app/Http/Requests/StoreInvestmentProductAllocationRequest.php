<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentProductAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'investment_id'                       => ['required', 'exists:investments,id'],
            'investment_purchase_allocation_id'   => ['nullable', 'exists:investment_purchase_allocations,id'],
            'batch_id'                            => ['required', 'exists:product_batches,id'],
            'variant_id'                          => ['required', 'exists:product_variants,id'],
            'funded_quantity'      => ['required', 'integer', 'min:1'],
            'cost_per_unit_afn'    => ['required', 'numeric', 'min:0'],
            'profit_per_unit_afn'  => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'investment_id.required'         => 'Please select an investment.',
            'batch_id.required'              => 'Please select a batch.',
            'variant_id.required'            => 'Please select a product variant.',
            'funded_quantity.required'       => 'Funded quantity is required.',
            'funded_quantity.min'            => 'Funded quantity must be at least 1.',
            'cost_per_unit_afn.required'     => 'Cost per unit (USD) is required.',
            'profit_per_unit_afn.required'   => 'Profit per unit (USD) is required.',
        ];
    }
}