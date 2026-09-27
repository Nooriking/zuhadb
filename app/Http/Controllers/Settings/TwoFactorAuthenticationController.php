<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorAuthenticationController extends Controller
{
    public function edit(Request $request, TwoFactor $twoFactor): Response
    {
        $user = $request->user();
        $enabled = $user->hasEnabledTwoFactor();
        $pending = filled($user->two_factor_secret) && ! $enabled;

        return Inertia::render('settings/two-factor', [
            'enabled' => $enabled,
            'pending' => $pending,
            'qrSvg' => $pending ? $twoFactor->qrCodeSvg($user, (string) $user->two_factor_secret) : null,
            'setupSecret' => $pending ? (string) $user->two_factor_secret : null,
            'recoveryCodes' => $request->session()->get('two_factor.recovery_codes', []),
        ]);
    }

    public function enable(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasEnabledTwoFactor()) {
            return back()->with('success', 'Two-factor authentication is already enabled.');
        }

        $secret = $twoFactor->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('success', 'Scan the QR code with your authenticator app, then confirm with a code.');
    }

    public function confirm(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! filled($user->two_factor_secret)) {
            return back()->withErrors(['code' => 'Enable two-factor authentication first.']);
        }

        if (! $twoFactor->verify((string) $user->two_factor_secret, (string) $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => 'The authentication code is invalid.',
            ]);
        }

        $recoveryCodes = $twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        return back()->with([
            'success' => 'Two-factor authentication is now enabled. Store these recovery codes safely.',
            'two_factor.recovery_codes' => $recoveryCodes,
        ]);
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasEnabledTwoFactor()) {
            return back()->withErrors(['code' => 'Two-factor authentication is not enabled.']);
        }

        $recoveryCodes = $twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        return back()->with([
            'success' => 'New recovery codes were generated.',
            'two_factor.recovery_codes' => $recoveryCodes,
        ]);
    }

    public function disable(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
            'code' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        if ($user->hasEnabledTwoFactor()) {
            $code = (string) $request->input('code', '');

            if (! $twoFactor->verify((string) $user->two_factor_secret, $code)) {
                throw ValidationException::withMessages([
                    'code' => 'The authentication code is invalid.',
                ]);
            }
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('success', 'Two-factor authentication has been disabled.');
    }
}
