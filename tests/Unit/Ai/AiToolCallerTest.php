<?php

use App\Ai\DTOs\AiToolCall;
use App\Ai\Tools\AiToolCaller;
use App\Ai\Tools\AiToolRegistry;
use App\Ai\Tools\GetProductStockTool;
use App\Ai\Tools\ListLowStockTool;
use App\Ai\Tools\SearchProductsTool;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('tool caller rejects unknown tools without executing anything', function () {
    $caller = app(AiToolCaller::class);
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $result = $caller->execute($user, new AiToolCall(
        id: 'call_1',
        name: 'drop_database',
        arguments: [],
    ));

    expect($result->ok)->toBeFalse()
        ->and($result->payload['error'])->toBe('unknown_tool');
});

test('tool caller rejects unauthorized users for search products', function () {
    $caller = app(AiToolCaller::class);
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

    $result = $caller->execute($cashier, new AiToolCall(
        id: 'call_2',
        name: SearchProductsTool::NAME,
        arguments: ['query' => 'Dior'],
    ));

    expect($result->ok)->toBeFalse()
        ->and($result->payload['error'])->toBe('unauthorized');
});

test('tool caller validates arguments and executes registered search products tool', function () {
    Brand::factory()->create(['name' => 'Lattafa']);
    Product::factory()->create([
        'brand_id' => Brand::query()->where('name', 'Lattafa')->value('id'),
        'name' => 'Khamrah',
        'trash' => false,
    ]);

    $caller = app(AiToolCaller::class);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $invalid = $caller->execute($admin, new AiToolCall(
        id: 'call_3',
        name: SearchProductsTool::NAME,
        arguments: ['limit' => 999],
    ));
    expect($invalid->ok)->toBeFalse()
        ->and($invalid->payload['error'])->toBe('invalid_arguments');

    $ok = $caller->execute($admin, new AiToolCall(
        id: 'call_4',
        name: SearchProductsTool::NAME,
        arguments: ['query' => 'Khamrah', 'limit' => 5],
        rawArguments: '{"query":"Khamrah","limit":5}',
    ));

    expect($ok->ok)->toBeTrue()
        ->and($ok->payload['count'])->toBe(1)
        ->and($ok->toProviderContent())->toContain('Khamrah');
});

test('tool registry exposes only registered provider schemas', function () {
    $registry = app(AiToolRegistry::class);
    $schemas = $registry->toProviderSchemas();
    $names = collect($schemas)->pluck('function.name')->all();

    expect($schemas)->toHaveCount(7)
        ->and($names)->toContain(SearchProductsTool::NAME)
        ->and($names)->toContain(GetProductStockTool::NAME)
        ->and($names)->toContain(ListLowStockTool::NAME)
        ->and($names)->toContain('list_database_tables')
        ->and($names)->toContain('describe_database_table')
        ->and($names)->toContain('get_database_relationships')
        ->and($names)->toContain('query_database')
        ->and($registry->has('search_products'))->toBeTrue()
        ->and($registry->has('get_product_stock'))->toBeTrue()
        ->and($registry->has('list_low_stock'))->toBeTrue()
        ->and($registry->has('query_database'))->toBeTrue()
        ->and($registry->has('delete_product'))->toBeFalse();
});
