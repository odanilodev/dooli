<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Support\SalesVisibility;

class OrderPolicy extends BasePolicy
{
    public function view(User $user, mixed $model): bool
    {
        if (! $model instanceof Order) {
            return false;
        }

        // Buyers see their own orders; staff access follows the sales visibility rule.
        return ((int) $model->user_id === (int) $user->id) || $this->viewSale($user, $model);
    }

    /**
     * Seeing the order as a sale (admin area): owning it as a buyer is not enough.
     */
    public function viewSale(User $user, Order $order): bool
    {
        return parent::view($user, $order) && SalesVisibility::allows($user, $order->seller_id);
    }

    public function create(User $user): bool
    {
        return true;
    }
}
