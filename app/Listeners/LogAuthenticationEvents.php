<?php

namespace App\Listeners;

use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthenticationEvents
{
    public function handleLogin(Login $event): void
    {
        ActivityLogger::log(
            action: 'login',
            section: 'auth',
            description: 'User signed in',
            subject: $event->user,
            properties: ['guard' => $event->guard],
            userId: $event->user->getAuthIdentifier(),
        );
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        ActivityLogger::log(
            action: 'logout',
            section: 'auth',
            description: 'User signed out',
            subject: $event->user,
            properties: ['guard' => $event->guard],
            userId: $event->user->getAuthIdentifier(),
        );
    }

    public function handleFailed(Failed $event): void
    {
        ActivityLogger::log(
            action: 'login_failed',
            section: 'auth',
            description: 'Failed sign-in attempt',
            properties: [
                'guard' => $event->guard,
                'email' => $event->credentials['email'] ?? $event->credentials['username'] ?? null,
            ],
            userId: $event->user?->getAuthIdentifier(),
        );
    }
}
