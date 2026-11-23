<?php

use App\Ai\Database\TableAccessPolicy;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

test('admin can access sales and products tables', function () {
    $policy = app(TableAccessPolicy::class);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    expect($policy->canUseDatabaseTools($admin))->toBeTrue()
        ->and($policy->canAccessTable($admin, 'sales'))->toBeTrue()
        ->and($policy->canAccessTable($admin, 'products'))->toBeTrue()
        ->and($policy->canAccessTable($admin, 'migrations'))->toBeFalse();
});

test('cashier cannot access investments or users tables', function () {
    $policy = app(TableAccessPolicy::class);
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

    expect($policy->canUseDatabaseTools($cashier))->toBeTrue()
        ->and($policy->canAccessTable($cashier, 'sales'))->toBeTrue()
        ->and($policy->canAccessTable($cashier, 'customers'))->toBeTrue()
        ->and($policy->canAccessTable($cashier, 'investments'))->toBeFalse()
        ->and($policy->canAccessTable($cashier, 'users'))->toBeFalse();
});

test('investor is limited to investor scoped tables', function () {
    $policy = app(TableAccessPolicy::class);
    $investor = User::factory()->create(['role' => User::ROLE_INVESTOR]);

    expect($policy->canUseDatabaseTools($investor))->toBeTrue()
        ->and($policy->canAccessTable($investor, 'investments'))->toBeTrue()
        ->and($policy->canAccessTable($investor, 'sales'))->toBeFalse()
        ->and($policy->canAccessTable($investor, 'users'))->toBeFalse();
});
