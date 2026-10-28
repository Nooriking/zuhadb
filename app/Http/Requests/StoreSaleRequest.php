<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Models\ProductVariant;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Sale::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'sale_date' => ['required', 'date'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'bank', 'mobile', 'credit', 'other'])],
            'due_date' => ['nullable', 'date', 'after_or_equal:sale_date'],
            'notes' => SafeData::plainText(SafeData::NOTES_MAX),
            'discount_afn' => SafeData::money(false, 0),

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => SafeData::quantity(true, 1),
            'items.*.quantity_unit' => ['nullable', Rule::in(['base', 'pack'])],
            'items.*.sale_price_afn' => SafeData::money(true, 0),
            'items.*.discount_afn' => SafeData::money(false, 0),

            'payments' => ['nullable', 'array', 'max:20'],
            'payments.*.amount_afn' => SafeData::money(true, 0.01),
            'payments.*.payment_method' => ['required_with:payments', Rule::in(['cash', 'card', 'bank', 'mobile', 'credit', 'other'])],
            'payments.*.reference_number' => SafeData::plainText(100),
            'payments.*.notes' => SafeData::plainText(500),
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

                if ($price < $cost) {
                    $validator->errors()->add("items.{$index}.sale_price_afn", 'Sale price cannot be less than the purchase price.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Please select a branch.',
            'items.required' => 'At least one item is required.',
            'items.max' => 'Too many line items on one sale.',
        ];
    }
}
