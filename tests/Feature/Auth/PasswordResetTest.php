<?php

use App\Mail\PasswordResetCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('reset password request screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password code can be requested and mailed', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'reset.user@example.com',
        'status' => 'active',
        'trash' => false,
    ]);

    $response = $this->post('/forgot-password', ['email' => $user->email]);

    $response->assertRedirect(route('password.code', ['email' => $user->email]));
    Mail::assertSent(PasswordResetCode::class, function (PasswordResetCode $mail) use ($user) {
        return $mail->hasTo($user->email) && strlen($mail->code) === 6;
    });
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();
});

test('reset code screen can be rendered', function () {
    $response = $this->get('/forgot-password/code?email=reset.user@example.com');

    $response->assertStatus(200);
});

test('password can be reset with a valid 6-digit code', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'code.user@example.com',
        'status' => 'active',
        'trash' => false,
    ]);

    $code = '123456';

    DB::table('password_reset_tokens')->updateOrInsert(
        ['email' => $user->email],
        ['token' => Hash::make($code), 'created_at' => now()]
    );

    $response = $this->post('/forgot-password/code', [
        'email' => $user->email,
        'code' => $code,
        'password' => 'NewPass@123',
        'password_confirmation' => 'NewPass@123',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    $user->refresh();
    expect(Hash::check('NewPass@123', $user->password))->toBeTrue();
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});
