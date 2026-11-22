<?php

namespace App\Console\Commands;

use App\Models\LoginLockout;
use App\Models\User;
use App\Services\LoginLockoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class UnlockLoginCommand extends Command
{
    protected $signature = 'login:unlock
        {--login= : Email or username to clear (default: clears all lockouts)}
        {--ip= : IP address to clear}
        {--reset-superadmin-password : Reset superadmin password to Super@123}';

    protected $description = 'Clear login lockouts (and optionally reset the superadmin password) so sign-in can succeed again.';

    public function handle(LoginLockoutService $lockouts): int
    {
        $login = trim((string) $this->option('login'));
        $ip = trim((string) $this->option('ip'));

        if ($login === '' && $ip === '') {
            $deleted = LoginLockout::query()->delete();
            $this->info("Cleared all login lockouts ({$deleted} row(s)).");
        } else {
            $keys = [];
            if ($login !== '' && $ip !== '') {
                $keys[] = $lockouts->accountKey($login, $ip);
            }
            if ($login !== '') {
                $keys[] = $lockouts->identityKey($login);
            }
            if ($ip !== '') {
                $keys[] = $lockouts->ipKey($ip);
            }

            $lockouts->clear(...$keys);
            $this->info('Cleared lockouts for: '.implode(', ', $keys));
        }

        $lockouts->forgetLockCookie();

        if ($this->option('reset-superadmin-password')) {
            $user = User::query()->where('username', 'superadmin')->first()
                ?? User::query()->where('email', 'zuhaperfume.af@gmail.com')->first();

            if (! $user) {
                $this->error('Superadmin user was not found.');

                return self::FAILURE;
            }

            $payload = [
                'password' => 'Super@123',
                'status' => 'active',
                'trash' => false,
            ];

            foreach ([
                'email_verified_at' => now(),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ] as $column => $value) {
                if (Schema::hasColumn('users', $column)) {
                    $payload[$column] = $value;
                }
            }

            $user->forceFill($payload)->save();

            $this->info('Superadmin password reset to Super@123 (status active).');
            $this->line('Login with email: zuhaperfume.af@gmail.com  OR username: superadmin');
        }

        return self::SUCCESS;
    }
}
