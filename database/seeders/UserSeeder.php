<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ─────────────────────────────────────────
        // 1. ایجاد یک شعبه پیش‌فرض (اگر وجود نداشت)
        // ─────────────────────────────────────────
        $mainBranch = Branch::firstOrCreate(
            ['code' => 'BR-001'],
            [
                'name'     => 'Main branch',
                'phone'    => '02100000000',
                'email'    => 'main@example.com',
                'country'  => 'Iran',
                'province' => 'Tehran',
                'district' => 'Markazi',
                'address'  => 'Main street, building 1',
                'status'   => 'active',
                'trash'    => false,
            ]
        );

        // ─────────────────────────────────────────
        // 2. Super Admin
        // ─────────────────────────────────────────
        $superAdmin = User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'branch_id'     => null, // سوپرادمین به شعبه‌ای وابسته نیست
                'name'          => 'Super Admin',
                'email'         => 'superadmin@example.com',
                'phone'         => '09120000001',
                'password'      => 'Super@123',
                'role'          => 'super_admin',
                'employee_code' => 'EMP-0001',
                'status'        => 'active',
                'trash'         => false,
            ]
        );

        // ─────────────────────────────────────────
        // 3. Admin
        // ─────────────────────────────────────────
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'branch_id'     => $mainBranch->id,
                'name'          => 'System Admin',
                'email'         => 'admin@example.com',
                'phone'         => '09120000002',
                'password'      => 'Admin@123',
                'role'          => 'admin',
                'employee_code' => 'EMP-0002',
                'status'        => 'active',
                'trash'         => false,
                'user_id'       => $superAdmin->id,
            ]
        );

        // ─────────────────────────────────────────
        // 4. Branch Manager
        // ─────────────────────────────────────────
        User::updateOrCreate(
            ['username' => 'manager'],
            [
                'branch_id'     => $mainBranch->id,
                'name'          => 'Branch Manager',
                'email'         => 'manager@example.com',
                'phone'         => '09120000003',
                'password'      => 'Manager@123',
                'role'          => 'branch_manager',
                'employee_code' => 'EMP-0003',
                'status'        => 'active',
                'trash'         => false,
                'user_id'       => $superAdmin->id,
            ]
        );

        // ─────────────────────────────────────────
        // 5. Cashier
        // ─────────────────────────────────────────
        User::updateOrCreate(
            ['username' => 'cashier'],
            [
                'branch_id'     => $mainBranch->id,
                'name'          => 'Cashier',
                'email'         => 'cashier@example.com',
                'phone'         => '09120000004',
                'password'      => 'Cashier@123',
                'role'          => 'cashier',
                'employee_code' => 'EMP-0004',
                'status'        => 'active',
                'trash'         => false,
                'user_id'       => $superAdmin->id,
            ]
        );

        // ─────────────────────────────────────────
        // 6. Accountant
        // ─────────────────────────────────────────
        User::updateOrCreate(
            ['username' => 'accountant'],
            [
                'branch_id'     => $mainBranch->id,
                'name'          => 'Accountant',
                'email'         => 'accountant@example.com',
                'phone'         => '09120000005',
                'password'      => 'Account@123',
                'role'          => 'accountant',
                'employee_code' => 'EMP-0005',
                'status'        => 'active',
                'trash'         => false,
                'user_id'       => $superAdmin->id,
            ]
        );

        // ─────────────────────────────────────────
        // 7. Investor
        // ─────────────────────────────────────────
        User::updateOrCreate(
            ['username' => 'investor'],
            [
                'branch_id'     => $mainBranch->id,
                'name'          => 'Investor',
                'email'         => 'investor@example.com',
                'phone'         => '09120000006',
                'password'      => 'Investor@123',
                'role'          => 'investor',
                'employee_code' => 'EMP-0006',
                'status'        => 'active',
                'trash'         => false,
                'user_id'       => $superAdmin->id,
            ]
        );

        // ─────────────────────────────────────────
        // 8. Salesperson
        // ─────────────────────────────────────────
        User::updateOrCreate(
            ['username' => 'salesperson'],
            [
                'branch_id'     => $mainBranch->id,
                'name'          => 'Salesperson',
                'email'         => 'salesperson@example.com',
                'phone'         => '09120000007',
                'password'      => 'Sales@123',
                'role'          => 'salesperson',
                'employee_code' => 'EMP-0007',
                'status'        => 'active',
                'trash'         => false,
                'user_id'       => $superAdmin->id,
            ]
        );
    }
}