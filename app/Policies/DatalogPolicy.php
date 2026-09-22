<?php

namespace App\Policies;

use App\Models\User;

class DatalogPolicy extends BasePolicy
{
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $model): bool
    {
        return false;
    }

    public function delete(User $user, mixed $model): bool
    {
        return false;
    }
}
