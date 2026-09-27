<?php

use App\Services\LicenseService;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'license.enabled' => true,
        'license.allow_issue' => true,
        'license.secret' => 'test-license-secret-for-unit-tests-only',
        'license.file' => storage_path('framework/testing/license-test.json'),
        'license.product' => 'zuha-perfume-pos',
    ]);

    $path = config('license.file');
    if (is_file($path)) {
        @unlink($path);
    }
});

afterEach(function () {
    $path = config('license.file');
    if (is_file($path)) {
        @unlink($path);
    }
});

test('license activates only for matching machine id', function () {
    $licenses = app(LicenseService::class);
    $machineId = $licenses->machineId();

    $issued = $licenses->issue($machineId, 'Test Shop');
    $payload = $licenses->activate($issued['key']);

    expect($payload['machine_id'])->toBe($machineId);

    $status = $licenses->status();
    expect($status['machine_id'])->toBe($machineId)
        ->and($status['customer'])->toBe('Test Shop')
        ->and($licenses->isValid())->toBeTrue();
});

test('license for another machine is rejected', function () {
    $licenses = app(LicenseService::class);
    $issued = $licenses->issue(str_repeat('A', 32), 'Other PC');

    expect(fn () => $licenses->activate($issued['key']))
        ->toThrow(RuntimeException::class);
});
