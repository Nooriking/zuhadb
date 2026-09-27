<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\LoginLockoutService;
use App\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly LoginLockoutService $lockouts) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        if ($this->ipLockedSeconds() > 0) {
            $seconds = $this->ipLockedSeconds();
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect()
                ->route('login')
                ->withErrors([
                    'credentials' => 'Sign-in is locked. Try again later.',
                    'lockout' => (string) $seconds,
                ]);
        }

        return Inertia::render('auth/two-factor-challenge');
    }

    public function store(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate([
            'code' => ['nullable', 'string', 'max:12'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ]);

        if ($this->ipLockedSeconds() > 0) {
            $seconds = $this->ipLockedSeconds();
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect()
                ->route('login')
                ->withErrors([
                    'credentials' => 'Sign-in is locked. Try again later.',
                    'lockout' => (string) $seconds,
                ]);
        }

        $userId = $request->session()->get('login.id');

        if (! $userId) {
            return redirect()->route('login');
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);

        if (! $user || ! $user->hasEnabledTwoFactor() || $user->trash || $user->status !== 'active') {
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect()->route('login')
                ->withErrors(['credentials' => 'Sign-in failed. Check your details and try again.']);
        }

        $code = trim((string) $request->input('code', ''));
        $recovery = trim((string) $request->input('recovery_code', ''));

        if ($code === '' && $recovery === '') {
            throw ValidationException::withMessages([
                'code' => 'Enter your authentication code or a recovery code.',
            ]);
        }

        $loginKey = $user->email ?: $user->username ?: (string) $user->id;
        $passed = false;

        if ($recovery !== '') {
            $codes = $user->two_factor_recovery_codes ?? [];

            if ($twoFactor->recoveryCodeMatches($codes, $recovery)) {
                $user->forceFill([
                    'two_factor_recovery_codes' => $twoFactor->consumeRecoveryCode($codes, $recovery),
                ])->save();
                $passed = true;
            }
        } else {
            $secret = (string) $user->two_factor_secret;
            if ($secret !== '' && $twoFactor->verify($secret, $code)) {
                $passed = true;
            }
        }

        if (! $passed) {
            $seconds = $this->lockouts->recordFailedAttempt($loginKey, $request->ip());

            ActivityLogger::log(
                action: 'login_failed',
                section: 'auth',
                description: 'Failed two-factor challenge',
                properties: [
                    'login' => $loginKey,
                    'ip' => $request->ip(),
                ],
                userId: $user->id,
            );

            if ($seconds > 0) {
                $request->session()->forget(['login.id', 'login.remember']);
                $request->session()->put('login.lockout_until', now()->addSeconds($seconds)->timestamp);

                return redirect()
                    ->route('login')
                    ->withErrors([
                        'credentials' => 'Sign-in is locked. Try again later.',
                        'lockout' => (string) $seconds,
                    ]);
            }

            throw ValidationException::withMessages([
                $recovery !== '' ? 'recovery_code' : 'code' => 'Verification failed. Try again.',
            ]);
        }

        $this->lockouts->clearForLogin($loginKey, $request->ip());
        $request->session()->forget('login.lockout_until');

        Auth::login($user, (bool) $request->session()->pull('login.remember', false));
        $request->session()->forget('login.id');
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    protected function ipLockedSeconds(): int
    {
        return $this->lockouts->visibleLockSeconds(request()->ip());
    }
}
