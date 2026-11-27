<?php

namespace App\Policies;

use App\Models\User;
use App\Support\SalesVisibility;

class PaymentPolicy extends BasePolicy
{
    public function view(User $user, mixed $model): bool
    {
        return parent::view($user, $model) && SalesVisibility::allows($user, $model->order?->seller_id);
    }

    public function update(User $user, mixed $model): bool
    {
        return parent::update($user, $model) && SalesVisibility::allows($user, $model->order?->seller_id);
    }

    public function delete(User $user, mixed $model): bool
    {
        return parent::delete($user, $model) && SalesVisibility::allows($user, $model->order?->seller_id);
    }
}
