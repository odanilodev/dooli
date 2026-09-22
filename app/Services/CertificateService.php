<?php

namespace App\Services;

use App\Enums\ActionCode;
use App\Exceptions\CertificateNotAvailableException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\Certificate;
use App\Models\CertificateUser;
use App\Models\User;
use App\Repositories\ActionRepository;
use App\Repositories\CertificateRepository;
use App\Repositories\CertificateUserRepository;
use App\Repositories\LessonRepository;
use App\Repositories\TimelineRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class CertificateService
{
    public function __construct(
        private readonly CertificateRepository $certificates,
        private readonly CertificateUserRepository $certificateUsers,
        private readonly LessonRepository $lessons,
        private readonly TimelineRepository $timeline,
        private readonly ActionRepository $actions,
        private readonly NotificationService $notifications,
        private readonly DatalogService $audit,
    ) {}

    /**
     * Issues a certificate when all required lessons are completed.
     * Returns null when the student is not eligible yet.
     */
    public function issueIfEligible(User $user, int $courseId, int $teamId): ?CertificateUser
    {
        $existing = $this->certificateUsers->findForUserCourseTeam($user->id, $courseId, $teamId);

        if ($existing && ! $existing->isInvalidated()) {
            return $existing;
        }

        $certificate = $this->certificates->findForCourse($courseId);

        if (! $certificate) {
            return null;
        }

        [$completed, $total] = $this->completionCounts($user->id, $courseId, $teamId);

        if ($total === 0 || $completed < $total) {
            return null;
        }

        $issued = $this->certificateUsers->create([
            'code' => (string) Str::uuid(),
            'user_id' => $user->id,
            'team_id' => $teamId,
            'certificate_id' => $certificate->id,
            'lessons_count' => $total,
        ]);

        $this->audit->log('certificate', 'issue', 'certificate_user', "Certificado emitido para user {$user->id}", $user->id);

        return $issued;
    }

    /**
     * Explicit generation endpoint. Fails when criteria are not met.
     */
    public function generate(User $user, int $courseId, int $teamId): CertificateUser
    {
        $issued = $this->issueIfEligible($user, $courseId, $teamId);

        if (! $issued) {
            throw new CertificateNotAvailableException;
        }

        return $issued->load(['certificate.course', 'team']);
    }

    /**
     * @return Collection<int, CertificateUser>
     */
    public function listForUser(User $user): Collection
    {
        return $this->certificateUsers->forUser($user->id);
    }

    /**
     * Invalidates an issued certificate (admin action).
     */
    public function revoke(CertificateUser $certificateUser, ?string $reason = null): CertificateUser
    {
        $revoked = $this->certificateUsers->update($certificateUser, [
            'invalidated_at' => now(),
            'invalidation_reason' => $reason,
        ]);

        $this->audit->log(
            'certificate',
            'revoke',
            'certificate_user',
            "Certificado invalidado: {$certificateUser->id}",
            $certificateUser->user_id,
        );

        return $revoked->load(['certificate.course', 'user', 'team']);
    }

    public function verify(string $code): CertificateUser
    {
        $certificate = $this->certificateUsers->findValidByCode($code);

        if (! $certificate) {
            throw new ResourceNotFoundException('Certificado não encontrado ou inválido.');
        }

        return $certificate;
    }

    /**
     * @return array{0: int, 1: int} [completed, total]
     */
    public function completionCounts(int $userId, int $courseId, int $teamId): array
    {
        $total = $this->lessons->countPublishedByCourse($courseId);

        $completedAction = $this->actions->findByCode(ActionCode::Completed->value);

        $completed = $completedAction
            ? count($this->timeline->completedLessonIds($userId, $courseId, $teamId, $completedAction->id))
            : 0;

        return [$completed, $total];
    }
}
