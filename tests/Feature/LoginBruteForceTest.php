<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('login is locked after too many failed attempts', function () {
    config([
        'business.login_max_attempts' => 3,
        'business.login_ip_max_attempts' => 20,
        'business.login_decay_seconds' => 900,
    ]);

    User::factory()->create([
        'username' => 'lockme',
        'password' => 'password',
        'status' => 'active',
    ]);

    RateLimiter::clear('lockme|127.0.0.1');
    RateLimiter::clear('login-ip:127.0.0.1');

    for ($i = 0; $i < 3; $i++) {
        $this->from('/login')->post('/login', [
            'email' => 'lockme',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $this->from('/login')->post('/login', [
        'email' => 'lockme',
        'password' => 'password',
    ])->assertSessionHasErrors('email');
});
