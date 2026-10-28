<?php

namespace App\Policies;

use App\Models\Investor;
use App\Models\User;

class InvestorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('investors.view');
    }

    public function view(User $user, Investor $investor): bool
    {
        return $user->hasPermission('investors.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('investors.create');
    }

    public function update(User $user, Investor $investor): bool
    {
        return $user->hasPermission('investors.update')
            || $user->hasPermission('investors.create');
    }

    public function delete(User $user, Investor $investor): bool
    {
        return $user->hasPermission('investors.update')
            || $user->isSuperAdmin();
    }
}
