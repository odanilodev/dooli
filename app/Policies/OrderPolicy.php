<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy extends BasePolicy
{
    public function view(User $user, mixed $model): bool
    {
        if (! $model instanceof Order) {
            return false;
        }

        // Students may only see their own orders; staff sees the company's.
        return ((int) $model->user_id === (int) $user->id) || parent::view($user, $model);
    }

    public function create(User $user): bool
    {
        return true;
    }
}
