<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can open the reports hub', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->has('categories')
            ->has('stats')
            ->where('stats.available', fn ($count) => $count >= 2)
        );
});

test('cashier cannot open the reports hub', function () {
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

    $this->actingAs($cashier)
        ->get(route('reports.index'))
        ->assertForbidden();
});

test('accountant can open the reports hub', function () {
    $accountant = User::factory()->create(['role' => User::ROLE_ACCOUNTANT]);

    $this->actingAs($accountant)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->has('categories')
        );
});

test('sales report route remains available to cashier', function () {
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

    $this->actingAs($cashier)
        ->get(route('sales.report'))
        ->assertOk();
});

test('investor dashboard remains available to admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->get(route('investors.dashboard'))
        ->assertOk();
});
