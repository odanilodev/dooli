<?php

namespace App\Services;

use App\Enums\NotificationSlug;
use App\Models\CertificateUser;
use App\Repositories\CertificateUserRepository;
use App\Repositories\LessonRepository;
use Illuminate\Support\Facades\DB;

/**
 * Business rule for course content changes (section 27).
 *
 * When new lessons are added to a course that already has students with
 * issued certificates, those certificates may no longer reflect the full
 * course completion. Affected students are detected, their certificates
 * invalidated (for reprocessing) and a notification is generated.
 */
class CourseCompletionService
{
    public function __construct(
        private readonly LessonRepository $lessons,
        private readonly CertificateUserRepository $certificateUsers,
        private readonly NotificationService $notifications,
        private readonly DatalogService $audit,
    ) {}

    /**
     * @param  array<int, int>  $courseIds
     * @return array<int, array<string, mixed>>
     */
    public function handleContentChange(array $courseIds): array
    {
        $results = [];

        foreach (array_unique($courseIds) as $courseId) {
            $results[] = $this->recalculateCourse((int) $courseId);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function recalculateCourse(int $courseId): array
    {
        $totalLessons = $this->lessons->countPublishedByCourse($courseId);

        $completed = $this->certificateUsers->completedForCourse($courseId);

        $affected = $completed->filter(fn (CertificateUser $certificate) => $certificate->lessons_count < $totalLessons);

        $affectedCount = 0;

        DB::transaction(function () use ($affected, &$affectedCount) {
            foreach ($affected as $certificate) {
                $certificate->forceFill([
                    'invalidated_at' => now(),
                    'invalidation_reason' => 'Novas aulas adicionadas ao curso após a conclusão.',
                ])->save();

                $this->notifications->createForTeam(
                    team: $certificate->team,
                    slug: NotificationSlug::CertificateRecalculationRequired,
                    userIds: [$certificate->user_id],
                );

                $affectedCount++;
            }
        });

        if ($affectedCount > 0) {
            $this->audit->log(
                'certificate',
                'recalculate',
                'course',
                "{$affectedCount} certificado(s) invalidado(s) por alteração no curso {$courseId} (total de aulas: {$totalLessons}).",
            );
        }

        return [
            'course_id' => $courseId,
            'total_lessons' => $totalLessons,
            'affected_certificates' => $affectedCount,
        ];
    }
}
