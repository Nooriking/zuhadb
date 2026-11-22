<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'variants: '.implode(', ', Schema::getColumnListing('product_variants')).PHP_EOL;
echo 'products: '.implode(', ', Schema::getColumnListing('products')).PHP_EOL;
echo 'batches: '.implode(', ', Schema::getColumnListing('product_batches')).PHP_EOL;
