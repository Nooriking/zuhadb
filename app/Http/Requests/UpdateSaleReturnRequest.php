<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_date'      => ['required', 'date'],
            'reason'           => ['nullable', 'string', 'max:1000'],
            'status'           => ['required', 'in:draft,completed,cancelled'],

            'items'                  => ['required', 'array', 'min:1'],
            'items.*.sale_item_id'   => ['required', 'exists:sale_items,id'],
            'items.*.quantity'       => ['required', 'integer', 'min:1'],
        ];
    }
}