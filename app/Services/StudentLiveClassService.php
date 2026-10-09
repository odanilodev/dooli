<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class StudentLiveClassService
{
    /**
     * Active live classes the user can access, ordered by start time.
     *
     * A live class is visible when it is active and linked to at least one
     * active team the user is actively enrolled in (not expired).
     *
     * @return Collection<int, LiveClass>
     */
    public function forUser(User $user): Collection
    {
        return LiveClass::query()
            ->where('status', GeneralStatus::Active->value)
            ->whereHas('teams', function ($query) use ($user) {
                $query->where('teams.status', GeneralStatus::Active->value)
                    ->where('team_live_classes.status', GeneralStatus::Active->value)
                    ->whereHas('users', fn ($u) => $this->activeEnrollmentConstraint($u, $user->id));
            })
            ->orderBy('starts_at')
            ->get();
    }

    private function activeEnrollmentConstraint($query, int $userId): void
    {
        $query->where('users.id', $userId)
            ->where('team_users.status', GeneralStatus::Active->value)
            ->where(function ($q) {
                $q->whereNull('team_users.expired_at')
                    ->orWhere('team_users.expired_at', '>', now());
            });
    }
}
