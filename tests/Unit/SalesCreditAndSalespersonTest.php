<?php

use App\Http\Requests\StoreSaleRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

test('salesperson can save products', function () {
    $user = new User(['role' => User::ROLE_SALESPERSON]);

    expect($user->canAccessModule('products'))->toBeTrue()
        ->and($user->canAccessModule('sales'))->toBeTrue()
        ->and($user->canAccessModule('customers'))->toBeTrue()
        ->and($user->canAccessModule('inventory'))->toBeTrue()
        ->and($user->hasPermission('products.view'))->toBeTrue()
        ->and($user->hasPermission('products.create'))->toBeTrue()
        ->and($user->hasPermission('sales.create'))->toBeTrue()
        ->and($user->hasPermission('sales.update'))->toBeTrue()
        ->and($user->hasPermission('customers.update'))->toBeTrue()
        ->and($user->hasPermission('inventory.view'))->toBeTrue()
        ->and($user->canAccessModule('investors'))->toBeFalse();
});

test('credit sale requires a customer', function () {
    $request = StoreSaleRequest::create('/sales', 'POST', [
        'payment_method' => 'credit',
    ]);
    $request->setContainer(app());

    $validator = Validator::make($request->all(), []);
    $request->withValidator($validator);
    $validator->passes();

    expect($validator->errors()->has('customer_id'))->toBeTrue();
});

test('cash sale does not require a customer', function () {
    $request = StoreSaleRequest::create('/sales', 'POST', [
        'payment_method' => 'cash',
    ]);
    $request->setContainer(app());

    $validator = Validator::make($request->all(), []);
    $request->withValidator($validator);
    $validator->passes();

    expect($validator->errors()->has('customer_id'))->toBeFalse();
});
