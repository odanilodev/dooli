<?php

namespace App\Repositories;

use App\Models\CertificateUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CertificateUserRepository extends BaseRepository
{
    protected array $filterable = ['company_id', 'user_id', 'team_id', 'certificate_id'];

    public function __construct()
    {
        parent::__construct(new CertificateUser);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['course_id'])) {
            $query->whereHas(
                'certificate',
                fn (Builder $q) => $q->where('course_id', (int) $filters['course_id']),
            );

            unset($filters['course_id']);
        }

        parent::applyFilters($query, $filters);
    }

    /**
     * @return Collection<int, CertificateUser>
     */
    public function forUser(int $userId): Collection
    {
        return $this->query()
            ->with(['certificate.course', 'team'])
            ->where('user_id', $userId)
            ->latest('id')
            ->get();
    }

    public function findValidByCode(string $code): ?CertificateUser
    {
        return $this->query()
            ->with(['certificate.course', 'user', 'team'])
            ->where('code', $code)
            ->whereNull('invalidated_at')
            ->first();
    }

    /**
     * Most recent certificate of the user for a course+team, regardless of
     * validity. Used to decide between "never issued" and "already revoked".
     */
    public function findForUserCourseTeam(int $userId, int $courseId, int $teamId): ?CertificateUser
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->whereHas('certificate', fn ($q) => $q->where('course_id', $courseId))
            ->latest('id')
            ->first();
    }

    /**
     * Current valid certificate of the user for a course+team. Optionally locks
     * the row so concurrent completions cannot issue two replacements.
     */
    public function findValidForUserCourseTeam(
        int $userId,
        int $courseId,
        int $teamId,
        bool $lock = false,
    ): ?CertificateUser {
        $query = $this->query()
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->whereNull('invalidated_at')
            ->whereHas('certificate', fn ($q) => $q->where('course_id', $courseId))
            ->latest('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Current valid certificates issued with a specific template.
     *
     * @return Collection<int, CertificateUser>
     */
    public function validForCertificate(int $certificateId, bool $lock = false): Collection
    {
        $query = $this->query()
            ->where('certificate_id', $certificateId)
            ->whereNull('invalidated_at')
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * Keys ("userId:teamId") that already have a valid certificate with the
     * given template, used to avoid breaking the active unique constraint.
     *
     * @return array<string, true>
     */
    public function validUserTeamKeys(int $certificateId): array
    {
        return $this->query()
            ->where('certificate_id', $certificateId)
            ->whereNull('invalidated_at')
            ->get(['user_id', 'team_id'])
            ->mapWithKeys(fn (CertificateUser $certificate) => [
                $certificate->user_id.':'.$certificate->team_id => true,
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function moveToCertificate(array $ids, int $certificateId): int
    {
        if ($ids === []) {
            return 0;
        }

        return $this->query()
            ->whereIn('id', $ids)
            ->update(['certificate_id' => $certificateId]);
    }

    /**
     * Invalidates every currently valid certificate for the enrollment with the
     * automatic replacement reason. The rows remain in the history.
     */
    public function invalidateForReplacement(int $userId, int $courseId, int $teamId): int
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->whereNull('invalidated_at')
            ->whereHas('certificate', fn ($q) => $q->where('course_id', $courseId))
            ->update([
                'invalidated_at' => now(),
                'invalidation_reason' => CertificateUser::INVALIDATION_REPLACED,
                'active_flag' => null,
            ]);
    }
}
