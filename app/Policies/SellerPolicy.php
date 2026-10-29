<?php

namespace App\Policies;

use App\Models\User;

class SellerPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $this->isSuperAdmin($user);
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->viewAny($user) && $this->sameCompany($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->view($user, $model);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->view($user, $model);
    }
}
