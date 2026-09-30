<?php

namespace App\Policies;

use App\Models\User;

class MenuPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->isAdmin($user);
    }
}
