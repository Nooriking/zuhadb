<?php

use App\Ai\Database\ReadOnlyQueryEngine;
use App\Ai\Database\SchemaDiscoveryService;
use App\Models\Branch;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'ai.database.enabled' => true,
        'ai.database.max_rows' => 100,
        'ai.database.max_execution_seconds' => 10,
        'ai.database.max_result_chars' => 12000,
        'ai.database.max_concurrent_per_user' => 2,
    ]);
    app(SchemaDiscoveryService::class)->forgetCache();
});

test('executes safe count query against real database', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    User::factory()->count(2)->create(['role' => User::ROLE_CASHIER]);

    $result = app(ReadOnlyQueryEngine::class)->execute(
        $admin,
        'How many users?',
        'SELECT COUNT(*) AS user_count FROM users',
    );

    expect($result['ok'])->toBeTrue()
        ->and($result['row_count'])->toBe(1)
        ->and((int) $result['rows'][0]['user_count'])->toBeGreaterThanOrEqual(3);
});

test('expands select star and never returns password', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Schema Admin']);

    $result = app(ReadOnlyQueryEngine::class)->execute(
        $admin,
        'List users',
        'SELECT * FROM users',
        [],
        10,
    );

    expect($result['ok'])->toBeTrue()
        ->and($result['row_count'])->toBeGreaterThan(0);

    foreach ($result['rows'] as $row) {
        expect($row)->not->toHaveKey('password')
            ->and($row)->not->toHaveKey('remember_token')
            ->and($row)->not->toHaveKey('two_factor_secret');
    }
});

test('rejects update statements', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $result = app(ReadOnlyQueryEngine::class)->execute(
        $admin,
        'Hack',
        'UPDATE users SET name = "nope"',
    );

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->toBe('invalid_sql');
});

test('rejects explicit password column selection', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $result = app(ReadOnlyQueryEngine::class)->execute(
        $admin,
        'Steal passwords',
        'SELECT id, password FROM users LIMIT 5',
    );

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->toBe('sensitive_column');
});

test('enforces branch filter for branch scoped users', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'role' => User::ROLE_BRANCH_MANAGER,
        'branch_id' => $branchA->id,
    ]);

    $denied = app(ReadOnlyQueryEngine::class)->execute(
        $manager,
        'Sales',
        'SELECT id FROM sales LIMIT 10',
    );

    expect($denied['ok'])->toBeFalse()
        ->and($denied['error'])->toBe('branch_filter_required');

    $allowed = app(ReadOnlyQueryEngine::class)->execute(
        $manager,
        'Sales for my branch',
        'SELECT id FROM sales WHERE branch_id = '.$branchA->id.' LIMIT 10',
    );

    expect($allowed['ok'])->toBeTrue();

    $foreign = app(ReadOnlyQueryEngine::class)->execute(
        $manager,
        'Other branch',
        'SELECT id FROM sales WHERE branch_id = '.$branchB->id.' LIMIT 10',
    );

    expect($foreign['ok'])->toBeFalse()
        ->and($foreign['error'])->toBe('unauthorized_branch');
});

test('enforces row limit', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    User::factory()->count(5)->create(['role' => User::ROLE_CASHIER]);

    $result = app(ReadOnlyQueryEngine::class)->execute(
        $admin,
        'Users',
        'SELECT id, name FROM users',
        [],
        2,
    );

    expect($result['ok'])->toBeTrue()
        ->and($result['row_count'])->toBeLessThanOrEqual(2)
        ->and($result['limit'])->toBe(2);
});
