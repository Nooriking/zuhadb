<?php

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

test('users with two factor enabled are redirected to the challenge', function () {
    $secret = app(Google2FA::class)->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => ['abcd-efgh-ijkl'],
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    expect(session('login.id'))->toBe($user->id);
});

test('users can pass the two factor challenge with a valid code', function () {
    $google2fa = app(Google2FA::class);
    $secret = $google2fa->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => ['abcd-efgh-ijkl'],
        'two_factor_confirmed_at' => now(),
    ]);

    $this->withSession([
        'login.id' => $user->id,
        'login.remember' => false,
    ])->post('/two-factor-challenge', [
        'code' => $google2fa->getCurrentOtp($secret),
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('users can pass the two factor challenge with a recovery code', function () {
    $secret = app(Google2FA::class)->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => ['abcd-efgh-ijkl', 'mnop-qrst-uvwx'],
        'two_factor_confirmed_at' => now(),
    ]);

    $this->withSession([
        'login.id' => $user->id,
        'login.remember' => false,
    ])->post('/two-factor-challenge', [
        'recovery_code' => 'abcd-efgh-ijkl',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    $user->refresh();
    expect($user->two_factor_recovery_codes)->toBe(['mnop-qrst-uvwx']);
});

test('users can enable and confirm two factor authentication', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('two-factor.enable'))
        ->assertRedirect();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();
    expect($user->hasEnabledTwoFactor())->toBeFalse();

    $code = app(Google2FA::class)->getCurrentOtp((string) $user->two_factor_secret);

    $this->actingAs($user)
        ->post(route('two-factor.confirm'), ['code' => $code])
        ->assertRedirect();

    $user->refresh();
    expect($user->hasEnabledTwoFactor())->toBeTrue();
    expect($user->two_factor_recovery_codes)->toHaveCount(8);
});
