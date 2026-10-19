<?php

namespace App\Services;

use App\DTO\CreateTeamDTO;
use App\Enums\AccessGrantSource;
use App\Enums\GeneralStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AccessGrant;
use App\Models\Course;
use App\Models\Team;
use App\Models\User;
use App\Repositories\TeamRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class TeamService
{
    public function __construct(
        private readonly TeamRepository $teams,
        private readonly DatalogService $audit,
        private readonly ScheduleService $schedules,
        private readonly AccessGrantService $accessGrants,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->teams->paginate(
            perPage: $perPage,
            filters: $filters,
            withCount: ['users', 'courses', 'schedules'],
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

        $this->schedules->pruneUnreachable($team);
    }

    /**
     * Attach one or more courses to several teams, reporting per-target results.
     *
     * @param  array<int, int>  $teamIds
     * @param  array<int, int>  $courseIds
     * @return array{processed: int, failed: array<int, array{id: int, name: ?string, reason: string}>}
     */
    public function bulkAttachCourses(array $teamIds, array $courseIds): array
    {
        $teams = Team::query()->whereIn('id', $teamIds)->get()->keyBy('id');
        $processed = 0;
        $failed = [];

        foreach ($teamIds as $teamId) {
            $teamId = (int) $teamId;
            $team = $teams[$teamId] ?? null;

            if (! $team) {
                $failed[] = ['id' => $teamId, 'name' => null, 'reason' => 'Turma não encontrada.'];

                continue;
            }

            foreach ($courseIds as $courseId) {
                try {
                    $this->attachCourse($team, (int) $courseId);
                    $processed++;
                } catch (Throwable $e) {
                    $failed[] = ['id' => $teamId, 'name' => $team->name, 'reason' => $e->getMessage()];
                }
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /**
     * Enroll one or more students into several teams.
     *
     * @param  array<int, int>  $teamIds
     * @param  array<int, int>  $userIds
     * @return array{processed: int, failed: array<int, array{id: int, name: ?string, reason: string}>}
     */
    public function bulkEnroll(array $teamIds, array $userIds, ?string $expiredAt = null): array
    {
        $teams = Team::query()->whereIn('id', $teamIds)->get()->keyBy('id');
        $processed = 0;
        $failed = [];

        foreach ($teamIds as $teamId) {
            $teamId = (int) $teamId;
            $team = $teams[$teamId] ?? null;

            if (! $team) {
                $failed[] = ['id' => $teamId, 'name' => null, 'reason' => 'Turma não encontrada.'];

                continue;
            }

            foreach ($userIds as $userId) {
                try {
                    $this->addStudent($team, (int) $userId, $expiredAt);
                    $processed++;
                } catch (Throwable $e) {
                    $failed[] = ['id' => (int) $userId, 'name' => null, 'reason' => $e->getMessage()];
                }
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /**
     * @param  array<int, int>  $teamIds
     * @return array{processed: int, failed: array<int, array{id: int, name: ?string, reason: string}>}
     */
    public function bulkSetStatus(array $teamIds, string $status): array
    {
        return $this->runBulk(
            $teamIds,
            fn (int $id) => Team::query()->find($id),
            fn (Team $team) => $this->update($team, ['status' => $status]),
        );
    }

    /**
     * @param  array<int, int>  $teamIds
     * @return array{processed: int, failed: array<int, array{id: int, name: ?string, reason: string}>}
     */
    public function bulkDelete(array $teamIds): array
    {
        return $this->runBulk(
            $teamIds,
            fn (int $id) => Team::query()->find($id),
            fn (Team $team) => $this->delete($team),
        );
    }

    /**
     * @param  array<int, int>  $ids
     * @return array{processed: int, failed: array<int, array{id: int, name: ?string, reason: string}>}
     */
    private function runBulk(array $ids, callable $resolve, callable $worker): array
    {
        $processed = 0;
        $failed = [];

        foreach ($ids as $id) {
            $id = (int) $id;
            $model = $resolve($id);

            if (! $model) {
                $failed[] = ['id' => $id, 'name' => null, 'reason' => 'Não encontrado.'];

                continue;
            }

            try {
                $worker($model);
                $processed++;
            } catch (Throwable $e) {
                $failed[] = ['id' => $id, 'name' => $model->name ?? null, 'reason' => $e->getMessage()];
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    public function addStudent(Team $team, int $userId, ?string $expiredAt = null): void
    {
        $user = User::query()->findOrFail($userId);

        if ($user->company_id !== $team->company_id) {
            throw new BusinessRuleException('Aluno pertence a outra empresa.');
        }

        $this->accessGrants->grantManual(
            companyId: (int) $team->company_id,
            userId: (int) $user->id,
            teamId: (int) $team->id,
            expiresAt: $expiredAt ? Carbon::parse($expiredAt) : null,
        );

        $this->audit->log('team', 'add_student', 'team', "Aluno {$user->name} adicionado à turma {$team->name}");
    }

    public function removeStudent(Team $team, int $userId): void
    {
        AccessGrant::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $team->company_id)
            ->where('team_id', $team->id)
            ->where('user_id', $userId)
            ->where('source_type', AccessGrantSource::Manual->value)
            ->where('source_id', 0)
            ->update(['status' => GeneralStatus::Inactive->value]);

        $this->accessGrants->syncTeamMembership((int) $team->company_id, (int) $team->id, $userId);

        $activeGrants = AccessGrant::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $team->company_id)
            ->where('team_id', $team->id)
            ->where('user_id', $userId)
            ->where('status', GeneralStatus::Active->value)
            ->get()
            ->filter(fn (AccessGrant $grant) => $grant->isActive());

        if ($activeGrants->isEmpty()) {
            $team->users()->detach($userId);
        }

        $this->audit->log('team', 'remove_student', 'team', "Aluno {$userId} removido da turma {$team->name}");
    }

    public function updateStudent(Team $team, int $userId, array $data): void
    {
        if (! $team->users()->whereKey($userId)->exists()) {
            throw new BusinessRuleException('Aluno não está matriculado nesta turma.');
        }

        $status = $data['status'] ?? GeneralStatus::Active->value;
        $expiresAt = $data['expired_at'] ?? null;

        AccessGrant::query()->withoutGlobalScope('company')->updateOrCreate([
            'company_id' => $team->company_id,
            'user_id' => $userId,
            'team_id' => $team->id,
            'source_type' => AccessGrantSource::Manual->value,
            'source_id' => 0,
        ], [
            'starts_at' => now(),
            'expires_at' => $expiresAt ? Carbon::parse($expiresAt) : null,
            'status' => $status,
        ]);

        $this->accessGrants->syncTeamMembership((int) $team->company_id, (int) $team->id, $userId);

        $this->audit->log('team', 'update_student', 'team', "Vínculo do aluno {$userId} atualizado na turma {$team->name}");
    }

    public function students(Team $team, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->teams->paginateStudents($team, $filters, $perPage);
    }

    /**
     * @return Collection<int, Course>
     */
    public function courses(Team $team): Collection
    {
        return $team->courses()->get();
    }
}
