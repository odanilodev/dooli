<?php

namespace App\Policies;

use App\Enums\UserTypeCode;
use App\Models\User;

abstract class BasePolicy
{
    protected function isAdmin(User $user): bool
    {
        return $user->hasRole(UserTypeCode::Admin->value);
    }

    protected function isStaff(User $user): bool
    {
        return $user->hasRole(UserTypeCode::Admin->value, UserTypeCode::Instructor->value);
    }

    protected function sameCompany(User $user, mixed $model): bool
    {
        return (int) $model->company_id === (int) $user->company_id;
    }

    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->isStaff($user) && $this->sameCompany($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->isAdmin($user) && $this->sameCompany($user, $model);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->isAdmin($user) && $this->sameCompany($user, $model);
    }
}
