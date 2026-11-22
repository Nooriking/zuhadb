<?php

namespace Database\Seeders\Qa;

use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\Qa\Concerns\GuardsQaDatabase;
use Database\Seeders\Qa\Concerns\QaContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class QaFoundationSeeder extends Seeder
{
    use GuardsQaDatabase;

    public function __construct(private readonly QaContext $ctx) {}

    public function run(): void
    {
        $this->assertQaDatabase();

        $settingPayload = [
            'name' => 'ZUHA PERFUME',
            'company_name' => 'ZUHA PERFUME',
            'company_name_local' => 'QA Seed Environment',
            'phone' => '0744442863',
            'email' => 'qa@zuha.test',
            'address' => 'Kabul QA Branch',
            'logo' => null,
        ];
        $setting = AppSetting::query()->first();
        if ($setting) {
            $setting->update($settingPayload);
        } else {
            AppSetting::query()->create($settingPayload);
        }

        $main = Branch::query()->updateOrCreate(
            ['code' => 'QA-MAIN'],
            [
                'name' => 'QA Main Shop',
                'code' => 'QA-MAIN',
                'phone' => '0702000001',
                'email' => 'main@zuha.qa',
                'address' => 'Kabul',
                'status' => 'active',
                'trash' => false,
            ]
        );

        $second = Branch::query()->updateOrCreate(
            ['code' => 'QA-HERAT'],
            [
                'name' => 'QA Herat Shop',
                'code' => 'QA-HERAT',
                'phone' => '0702000002',
                'email' => 'herat@zuha.qa',
                'address' => 'Herat',
                'status' => 'active',
                'trash' => false,
            ]
        );

        $admin = User::query()->updateOrCreate(
            ['username' => 'qa_admin'],
            [
                'name' => 'QA Admin',
                'email' => 'qa.admin@zuha.test',
                'phone' => '0703000001',
                'password' => Hash::make('QaAdmin@123'),
                'role' => User::ROLE_ADMIN,
                'branch_id' => $main->id,
                'status' => 'active',
                'trash' => false,
            ]
        );

        User::query()->updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'email' => 'zuhaperfume.af@gmail.com',
                'phone' => '0744442863',
                'password' => Hash::make('Super@123'),
                'role' => User::ROLE_SUPER_ADMIN,
                'branch_id' => null,
                'status' => 'active',
                'trash' => false,
            ]
        );

        $cashier = User::query()->updateOrCreate(
            ['username' => 'qa_cashier'],
            [
                'name' => 'QA Cashier',
                'email' => 'qa.cashier@zuha.test',
                'phone' => '0703000002',
                'password' => Hash::make('QaCashier@123'),
                'role' => User::ROLE_CASHIER,
                'branch_id' => $main->id,
                'status' => 'active',
                'trash' => false,
            ]
        );

        $stock = User::query()->updateOrCreate(
            ['username' => 'qa_stock'],
            [
                'name' => 'QA Stock Keeper',
                'email' => 'qa.stock@zuha.test',
                'phone' => '0703000003',
                'password' => Hash::make('QaStock@123'),
                'role' => User::ROLE_STOCK_KEEPER,
                'branch_id' => $main->id,
                'status' => 'active',
                'trash' => false,
            ]
        );

        User::query()->updateOrCreate(
            ['username' => 'qa_herat_mgr'],
            [
                'name' => 'QA Herat Manager',
                'email' => 'qa.herat@zuha.test',
                'phone' => '0703000004',
                'password' => Hash::make('QaManager@123'),
                'role' => User::ROLE_BRANCH_MANAGER,
                'branch_id' => $second->id,
                'status' => 'active',
                'trash' => false,
            ]
        );

        $supplierAfn = Supplier::query()->updateOrCreate(
            ['email' => 'supplier.afn@zuha.qa'],
            [
                'name' => 'Kabul Perfume Wholesale',
                'phone' => '0781000001',
                'contact_person' => 'Karim Supplier',
                'address' => 'Kabul Mandawi',
                'opening_balance' => 0,
                'status' => 'active',
                'trash' => false,
                'user_id' => $admin->id,
            ]
        );

        $supplierUsd = Supplier::query()->updateOrCreate(
            ['email' => 'supplier.usd@zuha.qa'],
            [
                'name' => 'Dubai Fragrance Trading',
                'phone' => '0781000002',
                'contact_person' => 'Omar Supplier',
                'address' => 'Dubai Deira',
                'opening_balance' => 0,
                'status' => 'active',
                'trash' => false,
                'user_id' => $admin->id,
            ]
        );

        $cashCustomer = Customer::query()->updateOrCreate(
            ['phone' => '0791000001'],
            ['name' => 'QA Cash Customer', 'status' => 'active', 'trash' => false, 'current_due_afn' => 0]
        );
        $creditCustomer = Customer::query()->updateOrCreate(
            ['phone' => '0791000002'],
            ['name' => 'QA Credit Customer', 'status' => 'active', 'trash' => false, 'current_due_afn' => 0, 'credit_limit_afn' => 50000]
        );
        $partialCustomer = Customer::query()->updateOrCreate(
            ['phone' => '0791000003'],
            ['name' => 'QA Partial Pay Customer', 'status' => 'active', 'trash' => false, 'current_due_afn' => 0, 'credit_limit_afn' => 30000]
        );

        $this->ctx->admin = $admin->fresh();
        $this->ctx->cashier = $cashier->fresh();
        $this->ctx->stockKeeper = $stock->fresh();
        $this->ctx->mainBranch = $main->fresh();
        $this->ctx->secondBranch = $second->fresh();
        $this->ctx->supplierAfn = $supplierAfn->fresh();
        $this->ctx->supplierUsd = $supplierUsd->fresh();
        $this->ctx->cashCustomer = $cashCustomer->fresh();
        $this->ctx->creditCustomer = $creditCustomer->fresh();
        $this->ctx->partialCustomer = $partialCustomer->fresh();

        $this->command?->info('Foundation users, branches, suppliers, customers ready.');
    }
}
