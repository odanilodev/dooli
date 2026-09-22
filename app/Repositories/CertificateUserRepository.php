<?php

namespace App\Repositories;

use App\Models\CertificateUser;
use Illuminate\Database\Eloquent\Collection;

class CertificateUserRepository extends BaseRepository
{
    protected array $filterable = ['company_id', 'user_id', 'team_id', 'certificate_id'];

    public function __construct()
    {
        parent::__construct(new CertificateUser);
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
     * Completed certificates that may be affected by new lessons.
     *
     * @return Collection<int, CertificateUser>
     */
    public function completedForCourse(int $courseId): Collection
    {
        return $this->query()
            ->with(['user', 'team', 'certificate'])
            ->whereHas('certificate', fn ($q) => $q->where('course_id', $courseId))
            ->whereNull('invalidated_at')
            ->get();
    }
}
