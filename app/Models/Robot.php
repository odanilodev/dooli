<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\GeneralStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['company_id', 'slug', 'name', 'description', 'market', 'icon', 'status', 'sort_order'])]
class Robot extends Model
{
    use BelongsToCompany;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'robot_team')
            ->withPivot(['company_id'])
            ->withTimestamps();
    }

    /**
     * Ferramentas que o usuário pode usar: administradores veem todas; aluno só as
     * ligadas a uma turma em que tem vínculo ativo e não expirado.
     */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereExists(function ($sub) use ($user) {
            $sub->selectRaw('1')
                ->from('robot_team')
                ->join('team_users', 'team_users.team_id', '=', 'robot_team.team_id')
                ->whereColumn('robot_team.robot_id', 'robots.id')
                ->where('team_users.user_id', $user->id)
                ->where('team_users.status', GeneralStatus::Active->value)
                ->where(fn ($q) => $q->whereNull('team_users.expired_at')->orWhere('team_users.expired_at', '>', now()));
        });
    }
}
