<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'branch_id' => null,
                'name' => 'Super Admin',
                'email' => 'zuhaperfume.af@gmail.com',
                'phone' => '0744442863',
                'password' => 'Super@123',
                'role' => User::ROLE_SUPER_ADMIN,
                'employee_code' => 'EMP-0001',
                'status' => 'active',
                'trash' => false,
                'user_id' => null,
            ]
        );

        // Remove any other seeded demo users if a fresh seed re-ran partially.
        User::query()
            ->where('id', '!=', $user->id)
            ->whereIn('username', [
                'admin', 'manager', 'cashier', 'accountant', 'investor', 'salesperson',
            ])
            ->delete();

        $this->command?->info('Super Admin ready');
        $this->command?->info('  Email / login: zuhaperfume.af@gmail.com  (or username: superadmin)');
        $this->command?->info('  Password: Super@123');
        $this->command?->warn('  Change this password after first login.');
    }
}
