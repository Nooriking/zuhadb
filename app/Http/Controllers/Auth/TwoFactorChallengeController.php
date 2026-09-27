<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/two-factor-challenge');
    }

    public function store(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $userId = $request->session()->get('login.id');

        if (! $userId) {
            return redirect()->route('login');
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);

        if (! $user || ! $user->hasEnabledTwoFactor()) {
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect()->route('login');
        }

        $code = (string) $request->input('code', '');
        $recovery = (string) $request->input('recovery_code', '');

        if ($recovery !== '') {
            $codes = $user->two_factor_recovery_codes ?? [];

            if (! $twoFactor->recoveryCodeMatches($codes, $recovery)) {
                throw ValidationException::withMessages([
                    'recovery_code' => 'This recovery code is invalid.',
                ]);
            }

            $user->forceFill([
                'two_factor_recovery_codes' => $twoFactor->consumeRecoveryCode($codes, $recovery),
            ])->save();
        } else {
            if (! $twoFactor->verify((string) $user->two_factor_secret, $code)) {
                throw ValidationException::withMessages([
                    'code' => 'The authentication code is invalid.',
                ]);
            }
        }

        Auth::login($user, (bool) $request->session()->pull('login.remember', false));
        $request->session()->forget('login.id');
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
