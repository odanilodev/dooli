<?php

namespace App\Policies;

use App\Models\CertificateUser;
use App\Models\User;

class CertificateUserPolicy extends BasePolicy
{
    public function view(User $user, mixed $model): bool
    {
        if (! $model instanceof CertificateUser) {
            return false;
        }

        return $model->user_id === $user->id || parent::view($user, $model);
    }
}
