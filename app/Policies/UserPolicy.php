<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, mixed $model): bool
    {
        if ($model instanceof User && $model->id === $user->id) {
            return true;
        }

        return $this->isAdmin($user) && $this->sameCompany($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->isAdmin($user) && $this->sameCompany($user, $model);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->isAdmin($user) && $this->sameCompany($user, $model);
    }
}
