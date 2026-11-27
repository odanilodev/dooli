<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use App\Support\SalesVisibility;

class SubscriptionPolicy extends BasePolicy
{
    public function view(User $user, mixed $model): bool
    {
        // Buyers see their own subscriptions; staff access follows the sales visibility rule.
        return ((int) $model->user_id === (int) $user->id) || $this->viewSale($user, $model);
    }

    /**
     * Seeing the subscription as a sale (admin area): owning it as a buyer is not enough.
     */
    public function viewSale(User $user, Subscription $subscription): bool
    {
        return parent::view($user, $subscription) && SalesVisibility::allows($user, $subscription->seller_id);
    }

    public function cancel(User $user, mixed $model): bool
    {
        return $this->view($user, $model);
    }
}
