<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    use Concerns\ValidatesProductMoneyAndUnits;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        /* ─────────────── Product fields ─────────────── */

        if (isset($payload['gender'])) {
            $payload['gender'] = strtolower(trim((string) $payload['gender']));
        }

        if (isset($payload['status'])) {
            $payload['status'] = strtolower(trim((string) $payload['status']));
        }

        if (isset($payload['name'])) {
            $payload['name'] = trim((string) $payload['name']);
        }

        if (isset($payload['description']) && trim((string) $payload['description']) === '') {
            $payload['description'] = null;
        }

        if (isset($payload['brand_id']) && in_array($payload['brand_id'], ['', 'none', 'null'], true)) {
            $payload['brand_id'] = null;
        }

        foreach (['usd_to_afn_rate', 'usd_to_aed_rate'] as $rateField) {
            if (isset($payload[$rateField]) && $payload[$rateField] === '') {
                $payload[$rateField] = null;
            }
        }

        /* ─────────────── Variants ─────────────── */

        if (isset($payload['variants']) && is_array($payload['variants'])) {
            foreach ($payload['variants'] as $i => $variant) {
                if (! is_array($variant)) {
                    continue;
                }

                // ❌ حذف normalization مربوط به status

                foreach (['description', 'concentration'] as $f) {
                    if (isset($variant[$f]) && trim((string) $variant[$f]) === '') {
                        $payload['variants'][$i][$f] = null;
                    }
                }

                foreach ([
                    'volume_ml',
                    'purchase_price_afn', 'purchase_price_usd',
                    'sale_price_afn', 'wholesale_price_afn', 'wholesale_min_qty',
                    'profit_per_unit', 'minimum_stock', 'stock_quantity',
                ] as $f) {
                    if (isset($variant[$f]) && $variant[$f] === '') {
                        $payload['variants'][$i][$f] = null;
                    }
                }

                if (! empty($variant['concentration'])) {
                    $payload['variants'][$i]['concentration'] =
                        $this->normalizeConcentration($variant['concentration']);
                }

                /* ─── Barcodes ─── */
                if (isset($variant['barcodes']) && is_array($variant['barcodes'])) {
                    foreach ($variant['barcodes'] as $j => $bc) {
                        if (! is_array($bc)) {
                            continue;
                        }

                        if (isset($bc['barcode_type'])) {
                            $payload['variants'][$i]['barcodes'][$j]['barcode_type'] =
                                $this->normalizeBarcodeType($bc['barcode_type']);
                        }

                        if (isset($bc['is_primary'])) {
                            $payload['variants'][$i]['barcodes'][$j]['is_primary'] =
                                filter_var($bc['is_primary'], FILTER_VALIDATE_BOOLEAN);
                        }

                        if (isset($bc['barcode'])) {
                            $payload['variants'][$i]['barcodes'][$j]['barcode'] =
                                trim((string) $bc['barcode']);
                        }

                        if (isset($bc['current_stock']) && $bc['current_stock'] === '') {
                            $payload['variants'][$i]['barcodes'][$j]['current_stock'] = 0;
                        }

                        if (empty($payload['variants'][$i]['barcodes'][$j]['barcode'])) {
                            unset($payload['variants'][$i]['barcodes'][$j]);
                        }
                    }

                    if (! empty($payload['variants'][$i]['barcodes'])) {
                        $payload['variants'][$i]['barcodes'] =
                            array_values($payload['variants'][$i]['barcodes']);
                    } else {
                        $payload['variants'][$i]['barcodes'] = [];
                    }
                }
            }
        }

        /* ─────────────── Purchases ─────────────── */

        if (isset($payload['purchases']) && is_array($payload['purchases'])) {
            foreach ($payload['purchases'] as $i => $purchase) {
                if (! is_array($purchase)) {
                    continue;
                }

                foreach (['investor_id', 'supplier_id'] as $fk) {
                    if (
                        isset($purchase[$fk]) &&
                        in_array($purchase[$fk], ['', 'none', 'null', 'store'], true)
                    ) {
                        $payload['purchases'][$i][$fk] = null;
                    }
                }

                foreach ([
                    'quantity', 'profit_per_unit', 'line_total', 'line_profit',
                ] as $f) {
                    if (isset($purchase[$f]) && $purchase[$f] === '') {
                        $payload['purchases'][$i][$f] = null;
                    }
                }

                foreach (['invoice_number', 'notes', 'payment_status'] as $f) {
                    if (isset($purchase[$f]) && trim((string) $purchase[$f]) === '') {
                        $payload['purchases'][$i][$f] = null;
                    }
                }

                if (empty($purchase['purchase_date'])) {
                    $payload['purchases'][$i]['purchase_date'] = now()->toDateString();
                }

                if (empty($payload['purchases'][$i]['payment_status'])) {
                    $payload['purchases'][$i]['payment_status'] = 'unpaid';
                }

                if (isset($purchase['variant_index']) && is_numeric($purchase['variant_index'])) {
                    $payload['purchases'][$i]['variant_index'] = (int) $purchase['variant_index'];
                }
            }
        }

        $this->replace($payload);
    }

    private function normalizeConcentration(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);
        $lower = strtolower($value);

        $keys = [
            'parfum', 'edp', 'edt', 'edc',
            'edt_legere', 'body_mist', 'attar', 'oud', 'other',
        ];

        if (in_array($lower, $keys, true)) {
            return $lower;
        }

        $map = [
            'Parfum'                => 'parfum',
            'Eau de Parfum (EDP)'   => 'edp',
            'Eau de Toilette (EDT)' => 'edt',
            'Eau de Cologne (EDC)'  => 'edc',
            'EDT Légère'            => 'edt_legere',
            'EDT Legere'            => 'edt_legere',
            'Body Mist'             => 'body_mist',
            'Attar'                 => 'attar',
            'Oud'                   => 'oud',
            'Other'                 => 'other',
        ];

        return $map[$value] ?? ($keys[array_search($lower, $keys, true)] ?? 'other');
    }

    private function normalizeBarcodeType(?string $value): string
    {
        if (! $value) {
            return 'EAN13';
        }

        $value = strtoupper(str_replace(['-', ' '], '', trim($value)));

        $map = [
            'EAN13'   => 'EAN13',
            'EAN8'    => 'EAN8',
            'UPC'     => 'UPC',
            'UPCA'    => 'UPC',
            'CODE128' => 'CODE128',
            'CODE39'  => 'CODE39',
            'QR'      => 'QR',
            'QRCODE'  => 'QR',
        ];

        return $map[$value] ?? 'other';
    }

    public function rules(): array
    {
        return [
            /* ─────────────── Product ─────────────── */
            'name'        => ['required', 'string', 'max:255'],
            'brand_id'    => ['nullable', 'integer', 'exists:brands,id'],
            'gender'      => ['required', Rule::in(['male', 'female', 'unisex'])],
            'status'      => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'image'       => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            ...$this->productUnitRules(),
            ...$this->productExchangeRateRules(),

            /* ─────────────── Variants ─────────────── */
            'variants'                       => ['required', 'array', 'min:1'],
            'variants.*.description'         => ['nullable', 'string', 'max:5000'],
            'variants.*.image'               => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'variants.*.volume_ml'           => ['nullable', 'integer', 'min:0', 'max:100000'],
            'variants.*.concentration'       => ['nullable', Rule::in([
                'parfum', 'edp', 'edt', 'edc',
                'edt_legere', 'body_mist', 'attar', 'oud', 'other',
            ])],
            'variants.*.purchase_price_afn'  => ['nullable', 'numeric', 'min:0'],
            'variants.*.sale_price_afn'      => ['required', 'numeric', 'min:0'],
            'variants.*.wholesale_price_afn' => ['nullable', 'numeric', 'min:0'],
            ...$this->variantMoneyRules(),
            'variants.*.wholesale_min_qty'   => ['nullable', 'integer', 'min:0'],
            'variants.*.profit_per_unit'     => ['nullable', 'numeric', 'min:0'],
            'variants.*.minimum_stock'       => ['nullable', 'integer', 'min:0'],
            'variants.*.stock_quantity'      => ['nullable', 'integer', 'min:0'],
            // ❌ حذف: 'variants.*.status'

            /* ─────────────── Barcodes ─────────────── */
            'variants.*.barcodes'                => ['nullable', 'array'],
            'variants.*.barcodes.*.barcode'      => ['required', 'string', 'max:100', 'distinct'],
            'variants.*.barcodes.*.barcode_type' => ['nullable', Rule::in([
                'EAN13', 'EAN8', 'UPC', 'CODE128', 'CODE39', 'QR', 'other',
            ])],
            'variants.*.barcodes.*.is_primary'   => ['nullable', 'boolean'],
            'variants.*.barcodes.*.status'       => ['nullable', Rule::in(['active', 'inactive'])],
            'variants.*.barcodes.*.current_stock'=> ['nullable', 'integer', 'min:0'],

            /* ─────────────── Purchases ─────────────── */
            'purchases'                       => ['nullable', 'array'],
            'purchases.*.variant_index'       => ['required', 'integer', 'min:0'],
            'purchases.*.investor_id'         => ['nullable', 'integer', 'exists:investors,id'],
            'purchases.*.supplier_id'         => ['nullable', 'integer', 'exists:suppliers,id'],
            'purchases.*.quantity'            => ['required', 'integer', 'min:1'],
            'purchases.*.profit_per_unit'     => ['nullable', 'numeric', 'min:0'],
            'purchases.*.line_total'          => ['nullable', 'numeric', 'min:0'],
            'purchases.*.line_profit'         => ['nullable', 'numeric', 'min:0'],
            'purchases.*.invoice_number'      => ['nullable', 'string', 'max:100'],
            'purchases.*.purchase_date'       => ['nullable', 'date'],
            'purchases.*.exchange_rate'       => ['nullable', 'numeric', 'min:0.01'],
            'purchases.*.paid_now'            => ['nullable', 'numeric', 'min:0'],
            'purchases.*.payment_status'      => ['nullable', Rule::in(['paid', 'unpaid', 'partial', 'credit'])],
            'purchases.*.notes'               => ['nullable', 'string', 'max:5000'],
            'purchases.*.bill_image'          => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('variants', []) as $index => $variant) {
                $purchase = (float) ($variant['purchase_price_afn'] ?? 0);
                $sale = (float) ($variant['sale_price_afn'] ?? 0);

                if ($purchase < 0 || $sale < 0) {
                    $validator->errors()->add("variants.{$index}.sale_price_afn", 'Prices cannot be negative.');
                }

                if ($sale < $purchase) {
                    $validator->errors()->add("variants.{$index}.sale_price_afn", 'Sale price cannot be less than the purchase price.');
                }
            }

            $this->validateProductMoneyAndUnits($validator);
        });
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Product name is required.',
            'name.max'           => 'Product name must not exceed 255 characters.',

            'gender.required'    => 'Gender is required.',
            'gender.in'          => 'Gender must be one of: male, female, unisex.',

            'status.required'    => 'Status is required.',
            'status.in'          => 'Status must be either "active" or "inactive".',

            'brand_id.exists'    => 'The selected brand does not exist.',

            'variants.required'              => 'At least one variant is required.',
            'variants.min'                   => 'At least one variant is required.',
            // ❌ حذف: variants.*.status messages
            'variants.*.concentration.in'    => 'The selected concentration is invalid.',
            'variants.*.volume_ml.integer'   => 'Volume must be an integer.',
            'variants.*.volume_ml.min'       => 'Volume cannot be negative.',
            'variants.*.sale_price_afn.required' => 'Sale price is required.',
            'variants.*.sale_price_afn.numeric'  => 'Sale price must be numeric.',
            'variants.*.sale_price_afn.min'      => 'Sale price cannot be negative.',

            'variants.*.barcodes.*.barcode.required' => 'Barcode is required.',
            'variants.*.barcodes.*.barcode.distinct' => 'Duplicate barcode in the same variant.',
            'variants.*.barcodes.*.barcode_type.in'  => 'The selected barcode type is invalid.',

            'purchases.*.variant_index.required' => 'Purchase must reference a variant.',
            'purchases.*.variant_index.integer'  => 'Variant index must be an integer.',
            'purchases.*.quantity.required'      => 'Purchase quantity is required.',
            'purchases.*.quantity.min'           => 'Purchase quantity must be at least 1.',
            'purchases.*.investor_id.exists'     => 'The selected investor does not exist.',
            'purchases.*.supplier_id.exists'     => 'The selected supplier does not exist.',
            'purchases.*.payment_status.in'      => 'Payment status must be paid, unpaid, partial, or credit.',
            'usd_to_afn_rate.numeric'            => 'USD to AFN must be a number of afghanis per 1 US dollar.',
            'usd_to_afn_rate.gt'                 => 'USD to AFN must be greater than zero. Example: 1 USD = 70 AFN is entered as 70.',
            'usd_to_afn_rate.regex'              => 'USD to AFN allows at most 8 decimal places.',
            'usd_to_aed_rate.numeric'            => 'USD to AED must be a number of dirhams per 1 US dollar.',
            'usd_to_aed_rate.gt'                 => 'USD to AED must be greater than zero. Example: 1 USD = 3.67 AED is entered as 3.67.',
            'usd_to_aed_rate.regex'              => 'USD to AED allows at most 8 decimal places.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'                                => 'product name',
            'gender'                              => 'gender',
            'status'                              => 'status',
            'brand_id'                            => 'brand',
            'description'                         => 'description',

            'variants.*.description'              => 'variant description',
            'variants.*.volume_ml'                => 'volume',
            'variants.*.concentration'            => 'concentration',
            'variants.*.sale_price_afn'           => 'sale price',
            // ❌ حذف: 'variants.*.status' => 'variant status',
            'variants.*.barcodes.*.barcode'       => 'barcode',
            'variants.*.barcodes.*.barcode_type'  => 'barcode type',

            'purchases.*.variant_index'           => 'variant index',
            'purchases.*.investor_id'             => 'investor',
            'purchases.*.supplier_id'             => 'supplier',
            'purchases.*.quantity'                => 'quantity',
            'purchases.*.invoice_number'          => 'invoice number',
            'purchases.*.purchase_date'           => 'purchase date',
            'purchases.*.payment_status'          => 'payment status',
            'purchases.*.exchange_rate'           => 'exchange rate',
            'usd_to_afn_rate'                     => 'USD to AFN rate (AFN per 1 USD)',
            'usd_to_aed_rate'                     => 'USD to AED rate (AED per 1 USD)',
        ];
    }
}