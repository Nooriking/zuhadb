<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\LoginLockoutService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Validate credentials and return the matching active user (without logging in).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): User
    {
        $lockouts = app(LoginLockoutService::class);

        $this->ensureIsNotRateLimited($lockouts);

        $login = trim((string) $this->input('email'));
        $password = (string) $this->input('password');

        $user = $this->findUserForLogin($login);

        $passwordOk = $user
            && filled($user->password)
            && Hash::check($password, $user->password);

        if (! $passwordOk) {
            // Constant-ish delay reduces password timing / user-enumeration side channels.
            usleep(random_int(120_000, 280_000));

            $seconds = $lockouts->recordFailedAttempt($login, $this->ip());

            ActivityLogger::log(
                action: 'login_failed',
                section: 'auth',
                description: 'Failed sign-in attempt',
                properties: [
                    'login' => $login,
                    'ip' => $this->ip(),
                ],
                userId: $user?->id,
            );

            if ($seconds > 0) {
                $this->rememberLockout($seconds);
                event(new Lockout($this));
                $this->throwLockout($seconds);
            }

            throw ValidationException::withMessages([
                'credentials' => 'Sign-in failed. Check your details and try again.',
            ]);
        }

        if ($user->trash || $user->status !== 'active') {
            usleep(random_int(120_000, 280_000));
            $seconds = $lockouts->recordFailedAttempt($login, $this->ip());

            if ($seconds > 0) {
                $this->rememberLockout($seconds);
                event(new Lockout($this));
                $this->throwLockout($seconds);
            }

            throw ValidationException::withMessages([
                'credentials' => 'Sign-in failed. Check your details and try again.',
            ]);
        }

        // Do not clear lockouts yet — wait until password + optional 2FA both succeed.
        return $user;
    }

    protected function findUserForLogin(string $login): ?User
    {
        $loginLower = Str::lower($login);

        return User::query()
            ->where(function ($query) use ($loginLower) {
                $query->whereRaw('LOWER(email) = ?', [$loginLower])
                    ->orWhereRaw('LOWER(username) = ?', [$loginLower]);
            })
            ->first();
    }

    public function ensureIsNotRateLimited(?LoginLockoutService $lockouts = null): void
    {
        $lockouts ??= app(LoginLockoutService::class);
        $login = trim((string) $this->input('email'));

        $seconds = $lockouts->maxRemaining(
            $lockouts->accountKey($login, $this->ip()),
            $lockouts->identityKey($login),
            $lockouts->ipKey($this->ip()),
        );

        if ($seconds <= 0) {
            $seconds = $lockouts->visibleLockSeconds($this->ip(), $login);
        }

        if ($seconds <= 0) {
            return;
        }

        event(new Lockout($this));

        ActivityLogger::log(
            action: 'login_lockout',
            section: 'auth',
            description: 'Login locked after too many failed attempts',
            properties: [
                'login' => $login,
                'ip' => $this->ip(),
                'seconds' => $seconds,
            ],
        );

        $this->rememberLockout($seconds);
        $lockouts->queueLockCookie($seconds);
        $this->throwLockout($seconds);
    }

    protected function rememberLockout(int $seconds): void
    {
        $this->session()->put('login.lockout_until', now()->addSeconds($seconds)->timestamp);
        app(LoginLockoutService::class)->queueLockCookie($seconds);
    }

    protected function throwLockout(int $seconds): never
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        throw ValidationException::withMessages([
            'credentials' => "Too many failed sign-in attempts. Try again in {$minutes} minute(s).",
            'lockout' => (string) $seconds,
        ])->status(429);
    }
}
