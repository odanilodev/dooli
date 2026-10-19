<?php

namespace App\Policies;

use App\Models\User;

class CommentPolicy extends BasePolicy
{
    public function deleteOwn(User $user, mixed $model): bool
    {
        return $this->sameCompany($user, $model)
            && (int) $model->user_id === (int) $user->id;
    }
}
