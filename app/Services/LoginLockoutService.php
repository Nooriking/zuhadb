<?php

namespace App\Services;

use App\Models\LoginLockout;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoginLockoutService
{
    public const COOKIE = 'zuha_login_lock';

    public function accountKey(string $login, ?string $ip): string
    {
        return 'account:'.Str::lower(trim($login)).'|'.Str::lower((string) $ip);
    }

    public function identityKey(string $login): string
    {
        return 'identity:'.Str::lower(trim($login));
    }

    public function ipKey(?string $ip): string
    {
        return 'ip:'.Str::lower((string) $ip);
    }

    public function secondsRemaining(string $key): int
    {
        $row = LoginLockout::query()->where('lock_key', $key)->first();

        if (! $row || ! $row->locked_until) {
            return 0;
        }

        $seconds = now()->diffInSeconds($row->locked_until, false);

        return $seconds > 0 ? (int) $seconds : 0;
    }

    public function isLocked(string $key): bool
    {
        return $this->secondsRemaining($key) > 0;
    }

    public function maxRemaining(string ...$keys): int
    {
        $max = 0;
        foreach ($keys as $key) {
            $max = max($max, $this->secondsRemaining($key));
        }

        return $max;
    }

    /**
     * Remaining lock for this browser request: DB IP lock + optional signed cookie.
     * Survives refresh and "clear cache" as long as the IP lock row exists.
     */
    public function visibleLockSeconds(?string $ip, ?string $login = null): int
    {
        $keys = [$this->ipKey($ip)];

        if (filled($login)) {
            $keys[] = $this->accountKey($login, $ip);
            $keys[] = $this->identityKey($login);
        }

        $seconds = $this->maxRemaining(...$keys);

        $cookieUntil = (int) (Cookie::get(self::COOKIE) ?: 0);
        if ($cookieUntil > time()) {
            $seconds = max($seconds, $cookieUntil - time());
        }

        return $seconds;
    }

    public function hit(string $key, int $maxAttempts, int $decaySeconds, ?string $ip = null, ?string $login = null): void
    {
        $maxAttempts = max(1, $maxAttempts);
        $decaySeconds = max(60, $decaySeconds);

        DB::transaction(function () use ($key, $maxAttempts, $decaySeconds, $ip, $login) {
            $row = LoginLockout::query()->lockForUpdate()->firstOrNew(['lock_key' => $key]);

            if ($row->locked_until && $row->locked_until->isFuture()) {
                $row->attempts = (int) $row->attempts + 1;
                $row->ip = $ip ?? $row->ip;
                $row->login = $login ?? $row->login;
                $row->save();

                return;
            }

            if ($row->locked_until && $row->locked_until->isPast()) {
                $row->attempts = 0;
                $row->locked_until = null;
            }

            $row->attempts = (int) $row->attempts + 1;
            $row->ip = $ip ?? $row->ip;
            $row->login = $login ?? $row->login;

            if ($row->attempts >= $maxAttempts) {
                $row->locked_until = now()->addSeconds($decaySeconds);
            }

            $row->save();
        });
    }

    /**
     * Extend/create a lock until at least now + $decaySeconds (used to mirror account lock onto IP).
     */
    public function forceLock(string $key, int $decaySeconds, ?string $ip = null, ?string $login = null): void
    {
        $decaySeconds = max(60, $decaySeconds);
        $until = now()->addSeconds($decaySeconds);

        DB::transaction(function () use ($key, $until, $ip, $login) {
            $row = LoginLockout::query()->lockForUpdate()->firstOrNew(['lock_key' => $key]);
            $row->ip = $ip ?? $row->ip;
            $row->login = $login ?? $row->login;
            $row->attempts = max((int) $row->attempts, 1);

            if (! $row->locked_until || $row->locked_until->lt($until)) {
                $row->locked_until = $until;
            }

            $row->save();
        });
    }

    /**
     * Record a failed login and lock IP + identity whenever any bucket locks,
     * so refresh / clear-cache cannot hide the timer.
     */
    public function recordFailedAttempt(string $login, ?string $ip): int
    {
        $maxAttempts = max(1, (int) config('business.login_max_attempts', 5));
        $ipMaxAttempts = max($maxAttempts, (int) config('business.login_ip_max_attempts', 10));
        $identityMax = max($maxAttempts, (int) config('business.login_identity_max_attempts', 8));
        $decay = max(60, (int) config('business.login_decay_seconds', 900));

        $accountKey = $this->accountKey($login, $ip);
        $identityKey = $this->identityKey($login);
        $ipKey = $this->ipKey($ip);

        $this->hit($accountKey, $maxAttempts, $decay, $ip, $login);
        $this->hit($identityKey, $identityMax, $decay, $ip, $login);
        $this->hit($ipKey, $ipMaxAttempts, $decay, $ip, $login);

        $seconds = $this->maxRemaining($accountKey, $identityKey, $ipKey);

        // If any bucket locked, mirror onto IP so UI/timer survives cookie/session wipe.
        if ($seconds > 0) {
            $this->forceLock($ipKey, $seconds, $ip, $login);
            $this->queueLockCookie($seconds);
        }

        return $this->maxRemaining($accountKey, $identityKey, $ipKey);
    }

    public function queueLockCookie(int $seconds): void
    {
        $seconds = max(60, $seconds);
        $until = time() + $seconds;

        Cookie::queue(cookie(
            name: self::COOKIE,
            value: (string) $until,
            minutes: (int) ceil($seconds / 60),
            path: '/',
            domain: null,
            secure: (bool) config('session.secure', false),
            httpOnly: true,
            raw: false,
            sameSite: 'strict',
        ));
    }

    public function forgetLockCookie(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    public function clear(string ...$keys): void
    {
        LoginLockout::query()->whereIn('lock_key', array_values(array_filter($keys)))->delete();
    }

    public function clearForLogin(string $login, ?string $ip): void
    {
        $this->clear(
            $this->accountKey($login, $ip),
            $this->identityKey($login),
            $this->ipKey($ip),
        );
        $this->forgetLockCookie();
    }
}
