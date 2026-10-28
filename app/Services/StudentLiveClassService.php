<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class StudentLiveClassService
{
    private const MAX_ITEMS = 10;

    /**
     * Active live classes the user can access, ordered by start time.
     *
     * A live class is visible when it is active and linked to at least one
     * active team the user is actively enrolled in (not expired). Classes
     * that have already ended are excluded and the response is capped.
     *
     * @return Collection<int, LiveClass>
     */
    public function forUser(User $user): Collection
    {
        $now = now();

        return LiveClass::query()
            ->where('status', GeneralStatus::Active->value)
            ->whereHas('teams', function ($query) use ($user) {
                $query->where('teams.status', GeneralStatus::Active->value)
                    ->where('team_live_classes.status', GeneralStatus::Active->value)
                    ->whereHas('users', fn ($u) => $this->activeEnrollmentConstraint($u, $user->id));
            })
            ->orderBy('starts_at')
            ->get()
            ->filter(function (LiveClass $liveClass) use ($now) {
                if ($liveClass->starts_at === null) {
                    return false;
                }

                return $liveClass->starts_at->copy()
                    ->addMinutes($liveClass->duration)
                    ->greaterThanOrEqualTo($now);
            })
            ->take(self::MAX_ITEMS)
            ->values();
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
