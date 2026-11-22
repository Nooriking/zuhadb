<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use Concerns\ValidatesProductMoneyAndUnits;

    /**
     * Only authenticated users may update a product.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalize incoming values before validation.
     * Identical to StoreProductRequest so behavior stays consistent.
     */
    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        /* ─────────────── Product fields ─────────────── */

        if (isset($payload['gender'])) {
            $payload['gender'] = strtolower(trim((string) $payload['gender']));
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

                foreach (['description', 'concentration'] as $f) {
                    if (isset($variant[$f]) && trim((string) $variant[$f]) === '') {
                        $payload['variants'][$i][$f] = null;
                    }
                }

                foreach ([
                    'volume_ml',
                    'purchase_price_afn', 'purchase_price_usd', 'purchase_amount',
                    'sale_price_afn', 'sale_amount',
                    'wholesale_price_afn', 'wholesale_amount', 'wholesale_min_qty',
                    'profit_per_unit', 'stock_quantity',
                    'catalog_currency_id',
                    'purchase_currency_id', 'sale_currency_id', 'wholesale_currency_id',
                ] as $f) {
                    if (isset($variant[$f]) && $variant[$f] === '') {
                        $payload['variants'][$i][$f] = null;
                    }
                }

                $postedCurrencies = array_values(array_unique(array_filter([
                    $payload['variants'][$i]['purchase_currency_id'] ?? null,
                    $payload['variants'][$i]['sale_currency_id'] ?? null,
                    $payload['variants'][$i]['wholesale_currency_id'] ?? null,
                ], fn ($id) => $id !== null && $id !== '')));

                $catalogCurrencyId = $payload['variants'][$i]['catalog_currency_id'] ?? null;

                if (! empty($catalogCurrencyId) && count($postedCurrencies) <= 1) {
                    $payload['variants'][$i]['catalog_currency_id'] = (int) $catalogCurrencyId;
                    $payload['variants'][$i]['purchase_currency_id'] = (int) $catalogCurrencyId;
                    $payload['variants'][$i]['sale_currency_id'] = (int) $catalogCurrencyId;
                    $payload['variants'][$i]['wholesale_currency_id'] = (int) $catalogCurrencyId;
                } elseif (empty($catalogCurrencyId) && count($postedCurrencies) === 1) {
                    $payload['variants'][$i]['catalog_currency_id'] = (int) $postedCurrencies[0];
                    $payload['variants'][$i]['purchase_currency_id'] = (int) $postedCurrencies[0];
                    $payload['variants'][$i]['sale_currency_id'] = (int) $postedCurrencies[0];
                    $payload['variants'][$i]['wholesale_currency_id'] = (int) $postedCurrencies[0];
                }

                if (
                    (! isset($payload['variants'][$i]['sale_price_afn']) || $payload['variants'][$i]['sale_price_afn'] === null)
                    && isset($payload['variants'][$i]['sale_amount'])
                    && $payload['variants'][$i]['sale_amount'] !== null
                ) {
                    $payload['variants'][$i]['sale_price_afn'] = $payload['variants'][$i]['sale_amount'];
                }

                if (! empty($variant['concentration'])) {
                    $payload['variants'][$i]['concentration'] =
                        $this->normalizeConcentration($variant['concentration']);
                }

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

    /**
     * Validation rules.
     */
   public function rules(): array
{
    $productId = $this->route('product')?->id;

    return [
        /* ─────────────── Product ─────────────── */
        'name'        => ['required', 'string', 'max:255'],
        'brand_id'    => ['nullable', 'integer', 'exists:brands,id'],
        'gender'      => ['required', Rule::in(['male', 'female', 'unisex'])],
        'status'      => ['required', Rule::in(['active', 'inactive'])],
        'description' => ['nullable', 'string', 'max:5000'],
        'image'       => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ...$this->productExchangeRateRules(),

        /* ─────────────── Variants ─────────────── */
        'variants'                       => ['required', 'array', 'min:1'],
        'variants.*.id'                  => [
            'nullable', 'integer',
            Rule::exists('product_variants', 'id')->where('product_id', $productId),
        ],
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
        'variants.*.stock_quantity'      => ['nullable', 'integer', 'min:0'],
        'variants.*.prices'                         => ['nullable', 'array'],
        'variants.*.prices.*.id'                     => ['nullable', 'integer'],
        'variants.*.prices.*.currency_id'            => ['required_with:variants.*.prices', 'integer', 'exists:currencies,id'],
        'variants.*.prices.*.price_type'             => ['required_with:variants.*.prices', Rule::in(['purchase', 'sale', 'wholesale'])],
        'variants.*.prices.*.amount'                 => ['required_with:variants.*.prices', 'numeric', 'min:0'],
        'variants.*.prices.*.min_qty'                => ['nullable', 'integer', 'min:0'],
        'variants.*.prices.*.is_active'              => ['nullable', 'boolean'],
        'variants.*.prices.*._delete'                => ['nullable', 'boolean'],

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

        // ✅ اصلاح شد — خریدها در جدول products هستند
        'purchases.*.id'                  => [
            'nullable',
            'integer',
            Rule::exists('products', 'id')->whereNotNull('purchase_date'),
        ],

        'purchases.*.variant_index'       => ['required', 'integer', 'min:0'],
        'purchases.*.investor_id'         => [
            'nullable',
            'integer',
            Rule::exists('users', 'id')->where(fn ($q) => $q
                ->where('role', \App\Models\User::ROLE_INVESTOR)
                ->where('trash', false)
                ->whereNull('deleted_at')),
        ],
        'purchases.*.supplier_id'         => ['nullable', 'integer', 'exists:suppliers,id'],
        'purchases.*.quantity'            => ['required', 'integer', 'min:1'],
        'purchases.*.profit_per_unit'     => ['nullable', 'numeric', 'min:0'],
        'purchases.*.line_total'          => ['nullable', 'numeric', 'min:0'],
        'purchases.*.line_profit'         => ['nullable', 'numeric', 'min:0'],
        'purchases.*.invoice_number'      => ['nullable', 'string', 'max:100'],
        'purchases.*.purchase_date'       => ['nullable', 'date'],
        'purchases.*.exchange_rate'       => ['nullable', 'numeric', 'min:0'],
        'purchases.*.paid_now'            => ['nullable', 'numeric', 'min:0'],
        'purchases.*.payment_status'      => ['nullable', Rule::in(['paid', 'unpaid', 'partial', 'credit'])],
        'purchases.*.notes'               => ['nullable', 'string', 'max:5000'],
        'purchases.*.bill_image'          => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
    ];
}

    /**
     * Validation error messages.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Compare after currency conversion in validateProductMoneyAndUnits —
            // never compare raw entered amounts across different currencies.
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
            'variants.*.id.exists'           => 'The specified variant does not belong to this product.',
            'variants.*.concentration.in'    => 'The selected concentration is invalid.',
            'variants.*.sale_price_afn.required' => 'Sale price is required.',
            'variants.*.sale_price_afn.numeric'  => 'Sale price must be numeric.',
            'variants.*.sale_price_afn.min'      => 'Sale price cannot be negative.',

            'variants.*.barcodes.*.barcode.required' => 'Barcode is required.',
            'variants.*.barcodes.*.barcode.distinct' => 'Duplicate barcode in the same variant.',
            'variants.*.barcodes.*.barcode_type.in'  => 'The selected barcode type is invalid.',

            'purchases.*.id.exists'              => 'The specified purchase does not belong to this product.',
            'purchases.*.variant_index.required' => 'Purchase must reference a variant.',
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

    /**
     * Human-readable attribute names.
     */
    public function attributes(): array
    {
        return [
            'name'                                => 'product name',
            'gender'                              => 'gender',
            'status'                              => 'status',
            'brand_id'                            => 'brand',
            'description'                         => 'description',

            'variants.*.id'                       => 'variant id',
            'variants.*.description'              => 'variant description',
            'variants.*.volume_ml'                => 'volume',
            'variants.*.concentration'            => 'concentration',
            'variants.*.sale_price_afn'           => 'sale price',
            'variants.*.barcodes.*.barcode'       => 'barcode',
            'variants.*.barcodes.*.barcode_type'  => 'barcode type',

            'purchases.*.id'                      => 'purchase id',
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