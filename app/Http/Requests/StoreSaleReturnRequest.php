<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_id'          => ['required', 'exists:sales,id'],
            'return_date'      => ['required', 'date'],
            'reason'           => ['nullable', 'string', 'max:1000'],
            'status'           => ['required', 'in:draft,completed,cancelled'],

            'items'                  => ['required', 'array', 'min:1'],
            'items.*.sale_item_id'   => ['required', 'exists:sale_items,id'],
            'items.*.quantity'       => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'sale_id.required'              => 'Please select a sale.',
            'items.required'                => 'At least one item is required.',
            'items.*.sale_item_id.required' => 'Please select a sale item.',
            'items.*.quantity.min'          => 'Quantity must be at least 1.',
        ];
    }
}