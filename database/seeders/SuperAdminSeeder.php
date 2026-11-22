<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::withTrashed()->updateOrCreate(
            ['username' => 'superadmin'],
            [
                'branch_id' => null,
                'name'      => 'Super Admin',
                'email'     => 'zuhaperfume.af@gmail.com',
                'phone'     => '0744442863',
                'password'  => 'Super@123',
                'role'      => User::ROLE_SUPER_ADMIN,
                'status'    => 'active',
                'user_id'   => null,
            ]
        );

        if ($user->trashed()) {
            $user->restore();
        }

        // Fields outside $fillable (trash / verification / 2FA) must use forceFill.
        // Guard optional columns — production DBs may lag behind newer migrations.
        $payload = [
            'status' => 'active',
            'trash' => false,
            'password' => 'Super@123',
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