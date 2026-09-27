<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Validate credentials and return the matching active user (without logging in).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $login = trim((string) $this->input('email'));
        $password = (string) $this->input('password');

        $user = $this->findUserForLogin($login);

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->hitRateLimits();

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

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if ($user->trash || $user->status !== 'active') {
            $this->hitRateLimits();

            throw ValidationException::withMessages([
                'email' => 'This account cannot sign in.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->ipThrottleKey());

        return $user;
    }

    protected function findUserForLogin(string $login): ?User
    {
        $loginLower = Str::lower($login);

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return User::query()
                ->whereRaw('LOWER(email) = ?', [$loginLower])
                ->first();
        }

        return User::query()
            ->whereRaw('LOWER(username) = ?', [$loginLower])
            ->first();
    }

    public function ensureIsNotRateLimited(): void
    {
        $maxAttempts = max(1, (int) config('business.login_max_attempts', 5));
        $ipMaxAttempts = max($maxAttempts, (int) config('business.login_ip_max_attempts', 20));

        $accountLocked = RateLimiter::tooManyAttempts($this->throttleKey(), $maxAttempts);
        $ipLocked = RateLimiter::tooManyAttempts($this->ipThrottleKey(), $ipMaxAttempts);

        if (! $accountLocked && ! $ipLocked) {
            return;
        }

        event(new Lockout($this));

        ActivityLogger::log(
            action: 'login_lockout',
            section: 'auth',
            description: 'Login locked after too many failed attempts',
            properties: [
                'login' => trim((string) $this->input('email')),
                'ip' => $this->ip(),
                'account_locked' => $accountLocked,
                'ip_locked' => $ipLocked,
            ],
        );

        $seconds = max(
            RateLimiter::availableIn($this->throttleKey()),
            RateLimiter::availableIn($this->ipThrottleKey()),
        );

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => max(1, (int) ceil($seconds / 60)),
            ]),
        ]);
    }

    protected function hitRateLimits(): void
    {
        $decay = max(60, (int) config('business.login_decay_seconds', 900));

        RateLimiter::hit($this->throttleKey(), $decay);
        RateLimiter::hit($this->ipThrottleKey(), $decay);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    public function ipThrottleKey(): string
    {
        return 'login-ip:'.Str::transliterate((string) $this->ip());
    }
}
