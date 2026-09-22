<?php

namespace App\Repositories;

use App\Enums\GeneralStatus;
use App\Models\Team;

class TeamRepository extends BaseRepository
{
    protected array $searchable = ['name'];

    protected array $filterable = ['status', 'company_id'];

    protected string $defaultSort = 'sort_order';

    protected string $defaultDirection = 'asc';

    public function __construct()
    {
        parent::__construct(new Team);
    }

    public function findWithRelations(int $id): Team
    {
        return $this->query()->with(['courses', 'users'])->findOrFail($id);
    }

    /**
     * Teams that a user is actively enrolled in.
     */
    public function activeTeamIdsForUser(int $userId): array
    {
        return $this->query()
            ->whereHas('users', function ($q) use ($userId) {
                $q->where('users.id', $userId)
                    ->where('team_users.status', GeneralStatus::Active->value)
                    ->where(function ($q) {
                        $q->whereNull('team_users.expired_at')
                            ->orWhere('team_users.expired_at', '>', now());
                    });
            })
            ->pluck('teams.id')
            ->all();
    }
}
