<?php

namespace App\Services;

use App\DTO\CreateTeamDTO;
use App\Enums\GeneralStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Course;
use App\Models\Team;
use App\Models\User;
use App\Repositories\TeamRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TeamService
{
    public function __construct(
        private readonly TeamRepository $teams,
        private readonly DatalogService $audit,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->teams->paginate(
            perPage: $perPage,
            filters: $filters,
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'asc',
        );
    }

    public function find(int $id): Team
    {
        return $this->teams->findWithRelations($id);
    }

    public function create(CreateTeamDTO $dto): Team
    {
        $team = $this->teams->create($dto->toArray());

        $this->audit->log('team', 'create', 'team', "Turma criada: {$team->name}");

        return $team;
    }

    public function update(Team $team, array $data): Team
    {
        return $this->teams->update($team, $data);
    }

    public function delete(Team $team): void
    {
        DB::transaction(function () use ($team) {
            $team->courses()->detach();
            $team->users()->detach();
            $team->delete();
        });

        $this->audit->log('team', 'delete', 'team', "Turma excluída: {$team->name}");
    }

    public function attachCourse(Team $team, int $courseId): void
    {
        $course = Course::query()->findOrFail($courseId);

        $team->courses()->syncWithoutDetaching([
            $course->id => [
                'company_id' => $team->company_id,
                'status' => GeneralStatus::Active->value,
            ],
        ]);

        $this->audit->log('team', 'attach_course', 'team', "Curso {$course->name} associado à turma {$team->name}");
    }

    public function detachCourse(Team $team, int $courseId): void
    {
        $team->courses()->detach($courseId);
    }

    public function addStudent(Team $team, int $userId, ?string $expiredAt = null): void
    {
        $user = User::query()->findOrFail($userId);

        if ($user->company_id !== $team->company_id) {
            throw new BusinessRuleException('Aluno pertence a outra empresa.');
        }

        $team->users()->syncWithoutDetaching([
            $user->id => [
                'company_id' => $team->company_id,
                'status' => GeneralStatus::Active->value,
                'expired_at' => $expiredAt,
            ],
        ]);

        $this->audit->log('team', 'add_student', 'team', "Aluno {$user->name} adicionado à turma {$team->name}");
    }

    public function removeStudent(Team $team, int $userId): void
    {
        $team->users()->detach($userId);

        $this->audit->log('team', 'remove_student', 'team', "Aluno {$userId} removido da turma {$team->name}");
    }

    /**
     * @return Collection<int, User>
     */
    public function students(Team $team): Collection
    {
        return $team->users()->get();
    }

    /**
     * @return Collection<int, Course>
     */
    public function courses(Team $team): Collection
    {
        return $team->courses()->get();
    }
}
