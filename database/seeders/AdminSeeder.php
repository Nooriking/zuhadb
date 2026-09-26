<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * ساخت کاربر Admin پیش‌فرض سیستم.
     *
     * این کاربر دسترسی کامل به همه چیز دارد.
     */
    public function run(): void
    {
        $email    = 'admin@zuha-perfume.com';
        $password = 'Admin@123';

        // بررسی: آیا از قبل وجود دارد؟
        $existing = User::where('email', $email)->first();

        if ($existing) {
            // اگر وجود دارد، مطمئن شو نقشش admin است
            if ($existing->role !== User::ROLE_ADMIN) {
                $existing->update([
                    'role'   => User::ROLE_ADMIN,
                    'status' => 'active',
                    'trash'  => false,
                ]);
                $this->command->warn('⚠️  Existing user updated to Admin.');
            } else {
                $this->command->info('ℹ️  Admin already exists.');
            }

            $this->command->info("   Email: {$email}");

            return;
        }

        // ساخت Admin جدید
        $admin = User::create([
            'branch_id'     => null,
            'name'          => 'Admin',
            'username'      => 'admin',
            'email'         => $email,
            'phone'         => null,
            'password'      => $password,
            'role'          => User::ROLE_ADMIN,
            'employee_code' => 'AD-0001',
            'avatar'        => null,
            'status'        => 'active',
            'trash'         => false,
            'user_id'       => null,
        ]);

        // ─────────────────────────────────────────────
        // نمایش اطلاعات ورود
        // ─────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('✅ Admin created successfully!');
        $this->command->info('   ─────────────────────────────────────');
        $this->command->info("   📧 Email:    {$email}");
        $this->command->info("   🔑 Password: {$password}");
        $this->command->info("   👤 Username: admin");
        $this->command->info("   🎖️  Role:     admin");
        $this->command->info('   ─────────────────────────────────────');
        $this->command->warn('   ⚠️  Change the password after first login!');
        $this->command->info('');
    }
}