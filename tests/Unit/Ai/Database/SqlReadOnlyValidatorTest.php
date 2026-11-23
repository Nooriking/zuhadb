<?php

use App\Ai\Database\SqlReadOnlyValidator;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'ai.database.max_sql_length' => 5000,
        'ai.database.max_joins' => 8,
        'ai.database.deny_system_schemas' => ['information_schema', 'mysql', 'performance_schema', 'sys'],
    ]);
});

test('allows safe select aggregates joins distinct and limit', function () {
    $result = app(SqlReadOnlyValidator::class)->validate(
        'SELECT b.name, COUNT(s.id) AS sales_count FROM sales s INNER JOIN branches b ON b.id = s.branch_id WHERE s.branch_id = 1 GROUP BY b.name ORDER BY sales_count DESC LIMIT 50'
    );

    expect($result['ok'])->toBeTrue()
        ->and($result['tables'])->toContain('sales')
        ->and($result['tables'])->toContain('branches')
        ->and($result['join_count'])->toBe(1)
        ->and($result['has_star'])->toBeFalse();
});

test('allows read-only cte with descriptive name', function () {
    $result = app(SqlReadOnlyValidator::class)->validate(
        'WITH cte_users AS (SELECT id FROM users) SELECT COUNT(*) AS c FROM cte_users'
    );

    expect($result['ok'])->toBeTrue()
        ->and($result['tables'])->toContain('users')
        ->and($result['cte_names'])->toContain('cte_users');
});

test('detects select star', function () {
    $result = app(SqlReadOnlyValidator::class)->validate('SELECT * FROM products LIMIT 10');

    expect($result['ok'])->toBeTrue()
        ->and($result['has_star'])->toBeTrue()
        ->and($result['tables'])->toBe(['products']);
});

test('rejects write and ddl statements', function (string $sql) {
    $result = app(SqlReadOnlyValidator::class)->validate($sql);

    expect($result['ok'])->toBeFalse();
})->with([
    'UPDATE users SET name = "x"',
    'DELETE FROM products',
    'INSERT INTO brands (name) VALUES ("x")',
    'DROP TABLE products',
    'TRUNCATE TABLE sales',
    'ALTER TABLE users ADD COLUMN x INT',
]);

test('rejects multi-statement sql', function () {
    $result = app(SqlReadOnlyValidator::class)->validate('SELECT id FROM users; DROP TABLE users');

    expect($result['ok'])->toBeFalse();
});

test('rejects sql comments', function () {
    expect(app(SqlReadOnlyValidator::class)->validate('SELECT id FROM users -- bye')['ok'])->toBeFalse()
        ->and(app(SqlReadOnlyValidator::class)->validate('SELECT id FROM users /* hack */')['ok'])->toBeFalse();
});

test('rejects dangerous functions', function () {
    expect(app(SqlReadOnlyValidator::class)->validate('SELECT SLEEP(1) FROM users')['ok'])->toBeFalse()
        ->and(app(SqlReadOnlyValidator::class)->validate('SELECT LOAD_FILE("/etc/passwd")')['ok'])->toBeFalse();
});

test('rejects system schema references', function () {
    $result = app(SqlReadOnlyValidator::class)->validate(
        'SELECT table_name FROM information_schema.tables LIMIT 5'
    );

    expect($result['ok'])->toBeFalse();
});
