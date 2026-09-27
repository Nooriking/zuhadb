<?php

use App\Models\LoginLockout;
use App\Models\User;
use Illuminate\Support\Facades\Session;

test('login is locked after too many failed attempts', function () {
    config([
        'business.login_max_attempts' => 3,
        'business.login_ip_max_attempts' => 10,
        'business.login_identity_max_attempts' => 8,
        'business.login_decay_seconds' => 900,
    ]);

    User::factory()->create([
        'username' => 'lockme',
        'password' => 'password',
        'status' => 'active',
    ]);

    LoginLockout::query()->delete();

    for ($i = 0; $i < 3; $i++) {
        $this->from('/login')->post('/login', [
            'email' => 'lockme',
            'password' => 'wrong-password',
        ]);
    }

    $this->from('/login')->post('/login', [
        'email' => 'lockme',
        'password' => 'password',
    ])->assertSessionHasErrors(['credentials', 'lockout']);

    expect(LoginLockout::query()->where('lock_key', 'like', 'ip:%')->whereNotNull('locked_until')->exists())->toBeTrue();
});

test('login lockout timer survives session wipe and page refresh', function () {
    config([
        'business.login_max_attempts' => 2,
        'business.login_ip_max_attempts' => 10,
        'business.login_identity_max_attempts' => 8,
        'business.login_decay_seconds' => 600,
    ]);

    User::factory()->create([
        'username' => 'bruteforce',
        'password' => 'Secret@123',
        'status' => 'active',
    ]);

    LoginLockout::query()->delete();

    $this->from('/login')->post('/login', [
        'email' => 'bruteforce',
        'password' => 'bad-1',
    ])->assertSessionHasErrors('credentials');

    $this->from('/login')->post('/login', [
        'email' => 'bruteforce',
        'password' => 'bad-2',
    ])->assertSessionHasErrors(['credentials', 'lockout']);

    // Simulate clearing browser cache/cookies: wipe session, keep DB lockouts.
    Session::flush();
    $this->flushSession();

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/login')
            ->where('lockoutSeconds', fn ($seconds) => is_int($seconds) && $seconds > 0)
        );

    $this->from('/login')->post('/login', [
        'email' => 'bruteforce',
        'password' => 'Secret@123',
    ])->assertSessionHasErrors(['credentials', 'lockout']);
});

test('google sign-in routes are disabled', function () {
    $this->get('/auth/google')->assertNotFound();
    $this->get('/auth/google/callback')->assertNotFound();
});
