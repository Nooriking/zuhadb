<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['product_variants', 'products'] as $t) {
    $fks = DB::select(
        'SELECT CONSTRAINT_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
        [$t]
    );
    echo $t.PHP_EOL;
    foreach ($fks as $fk) {
        echo "  {$fk->CONSTRAINT_NAME} => {$fk->COLUMN_NAME}".PHP_EOL;
    }
}
