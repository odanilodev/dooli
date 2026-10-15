<?php

namespace App\Policies;

use App\Models\User;

/**
 * Companies are platform-level (cross-tenant), so every action is restricted to
 * super admins. Company is the tenant root and has no company scope.
 */
class CompanyPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->isSuperAdmin($user);
    }
}
