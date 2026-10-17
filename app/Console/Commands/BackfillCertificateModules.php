<?php

namespace App\Console\Commands;

use App\Enums\ActionCode;
use App\Repositories\ModuleRepository;
use App\Support\CompanyContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCertificateModules extends Command
{
    protected $signature = 'certificates:backfill-modules
        {--dry-run : List what would be updated without changing anything}';

    protected $description = 'Fill the course-module snapshot of certificates issued before the module grid existed.';

    public function handle(ModuleRepository $modules, CompanyContext $context): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;

        DB::table('certificate_users')
            ->join('certificates', 'certificates.id', '=', 'certificate_users.certificate_id')
            ->whereNull('certificate_users.modules')
            ->select([
                'certificate_users.id as certificate_user_id',
                'certificate_users.company_id',
                'certificate_users.user_id',
                'certificate_users.team_id',
                'certificates.course_id',
            ])
            ->orderBy('certificate_users.id')
            ->chunkById(200, function ($rows) use ($dryRun, $modules, $context, &$updated, &$skipped) {
                foreach ($rows as $row) {
                    $courseId = (int) $row->course_id;
                    $lessonIds = $this->completedLessonIds($row);

                    if ($courseId === 0) {
                        $skipped++;

                        continue;
                    }

                    $context->set((int) $row->company_id);

                    try {
                        $snapshot = $modules->snapshotForCourse($courseId, $lessonIds);
                    } finally {
                        $context->forget();
                    }

                    $this->line(sprintf(
                        '%s[certificado %d] user %d, curso %d, %d módulo(s).',
                        $dryRun ? '[dry-run] ' : '',
                        $row->certificate_user_id,
                        $row->user_id,
                        $courseId,
                        count($snapshot),
                    ));

                    if ($dryRun) {
                        $updated++;

                        continue;
                    }

                    DB::table('certificate_users')
                        ->where('id', $row->certificate_user_id)
                        ->update(['modules' => json_encode($snapshot)]);

                    $updated++;
                }
            }, 'certificate_users.id', 'certificate_user_id');

        $this->info(sprintf(
            '%s%d certificado(s) %s, %d ignorado(s).',
            $dryRun ? '[dry-run] ' : '',
            $updated,
            $dryRun ? 'a preencher' : 'preenchido(s)',
            $skipped,
        ));

        return self::SUCCESS;
    }

    /**
     * Lessons completed by the student for the certificate course. Uses the
     * historical completion snapshot and falls back to the timeline for legacy
     * certificates that predate course_completions.
     *
     * @return array<int, int>
     */
    private function completedLessonIds(object $row): array
    {
        $completion = DB::table('course_completions')
            ->where('user_id', $row->user_id)
            ->where('team_id', $row->team_id)
            ->where('course_id', $row->course_id)
            ->first(['lesson_ids']);

        $lessonIds = $completion?->lesson_ids
            ? json_decode($completion->lesson_ids, true)
            : [];

        if (is_array($lessonIds) && $lessonIds !== []) {
            return array_values(array_map('intval', $lessonIds));
        }

        return DB::table('timelines')
            ->join('actions', 'actions.id', '=', 'timelines.action_id')
            ->where('actions.code', ActionCode::Completed->value)
            ->where('timelines.user_id', $row->user_id)
            ->where('timelines.team_id', $row->team_id)
            ->where('timelines.course_id', $row->course_id)
            ->distinct()
            ->pluck('timelines.lesson_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
