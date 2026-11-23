<?php

use App\Ai\Database\SensitiveColumnPolicy;
use Tests\TestCase;

uses(TestCase::class);

test('marks authentication columns as sensitive', function () {
    $policy = app(SensitiveColumnPolicy::class);

    expect($policy->isSensitive('users', 'password'))->toBeTrue()
        ->and($policy->isSensitive('users', 'remember_token'))->toBeTrue()
        ->and($policy->isSensitive('users', 'two_factor_secret'))->toBeTrue()
        ->and($policy->isSensitive('users', 'name'))->toBeFalse()
        ->and($policy->isSensitive('settings', 'value'))->toBeTrue();
});

test('filters sensitive keys from rows', function () {
    $policy = app(SensitiveColumnPolicy::class);

    $filtered = $policy->filterRow([
        'id' => 1,
        'name' => 'Admin',
        'password' => 'hash',
        'two_factor_secret' => 'secret',
    ], 'users');

    expect($filtered)->toBe(['id' => 1, 'name' => 'Admin'])
        ->and($policy->safeColumns('users', ['id', 'name', 'password']))->toBe(['id', 'name']);
});
