<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse|SymfonyRedirect
    {
        if (! $this->isConfigured()) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google sign-in is not available yet.']);
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google sign-in is not available yet.']);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google sign-in failed. Please try again.']);
        }

        $email = Str::lower((string) $googleUser->getEmail());

        if ($email === '') {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google account has no email address.']);
        }

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'No account matches this Google email. Ask an admin to create your user first.']);
        }

        if ($user->trash || $user->status !== 'active') {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'This account cannot sign in.']);
        }

        $user->forceFill([
            'google_id' => $googleUser->getId(),
            'email' => $user->email ?: $email,
            'name' => $user->name ?: ($googleUser->getName() ?: $user->username),
            'password' => $user->password ?: Hash::make(Str::random(32)),
        ])->save();

        if ($user->hasEnabledTwoFactor()) {
            session([
                'login.id' => $user->id,
                'login.remember' => true,
            ]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, true);
        request()->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    protected function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }
}
