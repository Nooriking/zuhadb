<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCode;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset link request page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = Str::lower(trim($request->string('email')->toString()));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user) {
            $code = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($code), 'created_at' => now()]
            );

            try {
                Mail::to($user->email)->send(new PasswordResetCode($code));
            } catch (\Throwable $e) {
                report($e);

                return back()->withErrors([
                    'email' => 'The reset code could not be sent. Check the mail settings (Gmail App Password in .env).',
                ]);
            }
        }

        return redirect()
            ->route('password.code', ['email' => $user?->email ?? $email])
            ->with('status', 'If this email has an account, a 6-digit code was sent to it.');
    }

    public function code(Request $request): Response
    {
        return Inertia::render('auth/reset-code', [
            'email' => (string) $request->query('email', ''),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function resetWithCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = Str::lower(trim($data['email']));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $row = $user
            ? DB::table('password_reset_tokens')->where('email', $user->email)->first()
            : null;

        $fresh = $row && $row->created_at && now()->subMinutes(10)->lte($row->created_at);

        if (! $user || ! $row || ! $fresh || ! Hash::check($data['code'], $row->token)) {
            return back()->withErrors(['code' => 'This code is invalid or expired.']);
        }

        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        event(new PasswordReset($user));

        return to_route('login')->with('status', 'Your password has been reset.');
    }
}
