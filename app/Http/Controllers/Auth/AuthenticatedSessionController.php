<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LoginLockoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        // Returning to login abandons any pending 2FA challenge.
        $request->session()->forget(['login.id', 'login.remember']);

        $lockouts = app(LoginLockoutService::class);
        $lockoutSeconds = $lockouts->visibleLockSeconds($request->ip());

        $sessionUntil = (int) $request->session()->get('login.lockout_until', 0);
        if ($sessionUntil > time()) {
            $lockoutSeconds = max($lockoutSeconds, $sessionUntil - time());
        }

        if ($lockoutSeconds > 0) {
            $lockouts->queueLockCookie($lockoutSeconds);
        }

        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'lockoutSeconds' => $lockoutSeconds > 0 ? $lockoutSeconds : null,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        if (! $user->hasEnabledTwoFactor()) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            $lockouts = app(LoginLockoutService::class);
            $lockouts->clearForLogin(trim((string) $request->input('email')), $request->ip());
            $request->session()->forget('login.lockout_until');

            $user->forceFill(['last_login_at' => now()])->save();

            return redirect()->intended(route('dashboard', absolute: false));
        }

        $request->session()->put([
            'login.id' => $user->id,
            'login.remember' => $request->boolean('remember'),
        ]);

        return redirect()->route('two-factor.login');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // ۱. خروج از گارد وب
        Auth::guard('web')->logout();

        // ۲. ابتدا Session را regenerate کن (شناسه جدید)
        $request->session()->regenerate();

        // ۳. سپس Session را کاملاً بی‌اعتبار کن (پاک کردن همه داده‌ها)
        $request->session()->invalidate();

        // ۴. توکن CSRF جدید بساز
        $request->session()->regenerateToken();

        // ۵. پاک کردن کوکی‌های Session و XSRF
        cookie()->queue(cookie()->forget(config('session.cookie')));
        cookie()->queue(cookie()->forget('XSRF-TOKEN'));
        cookie()->queue(cookie()->forget('laravel_session'));

        // ۶. ریدایرکت مستقیم به صفحه لاگین
        return redirect()->route('login');
    }
}