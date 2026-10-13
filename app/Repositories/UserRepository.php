<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserRepository extends BaseRepository
{
    protected array $searchable = ['name', 'email', 'username'];

    protected array $filterable = ['status', 'user_type_id', 'company_id'];

    protected string $defaultSort = 'id';

    public function __construct()
    {
        parent::__construct(new User);
    }

    public function findByLogin(string $login): ?User
    {
        return $this->query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();
    }

    public function findWithRelations(int $id): User
    {
        return $this->query()->with(['userType', 'teams'])->findOrFail($id);
    }

    public function delete(Model $model): bool
    {
        return DB::transaction(fn () => parent::delete($model));
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        $excludeTeamId = $filters['exclude_team_id'] ?? null;
        unset($filters['exclude_team_id']);

        if (is_numeric($excludeTeamId)) {
            $query->whereDoesntHave('teams', fn (Builder $q) => $q->whereKey((int) $excludeTeamId));
        }

        parent::applyFilters($query, $filters);
    }
}
