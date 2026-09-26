<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_branch_id' => ['required', 'exists:branches,id'],
            'to_branch_id'   => ['required', 'exists:branches,id', 'different:from_branch_id'],
            'transfer_date'  => ['required', 'date'],
            'status'         => ['required', 'in:draft,in_transit,completed,cancelled'],
            'notes'          => ['nullable', 'string', 'max:1000'],

            'items'                   => ['required', 'array', 'min:1'],
            'items.*.from_batch_id'   => ['required', 'exists:product_batches,id'],
            'items.*.quantity'        => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_branch_id.required'  => 'Please select a source branch.',
            'to_branch_id.required'    => 'Please select a destination branch.',
            'to_branch_id.different'   => 'Source and destination branches must be different.',
            'items.required'           => 'At least one item is required.',
            'items.*.from_batch_id.required' => 'Please select a batch.',
            'items.*.quantity.min'     => 'Quantity must be at least 1.',
        ];
    }
}