<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BackfillCourseCompletions extends Command
{
    protected $signature = 'completions:backfill
        {--dry-run : List what would be created without changing anything}
        {--report-manual : Also list manually revoked certificates that are not backfilled}';

    protected $description = 'Create course completions from existing certificates (valid and wrongly invalidated), reporting manual revocations.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = $this->validCertificates()
            ->concat($this->automaticallyInvalidatedCertificates())
            ->unique(fn ($row) => $row->user_id.':'.$row->team_id.':'.$row->course_id);

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $exists = DB::table('course_completions')
                ->where('user_id', $row->user_id)
                ->where('team_id', $row->team_id)
                ->where('course_id', $row->course_id)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $this->line(($dryRun ? '[dry-run] ' : '')."[completion] user {$row->user_id}, curso {$row->course_id}, team {$row->team_id}, required {$row->lessons_count}.");

            if ($dryRun) {
                $created++;

                continue;
            }

            DB::table('course_completions')->insert([
                'company_id' => $row->company_id,
                'user_id' => $row->user_id,
                'team_id' => $row->team_id,
                'course_id' => $row->course_id,
                'required_lessons' => $row->lessons_count,
                'lesson_ids' => json_encode($this->completedLessonIds($row)),
                'completed_at' => $row->created_at,
                'created_at' => now(),
            ]);

            $created++;
        }

        $this->info(sprintf(
            '%s%d conclusão(ões) %s, %d já existente(s).',
            $dryRun ? '[dry-run] ' : '',
            $created,
            $dryRun ? 'a criar' : 'criada(s)',
            $skipped,
        ));

        if ($this->option('report-manual')) {
            $this->reportManualRevocations();
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, object>
     */
    private function validCertificates()
    {
        return $this->baseQuery()
            ->whereNull('certificate_users.invalidated_at')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function automaticallyInvalidatedCertificates()
    {
        return $this->baseQuery()
            ->whereNotNull('certificate_users.invalidated_at')
            ->where('certificate_users.invalidation_reason', RestoreRecalculatedCertificates::AUTOMATIC_REASON)
            ->get();
    }

    private function baseQuery()
    {
        return DB::table('certificate_users')
            ->join('certificates', 'certificates.id', '=', 'certificate_users.certificate_id')
            ->select(
                'certificate_users.id as certificate_user_id',
                'certificate_users.company_id',
                'certificate_users.user_id',
                'certificate_users.team_id',
                'certificate_users.lessons_count',
                'certificate_users.invalidation_reason',
                'certificate_users.created_at',
                'certificates.course_id',
            );
    }

    /**
     * Best-effort snapshot of the lessons the student completed for the course.
     *
     * @return array<int, int>
     */
    private function completedLessonIds(object $row): array
    {
        return DB::table('timelines')
            ->join('actions', 'actions.id', '=', 'timelines.action_id')
            ->where('actions.code', 'completed')
            ->where('timelines.user_id', $row->user_id)
            ->where('timelines.team_id', $row->team_id)
            ->where('timelines.course_id', $row->course_id)
            ->distinct()
            ->pluck('timelines.lesson_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function reportManualRevocations(): void
    {
        $manual = $this->baseQuery()
            ->whereNotNull('certificate_users.invalidated_at')
            ->where(fn ($q) => $q
                ->whereNull('certificate_users.invalidation_reason')
                ->orWhere('certificate_users.invalidation_reason', '!=', RestoreRecalculatedCertificates::AUTOMATIC_REASON))
            ->orderBy('certificate_users.id')
            ->get();

        if ($manual->isEmpty()) {
            $this->info('Nenhum certificado revogado manualmente encontrado.');

            return;
        }

        $this->newLine();
        $this->warn("{$manual->count()} certificado(s) revogado(s) manualmente (NÃO backfillados — revisar manualmente):");

        foreach ($manual as $row) {
            $this->line(" - user {$row->user_id}, curso {$row->course_id}, team {$row->team_id} | motivo: ".($row->invalidation_reason ?? '(sem motivo)'));
        }
    }
}
