<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /** Columns removed from product_variants — still passed by older tests. */
    private const LEGACY_COLUMNS = [
        'purchase_price_afn',
        'sale_price_afn',
        'stock_quantity',
        'minimum_stock',
        'profit_per_unit',
        'purchase_amount',
        'purchase_amount_usd',
        'purchase_amount_aed',
        'sale_amount_usd',
        'sale_amount_aed',
    ];

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => '100ml',
            'sku' => 'SKU-'.$this->faker->unique()->numerify('######'),
            'volume_ml' => 100,
            'concentration' => 'edp',
            'trash' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ProductVariant $variant) {
            foreach (self::LEGACY_COLUMNS as $column) {
                if (array_key_exists($column, $variant->getAttributes())) {
                    unset($variant->$column);
                }
            }
        });
    }

    public function withPrices(float $purchaseAfn = 100, float $saleAfn = 150): static
    {
        return $this->afterCreating(function (ProductVariant $variant) use ($purchaseAfn, $saleAfn) {
            ProductPrice::query()->updateOrCreate(
                [
                    'variant_id' => $variant->id,
                    'price_type' => 'purchase',
                    'currency_code' => 'AFN',
                    'min_qty' => 1,
                ],
                [
                    'amount' => $purchaseAfn,
                    'is_active' => true,
                ]
            );
            ProductPrice::query()->updateOrCreate(
                [
                    'variant_id' => $variant->id,
                    'price_type' => 'sale',
                    'currency_code' => 'AFN',
                    'min_qty' => 1,
                ],
                [
                    'amount' => $saleAfn,
                    'is_active' => true,
                ]
            );
        });
    }
}
