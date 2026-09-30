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

    protected function canManage(User $user): bool
    {
        return $this->isAdmin($user) || ! empty($user->userType?->permission_menu);
    }

    protected function sameCompany(User $user, mixed $model): bool
    {
        return (int) $model->company_id === (int) $user->company_id;
    }

    public function viewAny(User $user): bool
    {
        return $this->isStaff($user) || $this->canManage($user);
    }

    public function view(User $user, mixed $model): bool
    {
        return ($this->isStaff($user) || $this->canManage($user)) && $this->sameCompany($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->canManage($user) && $this->sameCompany($user, $model);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->canManage($user) && $this->sameCompany($user, $model);
    }
}
