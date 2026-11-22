<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\LoginLockoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo 'db='.DB::connection()->getDatabaseName().PHP_EOL;

$user = User::query()->where('username', 'superadmin')->first();
if (! $user) {
    echo "NO SUPERADMIN\n";
    exit(1);
}

echo 'user_id='.$user->id.PHP_EOL;
echo 'email='.$user->email.PHP_EOL;
echo 'status='.$user->status.PHP_EOL;
echo 'trash='.var_export($user->trash, true).PHP_EOL;
echo '2fa_enabled='.var_export($user->hasEnabledTwoFactor(), true).PHP_EOL;
echo 'password_ok='.var_export(Hash::check('Super@123', $user->password), true).PHP_EOL;

$lockouts = app(LoginLockoutService::class);
echo 'visible_lock_ip='.$lockouts->visibleLockSeconds('127.0.0.1').PHP_EOL;
echo 'visible_lock_email='.$lockouts->visibleLockSeconds('127.0.0.1', 'zuhaperfume.af@gmail.com').PHP_EOL;
echo 'visible_lock_username='.$lockouts->visibleLockSeconds('127.0.0.1', 'superadmin').PHP_EOL;

// Clear all login lockouts for this user/ip so login can succeed.
$lockouts->clearForLogin('zuhaperfume.af@gmail.com', '127.0.0.1');
$lockouts->clearForLogin('superadmin', '127.0.0.1');
DB::table('login_lockouts')->where('ip', '127.0.0.1')->delete();
DB::table('login_lockouts')->where('login', 'like', '%zuhaperfume%')->delete();
DB::table('login_lockouts')->where('login', 'like', '%superadmin%')->delete();

echo 'lockouts_remaining='.DB::table('login_lockouts')->count().PHP_EOL;

// Force-reset password to Super@123 (hashed via cast)
$user->password = 'Super@123';
$user->status = 'active';
$user->trash = false;
if (method_exists($user, 'forceFill')) {
    $user->forceFill([
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ]);
}
$user->save();

$user = $user->fresh();
echo 'after_reset_password_ok='.var_export(Hash::check('Super@123', $user->password), true).PHP_EOL;
echo 'after_reset_2fa='.var_export($user->hasEnabledTwoFactor(), true).PHP_EOL;
echo 'DONE'.PHP_EOL;
