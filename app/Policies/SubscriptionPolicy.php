<?php

namespace App\Policies;

use App\Models\User;

class SubscriptionPolicy extends BasePolicy
{
    public function view(User $user, mixed $model): bool
    {
        return ((int) $model->user_id === (int) $user->id) || parent::view($user, $model);
    }

    public function cancel(User $user, mixed $model): bool
    {
        return $this->view($user, $model);
    }
}
