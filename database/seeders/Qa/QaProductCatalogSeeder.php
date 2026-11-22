<?php

namespace Database\Seeders\Qa;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use Database\Seeders\Qa\Concerns\GuardsQaDatabase;
use Database\Seeders\Qa\Concerns\QaContext;
use Illuminate\Database\Seeder;

/**
 * Catalog products + variants only. Ownership is assigned later at purchase/batch level.
 */
class QaProductCatalogSeeder extends Seeder
{
    use GuardsQaDatabase;

    public function __construct(private readonly QaContext $ctx) {}

    public function run(): void
    {
        $this->assertQaDatabase();

        $catalog = $this->catalog();

        foreach ($catalog as $row) {
            $brand = Brand::query()->firstOrCreate(
                ['name' => $row['brand']],
                [
                    'country' => $row['country'] ?? 'International',
                    'description' => 'QA reference brand',
                    'status' => 'active',
                    'trash' => false,
                ]
            );

            $product = Product::query()->firstOrCreate(
                ['name' => $row['name'], 'variant_id' => null],
                [
                    'brand_id' => $brand->id,
                    'gender' => $row['gender'] ?? 'unisex',
                    'status' => 'active',
                    'description' => $row['description'] ?? 'QA catalog product',
                    'trash' => false,
                    'user_id' => $this->ctx->admin->id,
                ]
            );

            $sku = $row['sku'];
            $variant = ProductVariant::query()->firstOrCreate(
                ['sku' => $sku],
                [
                    'product_id' => $product->id,
                    'name' => $row['variant_name'] ?? '100ml',
                    'volume_ml' => $row['volume_ml'] ?? 100,
                    'concentration' => $row['concentration'] ?? 'edp',
                    'purchase_price_afn' => $row['purchase_afn'],
                    'sale_price_afn' => $row['sale_afn'],
                    'wholesale_price_afn' => max(0, $row['sale_afn'] - 500),
                    'wholesale_min_qty' => 6,
                    'profit_per_unit' => 0,
                    'stock_quantity' => 0,
                    'trash' => false,
                    'user_id' => $this->ctx->admin->id,
                ]
            );

            // Keep catalog prices aligned on re-seed.
            $variant->update([
                'purchase_price_afn' => $row['purchase_afn'],
                'sale_price_afn' => $row['sale_afn'],
            ]);

            ProductBarcode::query()->firstOrCreate(
                ['barcode' => $row['barcode']],
                [
                    'variant_id' => $variant->id,
                    'barcode_type' => 'CODE128',
                    'is_primary' => true,
                    'status' => 'active',
                    'current_stock' => 0,
                ]
            );

            $this->ctx->variants->put($row['key'], $variant->fresh(['product.brand']));
        }

        $this->command?->info('Product catalog: '.$this->ctx->variants->count().' variants ready.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        // Known reference prices where provided; otherwise synthetic QA values clearly tagged.
        return [
            [
                'key' => 'swy_absolutely',
                'brand' => 'EMPORIO ARMANI',
                'country' => 'Italy',
                'name' => 'Stronger With YOU Absolutely',
                'sku' => 'QA-SWY-ABS-100',
                'barcode' => '8901110000001',
                'purchase_afn' => 5450,
                'sale_afn' => 6500,
                'gender' => 'male',
                'concentration' => 'edp',
                'description' => 'Reference product — known purchase/sale AFN',
            ],
            [
                'key' => 'most_wanted',
                'brand' => 'AZZARO',
                'country' => 'France',
                'name' => 'The Most Wanted Parfum',
                'sku' => 'QA-AZZ-MW-100',
                'barcode' => '8901110000002',
                'purchase_afn' => 3616,
                'sale_afn' => 6000,
                'gender' => 'male',
                'concentration' => 'parfum',
                'description' => 'Reference product — known purchase/sale AFN',
            ],
            [
                'key' => 'al_fareed',
                'brand' => 'Arabian oud',
                'country' => 'UAE',
                'name' => 'Al Fareed',
                'sku' => 'QA-AO-AF-100',
                'barcode' => '8901110000003',
                'purchase_afn' => 8469,
                'sale_afn' => 10000,
                'gender' => 'unisex',
                'description' => 'Reference product — known purchase/sale AFN',
            ],
            [
                'key' => 'kalemat',
                'brand' => 'Arabian oud',
                'country' => 'UAE',
                'name' => 'Kalemat',
                'sku' => 'QA-AO-KAL-100',
                'barcode' => '8901110000004',
                'purchase_afn' => 4200, // QA-SYNTHETIC
                'sale_afn' => 5800,
                'gender' => 'unisex',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'sultani',
                'brand' => 'Arabian oud',
                'country' => 'UAE',
                'name' => 'Sultani Amberwood',
                'sku' => 'QA-AO-SULT-100',
                'barcode' => '8901110000005',
                'purchase_afn' => 5100,
                'sale_afn' => 7200,
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'bleu_chanel',
                'brand' => 'Chanel',
                'country' => 'France',
                'name' => 'Bleu de Chanel Parfum',
                'sku' => 'QA-CH-BDC-100',
                'barcode' => '8901110000006',
                'purchase_afn' => 7800,
                'sale_afn' => 9800,
                'gender' => 'male',
                'concentration' => 'parfum',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'fahrenheit',
                'brand' => 'DIOR',
                'country' => 'France',
                'name' => 'Fahrenheit',
                'sku' => 'QA-DIOR-FAH-100',
                'barcode' => '8901110000007',
                'purchase_afn' => 6200,
                'sale_afn' => 8200,
                'gender' => 'male',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'terre_hermes',
                'brand' => 'HERMES',
                'country' => 'France',
                'name' => "Terre D'Hermes",
                'sku' => 'QA-HER-TDH-100',
                'barcode' => '8901110000008',
                'purchase_afn' => 7000,
                'sale_afn' => 9000,
                'gender' => 'male',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'gentleman',
                'brand' => 'Givenchy',
                'country' => 'France',
                'name' => 'Gentleman Givenchy',
                'sku' => 'QA-GIV-GEN-100',
                'barcode' => '8901110000009',
                'purchase_afn' => 5500,
                'sale_afn' => 7500,
                'gender' => 'male',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'kenzo_homme',
                'brand' => 'KENZO',
                'country' => 'France',
                'name' => 'KENZO HOMME',
                'sku' => 'QA-KEN-HOM-100',
                'barcode' => '8901110000010',
                'purchase_afn' => 3800,
                'sale_afn' => 5200,
                'gender' => 'male',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'joop_homme',
                'brand' => 'JOOP',
                'country' => 'Germany',
                'name' => 'JOOP HOMME',
                'sku' => 'QA-JOOP-HOM-100',
                'barcode' => '8901110000011',
                'purchase_afn' => 3200,
                'sale_afn' => 4500,
                'gender' => 'male',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'hawas_gold',
                'brand' => 'RASASI',
                'country' => 'UAE',
                'name' => 'HAWAS GOLD DIGGER',
                'sku' => 'QA-RAS-HAW-100',
                'barcode' => '8901110000012',
                'purchase_afn' => 2800,
                'sale_afn' => 4200,
                'gender' => 'male',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'mancera',
                'brand' => 'MANCERA',
                'country' => 'France',
                'name' => 'MANCERA Cedrat Boise',
                'sku' => 'QA-MAN-CED-100',
                'barcode' => '8901110000013',
                'purchase_afn' => 6500,
                'sale_afn' => 8500,
                'gender' => 'unisex',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'eros',
                'brand' => 'VERSACE',
                'country' => 'Italy',
                'name' => 'VERSACE EROS',
                'sku' => 'QA-VER-EROS-100',
                'barcode' => '8901110000014',
                'purchase_afn' => 4800,
                'sale_afn' => 6500,
                'gender' => 'male',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'explorer',
                'brand' => 'MONTBLANC',
                'country' => 'Germany',
                'name' => 'EXPLORER MONTBLANC',
                'sku' => 'QA-MB-EXP-100',
                'barcode' => '8901110000015',
                'purchase_afn' => 4100,
                'sale_afn' => 5800,
                'gender' => 'male',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'forever_wanted',
                'brand' => 'AZZARO',
                'country' => 'France',
                'name' => 'AZZARO FOREVER WANTED',
                'sku' => 'QA-AZZ-FW-100',
                'barcode' => '8901110000016',
                'purchase_afn' => 3900,
                'sale_afn' => 5600,
                'gender' => 'male',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'tk_no4',
                'brand' => 'THOMAS KOSMALA',
                'country' => 'France',
                'name' => 'THOMAS KOSMALA NO.4',
                'sku' => 'QA-TK-N4-100',
                'barcode' => '8901110000017',
                'purchase_afn' => 7200,
                'sale_afn' => 9500,
                'gender' => 'unisex',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'cdn_intense',
                'brand' => 'ARMAF',
                'country' => 'UAE',
                'name' => 'CLUB DE NUIT Intense Man',
                'sku' => 'QA-ARM-CDN-I-100',
                'barcode' => '8901110000018',
                'purchase_afn' => 2500,
                'sale_afn' => 3800,
                'gender' => 'male',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'cdn_untold',
                'brand' => 'ARMAF',
                'country' => 'UAE',
                'name' => 'CLUB DE NUIT Untold',
                'sku' => 'QA-ARM-CDN-U-100',
                'barcode' => '8901110000019',
                'purchase_afn' => 2700,
                'sale_afn' => 4000,
                'gender' => 'unisex',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            [
                'key' => 'billie',
                'brand' => 'Billie Eilish',
                'country' => 'USA',
                'name' => 'Billie Eilish',
                'sku' => 'QA-BE-E1-100',
                'barcode' => '8901110000020',
                'purchase_afn' => 3300,
                'sale_afn' => 4800,
                'gender' => 'unisex',
                'description' => 'QA-SYNTHETIC catalog price',
            ],
            // Extra store-only / low-stock / zero-stock variants
            [
                'key' => 'dg_light_blue',
                'brand' => 'D&G',
                'country' => 'Italy',
                'name' => 'Light Blue Forever',
                'sku' => 'QA-DG-LB-100',
                'barcode' => '8901110000021',
                'purchase_afn' => 3600,
                'sale_afn' => 5100,
                'gender' => 'female',
                'description' => 'QA-SYNTHETIC — store-owned stock scenarios',
            ],
            [
                'key' => 'rasasi_blue',
                'brand' => 'RASASI',
                'country' => 'UAE',
                'name' => 'Rasasi Blue Homme',
                'sku' => 'QA-RAS-BLU-100',
                'barcode' => '8901110000022',
                'purchase_afn' => 2100,
                'sale_afn' => 3200,
                'gender' => 'male',
                'description' => 'QA-SYNTHETIC — critical/zero stock scenarios',
            ],
        ];
    }
}
