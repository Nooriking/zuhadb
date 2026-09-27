<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /* =========================================================
     |  Role Constants
     ========================================================= */

    public const ROLE_SUPER_ADMIN    = 'super_admin';
    public const ROLE_ADMIN          = 'admin';
    public const ROLE_BRANCH_MANAGER = 'branch_manager';
    public const ROLE_CASHIER        = 'cashier';
    public const ROLE_ACCOUNTANT     = 'accountant';
    public const ROLE_INVESTOR       = 'investor';
    public const ROLE_SALESPERSON    = 'salesperson';
    public const ROLE_STOCK_KEEPER   = 'stock_keeper';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_BRANCH_MANAGER,
        self::ROLE_CASHIER,
        self::ROLE_ACCOUNTANT,
        self::ROLE_INVESTOR,
        self::ROLE_SALESPERSON,
        self::ROLE_STOCK_KEEPER,
    ];

    public const ROLE_LABELS = [
        self::ROLE_SUPER_ADMIN    => 'Super Admin',
        self::ROLE_ADMIN          => 'Admin',
        self::ROLE_BRANCH_MANAGER => 'Branch Manager',
        self::ROLE_CASHIER        => 'Cashier',
        self::ROLE_ACCOUNTANT     => 'Accountant',
        self::ROLE_INVESTOR       => 'Investor',
        self::ROLE_SALESPERSON    => 'Salesperson',
        self::ROLE_STOCK_KEEPER   => 'Stock Keeper',
    ];

    public const SELECTABLE_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_BRANCH_MANAGER,
        self::ROLE_CASHIER,
        self::ROLE_ACCOUNTANT,
        self::ROLE_INVESTOR,
        self::ROLE_SALESPERSON,
        self::ROLE_STOCK_KEEPER,
    ];

    /* =========================================================
     |  Role Hierarchy
     ========================================================= */

    public const CREATABLE_ROLES = [
        self::ROLE_SUPER_ADMIN => [
            self::ROLE_ADMIN,
            self::ROLE_ACCOUNTANT,
            self::ROLE_BRANCH_MANAGER,
            self::ROLE_CASHIER,
            self::ROLE_SALESPERSON,
            self::ROLE_STOCK_KEEPER,
            self::ROLE_INVESTOR,
        ],
        self::ROLE_ADMIN => [
            self::ROLE_ACCOUNTANT,
            self::ROLE_BRANCH_MANAGER,
            self::ROLE_CASHIER,
            self::ROLE_SALESPERSON,
            self::ROLE_STOCK_KEEPER,
            self::ROLE_INVESTOR,
        ],
        self::ROLE_BRANCH_MANAGER => [
            self::ROLE_CASHIER,
            self::ROLE_SALESPERSON,
            self::ROLE_STOCK_KEEPER,
        ],
    ];

    /* =========================================================
     |  ⭐ MODULES per Role (Middleware-based)
     ========================================================= */

    public const ROLE_MODULES = [
        self::ROLE_SUPER_ADMIN => ['*'],

        self::ROLE_ADMIN => [
            'dashboard', 'companies', 'users',
            'brands', 'categories', 'products',
            'suppliers', 'customers', 'purchases', 'inventory',
            'stock-transfers', 'sales', 'sale-returns',
            'investors', 'investments', 'investor-withdrawals',
            'investor-profit-entries', 'investor-transactions',
            'expenses', 'reports', 'settings',
        ],

        self::ROLE_BRANCH_MANAGER => [
            'dashboard', 'users',
            'brands', 'products', 'suppliers', 'customers',
            'purchases', 'inventory', 'stock-transfers',
            'sales', 'sale-returns',
        ],

        self::ROLE_ACCOUNTANT => [
            'dashboard',
            'investors', 'investments', 'investor-withdrawals',
            'investor-profit-entries', 'investor-transactions',
            'expenses',
            'reports', 'products',
        ],

        self::ROLE_STOCK_KEEPER => [
            'dashboard',
            'brands', 'products', 'suppliers',
            'purchases', 'inventory', 'stock-transfers',
        ],

        self::ROLE_CASHIER => [
            'dashboard', 'customers', 'sales', 'sale-returns', 'inventory',
        ],

        // Shop-floor seller: products, sales, dues/receipts, returns, stock view
        self::ROLE_SALESPERSON => [
            'dashboard',
            'products',
            'customers',
            'sales',
            'sale-returns',
            'inventory',
        ],

        self::ROLE_INVESTOR => [
            'dashboard',
        ],
    ];

    /* =========================================================
     |  ⭐ PERMISSIONS per Role (Middleware-based)
     ========================================================= */

    public const ROLE_PERMISSIONS = [
        self::ROLE_SUPER_ADMIN => ['*'],

        self::ROLE_ADMIN => [
            'users.view', 'users.create', 'users.update', 'users.block', 'users.unblock',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'brands.view', 'brands.create',
            'suppliers.view', 'suppliers.create',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'inventory.view', 'inventory.adjust',
            'stock-transfers.view', 'stock-transfers.create',
            'sales.view', 'sales.create', 'sales.update', 'sales.delete',
            'sale-returns.view', 'sale-returns.create',
            'purchases.view', 'purchases.create', 'purchases.update', 'purchases.delete',
            'investors.view', 'investors.create', 'investors.update',
            'investments.view', 'investments.create', 'investments.update',
            'withdrawals.view', 'withdrawals.create', 'withdrawals.approve',
            'profit-entries.view',
            'expenses.view', 'expenses.create', 'expenses.update',
            'reports.view',
            'settings.view', 'settings.update',
        ],

        self::ROLE_BRANCH_MANAGER => [
            'users.view', 'users.create', 'users.update', 'users.block', 'users.unblock',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'brands.view', 'brands.create',
            'suppliers.view', 'suppliers.create',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'inventory.view', 'inventory.adjust',
            'stock-transfers.view', 'stock-transfers.create',
            'sales.view', 'sales.create',
            'sale-returns.view', 'sale-returns.create',
            'purchases.view', 'purchases.create',
        ],

        self::ROLE_ACCOUNTANT => [
            'products.view',
            'investors.view', 'investors.create',
            'investments.view', 'investments.create',
            'withdrawals.view', 'withdrawals.create',
            'profit-entries.view',
            'expenses.view', 'expenses.create',
            'reports.view',
        ],

        self::ROLE_STOCK_KEEPER => [
            'products.view', 'products.create', 'products.update', 'products.delete',
            'brands.view', 'brands.create',
            'suppliers.view', 'suppliers.create',
            'inventory.view', 'inventory.adjust',
            'purchases.view', 'purchases.create',
            'stock-transfers.view', 'stock-transfers.create',
        ],

        self::ROLE_CASHIER => [
            'sales.view', 'sales.create', 'sales.update',
            'sale-returns.view', 'sale-returns.create',
            'customers.view', 'customers.create', 'customers.update',
            'inventory.view',
        ],

        self::ROLE_SALESPERSON => [
            'sales.view', 'sales.create', 'sales.update',
            'sale-returns.view', 'sale-returns.create',
            'customers.view', 'customers.create', 'customers.update',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'inventory.view',
        ],

        self::ROLE_INVESTOR => [
            'investments.view',
            'profit-entries.view',
            'transactions.view',
        ],
    ];

    /* =========================================================
     |  Fillable / Casts
     ========================================================= */

    protected $fillable = [
        'branch_id',
        'name',
        'username',
        'email',
        'google_id',
        'phone',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'role',
        'employee_code',
        'avatar',
        'last_login_at',
        'status',
        'trash',
        'user_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $appends = [
        'two_factor_enabled',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'two_factor_secret'  => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'trash'             => 'boolean',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
            'deleted_at'        => 'datetime',
        ];
    }

    public function hasEnabledTwoFactor(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function getTwoFactorEnabledAttribute(): bool
    {
        return $this->hasEnabledTwoFactor();
    }

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function managedBranches(): HasMany
    {
        return $this->hasMany(Branch::class, 'manager_user_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Product::class, 'user_id')->whereNotNull('variant_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopeActive($query)      { return $query->where('status', 'active'); }
    public function scopeInactive($query)    { return $query->where('status', 'inactive'); }
    public function scopeBlocked($query)     { return $query->where('status', 'blocked'); }
    public function scopeNotTrashed($query)  { return $query->where('trash', false); }
    public function scopeOnlyTrashed($query)
    {
        return $query->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)->where('trash', true);
    }
    public function scopeByRole($query, string $role) { return $query->where('role', $role); }

    public function scopeVisibleTo($query, User $authUser)
    {
        if ($authUser->isSuperAdmin() || $authUser->isAdmin()) {
            return $query;
        }

        return $query->where('branch_id', $authUser->branch_id);
    }

    public function scopeManageableBy($query, User $authUser)
    {
        if ($authUser->isSuperAdmin()) {
            return $query;
        }

        if ($authUser->isAdmin()) {
            return $query->whereNotIn('role', [
                self::ROLE_SUPER_ADMIN,
                self::ROLE_ADMIN,
            ]);
        }

        if ($authUser->isBranchManager()) {
            return $query->where('branch_id', $authUser->branch_id)
                         ->whereIn('role', self::CREATABLE_ROLES[self::ROLE_BRANCH_MANAGER] ?? []);
        }

        return $query->whereRaw('1 = 0');
    }

    /* =========================================================
     |  Role Helpers
     ========================================================= */

    public function hasRole(string $role): bool    { return $this->role === $role; }
    public function hasAnyRole(array $roles): bool { return in_array($this->role, $roles, true); }
    public function isSuperAdmin(): bool           { return $this->hasRole(self::ROLE_SUPER_ADMIN); }
    public function isAdmin(): bool                { return $this->hasRole(self::ROLE_ADMIN); }
    public function isBranchManager(): bool        { return $this->hasRole(self::ROLE_BRANCH_MANAGER); }

    /** Shop staff stay on their own branch and do not pick another one. */
    public function isBranchScoped(): bool
    {
        return (bool) $this->branch_id
            && ! $this->isSuperAdmin()
            && ! $this->isAdmin();
    }
    public function isCashier(): bool              { return $this->hasRole(self::ROLE_CASHIER); }
    public function isAccountant(): bool           { return $this->hasRole(self::ROLE_ACCOUNTANT); }
    public function isInvestor(): bool             { return $this->hasRole(self::ROLE_INVESTOR); }
    public function isSalesperson(): bool          { return $this->hasRole(self::ROLE_SALESPERSON); }
    public function isStockKeeper(): bool          { return $this->hasRole(self::ROLE_STOCK_KEEPER); }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }

    public function getCreatableRoles(): array
    {
        return self::CREATABLE_ROLES[$this->role] ?? [];
    }

    public function getCreatableRoleLabels(): array
    {
        $creatable = $this->getCreatableRoles();

        return collect(self::ROLE_LABELS)
            ->only($creatable)
            ->toArray();
    }

    /* =========================================================
     |  ⭐ MIDDLEWARE-BASED: Permission Helpers
     ========================================================= */

    public function getPermissions(): array
    {
        return self::ROLE_PERMISSIONS[$this->role] ?? [];
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = $this->getPermissions();

        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    public function canAccessModule(string $module): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $modules = self::ROLE_MODULES[$this->role] ?? [];

        if (in_array('*', $modules, true)) {
            return true;
        }

        return in_array($module, $modules, true);
    }

    public function getAccessibleModules(): array
    {
        if ($this->isSuperAdmin()) {
            return ['*'];
        }

        return self::ROLE_MODULES[$this->role] ?? [];
    }

    /* =========================================================
     |  Users Module Helpers
     ========================================================= */

    public function canAccessUsers(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_BRANCH_MANAGER,
        ]);
    }

    public function canCreateUsers(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_BRANCH_MANAGER,
        ]);
    }

    public function canUpdateUsers(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_BRANCH_MANAGER,
        ]);
    }

    public function canBlockUsers(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_BRANCH_MANAGER,
        ]);
    }

    public function canUnblockUsers(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_BRANCH_MANAGER,
        ]);
    }

    public function canDeleteUsers(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canRestoreUsers(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canForceDeleteUsers(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canViewTrash(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageBranches(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin();
    }

    /* =========================================================
     |  ⭐ TARGET-BASED PERMISSIONS
     |  قوانین:
     |  - Super Admin هرگز قابل مدیریت/بلاک/حذف توسط دیگران نیست
     |  - Admin فقط توسط Super Admin قابل مدیریت/بلاک است
     |  - Branch Manager فقط کاربران شعبه خودش را مدیریت می‌کند
     ========================================================= */

    public function canManageUser(User $target): bool
    {
        // ⛔ هیچ‌کس نمی‌تواند خودش را مدیریت کند
        if ($this->id === $target->id) {
            return false;
        }

        // Super Admin می‌تواند همه را مدیریت کند (به جز خودش)
        if ($this->isSuperAdmin()) {
            return true;
        }

        // ⛔ Super Admin فقط توسط Super Admin قابل مدیریت است
        if ($target->isSuperAdmin()) {
            return false;
        }

        // Admin می‌تواند همه را مدیریت کند مگر Super Admin و Admin دیگر
        if ($this->isAdmin()) {
            return ! $target->isAdmin();
        }

        // Branch Manager فقط کاربران شعبه خودش
        if ($this->isBranchManager()) {
            return $target->branch_id === $this->branch_id
                && in_array($target->role, $this->getCreatableRoles(), true);
        }

        return false;
    }

    public function canBlockTarget(User $target): bool
    {
        // ⛔ نمی‌توانی خودت را بلاک کنی
        if ($this->id === $target->id) {
            return false;
        }

        // ⛔ Super Admin هرگز بلاک نمی‌شود — حتی توسط Super Admin دیگر
        if ($target->isSuperAdmin()) {
            return false;
        }

        if (! $this->canBlockUsers()) {
            return false;
        }

        // اگر قبلاً بلاک شده، دیگر بلاک نکن
        if ($target->status === 'blocked') {
            return false;
        }

        // ⛔ Admin فقط توسط Super Admin قابل بلاک است
        if ($target->isAdmin()) {
            return $this->isSuperAdmin();
        }

        // Branch Manager فقط کاربران شعبه خودش
        if ($this->isBranchManager()) {
            return $target->branch_id === $this->branch_id;
        }

        return true;
    }

    public function canUnblockTarget(User $target): bool
    {
        // ⛔ Super Admin هرگز آنبلاک نمی‌شود (چون اصلاً نباید بلاک شود)
        if ($target->isSuperAdmin()) {
            return false;
        }

        if (! $this->canUnblockUsers()) {
            return false;
        }

        // فقط کاربران بلاک‌شده قابل آنبلاک هستند
        if ($target->status !== 'blocked') {
            return false;
        }

        // ⛔ Admin فقط توسط Super Admin قابل آنبلاک است
        if ($target->isAdmin()) {
            return $this->isSuperAdmin();
        }

        // Branch Manager فقط کاربران شعبه خودش
        if ($this->isBranchManager()) {
            return $target->branch_id === $this->branch_id;
        }

        return true;
    }
}