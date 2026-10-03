<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Super Admin Seeder
 *
 * Creates the primary Super Admin account.
 * This is the only seeder that runs by default on fresh install
 * (see DatabaseSeeder.php).
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'branch_id' => null, // Super Admin is not tied to any branch
                'name'      => 'Super Admin',
                'email'     => 'zuhaperfume.af@gmail.com',
                'phone'     => '0744442863',
                'password'  => 'Super@123',
                'role'      => 'super_admin',
                'status'    => 'active',
                'trash'     => false,
            ]
        );

        // ─────────────────────────────────────────
        // Console output (shown during `php artisan db:seed`)
        // ─────────────────────────────────────────
        $this->command->info('  Email / login: ' . $superAdmin->email . '  (or username: ' . $superAdmin->username . ')');
        $this->command->info('  Password: Super@123');
        $this->command->info('  Change this password after first login.');
    }
}