<?php

namespace App\Policies;

use App\Models\Investor;
use App\Models\User;

class InvestorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('investors.view') || $user->isInvestor();
    }

    public function view(User $user, Investor $investor): bool
    {
        // Investor identity is the user row itself.
        if ($user->isInvestor()) {
            return (int) $investor->id === (int) $user->id && ! $investor->trash;
        }

        return $user->hasPermission('investors.view');
    }

    public function create(User $user): bool
    {
        // Investor identity is created via Users module + investor role.
        return false;
    }

    public function update(User $user, Investor $investor): bool
    {
        if ($user->isInvestor()) {
            return false;
        }

        // Status toggle / overview actions only — identity edits happen on Users.
        return $user->hasPermission('investors.update')
            || $user->hasPermission('investors.create');
    }

    public function delete(User $user, Investor $investor): bool
    {
        if ($user->isInvestor()) {
            return false;
        }

        return $user->hasPermission('investors.update')
            || $user->isSuperAdmin();
    }
}
