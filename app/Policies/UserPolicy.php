<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    public function view(User $user, mixed $model): bool
    {
        if ($model instanceof User && $model->id === $user->id) {
            return true;
        }

        return parent::view($user, $model);
    }
}
