<?php

namespace App\Services;

use App\Enums\ActionCode;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\CertificateNotAvailableException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\Certificate;
use App\Models\CertificateUser;
use App\Models\User;
use App\Repositories\ActionRepository;
use App\Repositories\CertificateRepository;
use App\Repositories\CertificateUserRepository;
use App\Repositories\CourseCompletionRepository;
use App\Repositories\LessonRepository;
use App\Repositories\TimelineRepository;
use App\Support\CompanyContext;
use App\Support\ImageStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CertificateService
{
    public function __construct(
        private readonly CertificateRepository $certificates,
        private readonly CertificateUserRepository $certificateUsers,
        private readonly LessonRepository $lessons,
        private readonly TimelineRepository $timeline,
        private readonly ActionRepository $actions,
        private readonly CourseCompletionRepository $completions,
        private readonly NotificationService $notifications,
        private readonly DatalogService $audit,
        private readonly ImageStorage $images,
        private readonly CompanyContext $context,
    ) {}

    /**
     * Creates a certificate template, storing any uploaded images.
     */
    public function createTemplate(array $data, ?UploadedFile $background = null, ?UploadedFile $signature = null): Certificate
    {
        $companyId = (int) ($data['company_id'] ?? $this->context->id() ?? 0);
        $copyFrom = isset($data['copy_from']) ? (int) $data['copy_from'] : null;
        $source = $copyFrom ? $this->certificates->query()->find($copyFrom) : null;

        unset($data['copy_from']);

        if ($background) {
            $data['background'] = $this->images->store($background, 'certificates', $companyId);
        } elseif ($source) {
            $data['background'] = $this->images->copy($source->background, 'certificates', $companyId);
        }

        if ($signature) {
            $data['signature'] = $this->images->store($signature, 'certificates', $companyId);
        } elseif ($source) {
            $data['signature'] = $this->images->copy($source->signature, 'certificates', $companyId);
        }

        $certificate = $this->certificates->create($data);

        $this->audit->log('certificate', 'create', 'certificate', "Certificado criado: {$certificate->name}");

        return $certificate;
    }

    /**
     * Updates a certificate template, replacing any uploaded images.
     */
    public function updateTemplate(Certificate $certificate, array $data, ?UploadedFile $background = null, ?UploadedFile $signature = null): Certificate
    {
        if ($background) {
            $this->images->delete($certificate->background);
            $data['background'] = $this->images->store($background, 'certificates', (int) $certificate->company_id);
        }

        if ($signature) {
            $this->images->delete($certificate->signature);
            $data['signature'] = $this->images->store($signature, 'certificates', (int) $certificate->company_id);
        }

        $certificate = $this->certificates->update($certificate, $data);

        $this->audit->log('certificate', 'update', 'certificate', "Certificado atualizado: {$certificate->name}");

        return $certificate;
    }

    /**
     * Deletes a certificate template and its uploaded images.
     */
    public function deleteTemplate(Certificate $certificate): void
    {
        if ($certificate->certificateUsers()->exists()) {
            throw new BusinessRuleException('Não é possível excluir um certificado já emitido para alunos.');
        }

        $this->images->delete($certificate->background);
        $this->images->delete($certificate->signature);

        $this->certificates->delete($certificate);

        $this->audit->log('certificate', 'delete', 'certificate', "Certificado excluído: {$certificate->name}");
    }

    /**
     * Issues a certificate when the course is completed and the student has no
     * certificate yet. Never re-issues: an existing certificate (valid or
     * revoked) is always respected. Returns null when not eligible.
     */
    public function issueIfEligible(User $user, int $courseId, int $teamId): ?CertificateUser
    {
        $existing = $this->certificateUsers->findForUserCourseTeam($user->id, $courseId, $teamId);

        if ($existing) {
            return $existing->isInvalidated() ? null : $existing;
        }

        $certificate = $this->certificates->findForCourse($courseId);

        if (! $certificate) {
            return null;
        }

        if (! $this->isCompleted($user->id, $courseId, $teamId)) {
            return null;
        }

        return $this->issue($user, $courseId, $teamId, $certificate);
    }

    /**
     * Issues the first certificate to every already-completed student of a
     * course that has no certificate yet. Used when a template becomes
     * available so historical completions are certified retroactively.
     * Students whose certificate was revoked (manually) are never re-issued.
     */
    public function issuePendingForCourse(int $courseId, int $companyId): int
    {
        $certificate = $this->certificates->findForCourse($courseId);

        if (! $certificate) {
            return 0;
        }

        $issued = 0;

        foreach ($this->completions->forCourse($courseId, $companyId) as $completion) {
            $userId = (int) $completion->user_id;
            $teamId = (int) $completion->team_id;

            if ($this->certificateUsers->findForUserCourseTeam($userId, $courseId, $teamId)) {
                continue;
            }

            $this->certificateUsers->create([
                'code' => (string) Str::uuid(),
                'company_id' => $companyId,
                'user_id' => $userId,
                'team_id' => $teamId,
                'certificate_id' => $certificate->id,
                'lessons_count' => (int) $completion->required_lessons,
            ]);

            $issued++;
        }

        if ($issued > 0) {
            $this->audit->log(
                'certificate',
                'issue_pending',
                'course',
                "{$issued} certificado(s) emitido(s) retroativamente para o curso {$courseId}.",
            );
        }

        return $issued;
    }

    /**
     * True when the student completed the course (historical record) or the
     * current lesson counts already satisfy the requirement.
     */
    private function isCompleted(int $userId, int $courseId, int $teamId): bool
    {
        if ($this->completions->findForUserCourseTeam($userId, $courseId, $teamId) !== null) {
            return true;
        }

        [$completed, $total] = $this->completionCounts($userId, $courseId, $teamId);

        return $total > 0 && $completed >= $total;
    }

    private function issue(User $user, int $courseId, int $teamId, Certificate $certificate): CertificateUser
    {
        $completion = $this->completions->findForUserCourseTeam($user->id, $courseId, $teamId);
        $total = (int) ($completion?->required_lessons ?? $this->lessons->countPublishedByCourse($courseId));

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
