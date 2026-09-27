<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Models\ProductVariant;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaleRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        $sale = $this->route('sale');

        return $sale
            ? ($this->user()?->can('update', $sale) ?? false)
            : ($this->user() !== null);
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'sale_date' => ['required', 'date'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'bank', 'mobile', 'credit', 'other'])],
            'notes' => SafeData::plainText(SafeData::NOTES_MAX),
            'discount_afn' => SafeData::money(false, 0),

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => SafeData::quantity(true, 1),
            'items.*.sale_price_afn' => SafeData::money(true, 0),
            'items.*.discount_afn' => SafeData::money(false, 0),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
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
}
