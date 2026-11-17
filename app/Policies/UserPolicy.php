<?php

namespace App\Policies;

use App\Enums\UserTypeCode;
use App\Models\User;

class UserPolicy extends BasePolicy
{
    public const MENU_LINK = '/admin/usuarios';

    /**
     * Non-admin staff holding the "Usuários" menu may only manage students.
     */
    private function managesStudents(User $user): bool
    {
        return $user->canAccessMenu(self::MENU_LINK);
    }

    private function isStudent(mixed $model): bool
    {
        return $model instanceof User && $model->userType?->code === UserTypeCode::Student->value;
    }

    private function canActOn(User $user, mixed $model): bool
    {
        if (! $this->sameCompany($user, $model)) {
            return false;
        }

        return $this->isAdmin($user) || ($this->managesStudents($user) && $this->isStudent($model));
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $this->managesStudents($user);
    }

    public function view(User $user, mixed $model): bool
    {
        if ($model instanceof User && $model->id === $user->id) {
            return true;
        }

        if ($this->isProtectedSuperAdmin($model) && ! $user->isSuperAdmin()) {
            return false;
        }

        return $this->canActOn($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $this->managesStudents($user);
    }

    public function update(User $user, mixed $model): bool
    {
        if ($this->isProtectedSuperAdmin($model)) {
            return $user->isSuperAdmin();
        }

        return $this->canActOn($user, $model);
    }

    public function delete(User $user, mixed $model): bool
    {
        if ($this->isProtectedSuperAdmin($model)) {
            return $user->isSuperAdmin() && $model->id !== $user->id;
        }

        return $this->canActOn($user, $model);
    }

    private function isProtectedSuperAdmin(mixed $model): bool
    {
        return $model instanceof User && $model->isSuperAdmin();
    }
}
