<?php

namespace App\Http\Requests;

use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /* ═══ Sale Header ═══ */
            'branch_id'      => ['required', 'exists:branches,id'],
            'customer_id'    => ['nullable', 'exists:customers,id'],
            'sale_date'      => ['required', 'date'],
            'payment_method' => ['nullable', 'in:cash,card,bank,mobile,credit,other'],
            'due_date'       => ['nullable', 'date'],
            'notes'          => ['nullable', 'string'],
            'discount_afn'   => ['nullable', 'numeric', 'min:0'],

            /* ═══ Items ═══ */
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.variant_id'        => ['required', 'exists:product_variants,id'],
            'items.*.quantity'          => ['required', 'integer', 'min:1'],
            'items.*.sale_price_afn'    => ['required', 'numeric', 'min:0'],
            'items.*.discount_afn'      => ['nullable', 'numeric', 'min:0'],

            /* ═══ Payments ═══ */
            'payments'                    => ['nullable', 'array'],
            'payments.*.amount_afn'       => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.payment_method'   => ['required_with:payments', 'in:cash,card,bank,mobile,credit,other'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:255'],
            'payments.*.notes'            => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('payment_method') === 'credit' && ! $this->filled('customer_id')) {
                $validator->errors()->add(
                    'customer_id',
                    'A customer is required when the sale is on credit.',
                );
            }

            $items = $this->input('items', []);
            $costs = ProductVariant::query()
                ->whereIn('id', collect($items)->pluck('variant_id')->filter())
                ->pluck('purchase_price_afn', 'id');

            foreach ($items as $index => $item) {
                $price = (float) ($item['sale_price_afn'] ?? 0);
                $cost = (float) ($costs[$item['variant_id'] ?? null] ?? 0);

                if ($price < 0) {
                    $validator->errors()->add("items.{$index}.sale_price_afn", 'Sale price cannot be negative.');
                }

                if ($price < $cost) {
                    $validator->errors()->add("items.{$index}.sale_price_afn", 'Sale price cannot be less than the purchase price.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'branch_id.required'                => 'Please select a branch.',
            'branch_id.exists'                  => 'Selected branch does not exist.',
            'customer_id.exists'                => 'Selected customer does not exist.',
            'sale_date.required'                => 'Sale date is required.',
            'sale_date.date'                    => 'Sale date must be a valid date.',

            'items.required'                    => 'At least one item is required.',
            'items.min'                         => 'At least one item is required.',
            'items.*.variant_id.required'       => 'Please select a product.',
            'items.*.variant_id.exists'         => 'Selected product does not exist.',
            'items.*.quantity.required'         => 'Quantity is required.',
            'items.*.quantity.min'              => 'Quantity must be at least 1.',
            'items.*.sale_price_afn.required'   => 'Sale price is required.',
            'items.*.sale_price_afn.min'        => 'Sale price must be greater than or equal to 0.',

            'payments.*.amount_afn.required_with'      => 'Amount is required for each payment.',
            'payments.*.amount_afn.min'                => 'Payment amount must be greater than 0.',
            'payments.*.payment_method.required_with'  => 'Payment method is required.',
        ];
    }
}