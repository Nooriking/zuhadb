<?php

use App\Ai\Database\SchemaDiscoveryService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['ai.database.enabled' => true]);
    app(SchemaDiscoveryService::class)->forgetCache();
});

test('lists only current database application tables and excludes denied', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $service = app(SchemaDiscoveryService::class);

    $tables = $service->listTables($admin);
    $names = array_map(static fn (array $t) => $t['name'], $tables);

    expect($service->databaseName())->toBe(DB::getDatabaseName())
        ->and($names)->toContain('products')
        ->and($names)->toContain('users')
        ->and($names)->not->toContain('migrations')
        ->and($names)->not->toContain('sessions')
        ->and($names)->not->toContain('password_reset_tokens')
        ->and($names)->not->toContain('ai_conversations');
});

test('describes a valid table and omits sensitive columns', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $result = app(SchemaDiscoveryService::class)->describeTable($admin, 'users');

    expect($result['ok'])->toBeTrue();

    $columnNames = array_map(static fn (array $c) => $c['name'], $result['columns']);

    expect($columnNames)->toContain('name')
        ->and($columnNames)->not->toContain('password')
        ->and($columnNames)->not->toContain('remember_token')
        ->and($columnNames)->not->toContain('two_factor_secret')
        ->and($result['restricted_columns_omitted'])->toBeGreaterThan(0);
});

test('rejects nonexistent and unauthorized tables', function () {
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
    $service = app(SchemaDiscoveryService::class);

    expect($service->describeTable($cashier, 'no_such_table_xyz')['ok'])->toBeFalse()
        ->and($service->describeTable($cashier, 'investments')['ok'])->toBeFalse();
});

test('schema cache can be invalidated', function () {
    $service = app(SchemaDiscoveryService::class);
    $first = $service->snapshot();
    $service->forgetCache();
    $second = $service->snapshot(true);

    expect($first['database'])->toBe($second['database'])
        ->and(count($second['tables']))->toBeGreaterThan(0);
});
