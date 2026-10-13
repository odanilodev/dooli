<?php

namespace App\Console\Commands;

use App\Enums\ActionCode;
use App\Enums\LessonProgressStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class VerifyLessonProgress extends Command
{
    protected $signature = 'progress:verify {--company= : Verify only a company id}';

    protected $description = 'Report drift between the student_lesson_progress snapshot and the timeline/video source of truth.';

    public function handle(): int
    {
        $companyIds = DB::table('companies')
            ->when($this->option('company'), fn ($q, $id) => $q->where('id', (int) $id))
            ->pluck('id');

        $total = 0;

        foreach ($companyIds as $companyId) {
            $companyId = (int) $companyId;

            $stats = [
                'missing_completed' => $this->missingCompleted($companyId),
                'missing_started' => $this->missingStarted($companyId),
                'extra_completed' => $this->extraCompleted($companyId),
                'orphan_snapshot' => $this->orphanSnapshot($companyId),
            ];

            $drift = array_sum($stats);
            $total += $drift;

            $this->line(sprintf(
                'Company %d: missing_completed=%d missing_started=%d extra_completed=%d orphan_snapshot=%d',
                $companyId,
                $stats['missing_completed'],
                $stats['missing_started'],
                $stats['extra_completed'],
                $stats['orphan_snapshot'],
            ));
        }

        if ($total > 0) {
            $this->warn("Drift detected ({$total}). Run: php artisan progress:rebuild --company=<id> [--fresh]");

            return self::FAILURE;
        }

        $this->info('Snapshot is consistent with timelines and video progress.');

        return self::SUCCESS;
    }

    private function missingCompleted(int $companyId): int
    {
        $actionId = $this->actionId($companyId, ActionCode::Completed->value);

        if ($actionId === null) {
            return 0;
        }

        $sub = DB::table('timelines as t')
            ->where('t.company_id', $companyId)
            ->where('t.action_id', $actionId)
            ->whereNotExists(fn (Builder $q) => $this->snapshotExists($q, 't')
                ->where('s.status', LessonProgressStatus::Completed->value))
            ->distinct()
            ->select($this->keyColumns('t'));

        return DB::query()->fromSub($sub, 'drift')->count();
    }

    private function missingStarted(int $companyId): int
    {
        $actionId = $this->actionId($companyId, ActionCode::Started->value);

        if ($actionId === null) {
            return 0;
        }

        $started = DB::table('timelines as t')
            ->where('t.company_id', $companyId)
            ->where('t.action_id', $actionId)
            ->whereNotExists(fn (Builder $q) => $this->snapshotExists($q, 't'))
            ->distinct()
            ->select($this->keyColumns('t'));

        $watched = DB::table('video_progresses as v')
            ->where('v.company_id', $companyId)
            ->whereNotExists(fn (Builder $q) => $this->snapshotExists($q, 'v'))
            ->distinct()
            ->select($this->keyColumns('v'));

        return DB::query()->fromSub($started, 'drift')->count()
            + DB::query()->fromSub($watched, 'drift')->count();
    }

    private function extraCompleted(int $companyId): int
    {
        $actionId = $this->actionId($companyId, ActionCode::Completed->value);

        $sub = DB::table('student_lesson_progress as s')
            ->where('s.company_id', $companyId)
            ->where('s.status', LessonProgressStatus::Completed->value)
            ->whereNotExists(fn (Builder $q) => $this->timelineExists($q, 's', $actionId))
            ->distinct()
            ->select($this->keyColumns('s'));

        return DB::query()->fromSub($sub, 'drift')->count();
    }

    private function orphanSnapshot(int $companyId): int
    {
        $sub = DB::table('student_lesson_progress as s')
            ->where('s.company_id', $companyId)
            ->whereNotExists(fn (Builder $q) => $this->timelineExists($q, 's'))
            ->whereNotExists(fn (Builder $q) => $this->videoExists($q, 's'))
            ->distinct()
            ->select($this->keyColumns('s'));

        return DB::query()->fromSub($sub, 'drift')->count();
    }

    private function snapshotExists(Builder $query, string $left): Builder
    {
        return $query->select(DB::raw(1))->from('student_lesson_progress as s')
            ->whereColumn('s.user_id', "{$left}.user_id")
            ->whereColumn('s.team_id', "{$left}.team_id")
            ->whereColumn('s.course_id', "{$left}.course_id")
            ->whereColumn('s.lesson_id', "{$left}.lesson_id")
            ->whereColumn('s.company_id', "{$left}.company_id");
    }

    private function timelineExists(Builder $query, string $left, ?int $actionId = null): Builder
    {
        $query->select(DB::raw(1))->from('timelines as t')
            ->whereColumn('t.user_id', "{$left}.user_id")
            ->whereColumn('t.team_id', "{$left}.team_id")
            ->whereColumn('t.course_id', "{$left}.course_id")
            ->whereColumn('t.lesson_id', "{$left}.lesson_id")
            ->whereColumn('t.company_id', "{$left}.company_id");

        return $actionId === null ? $query : $query->where('t.action_id', $actionId);
    }

    private function videoExists(Builder $query, string $left): Builder
    {
        return $query->select(DB::raw(1))->from('video_progresses as v')
            ->whereColumn('v.user_id', "{$left}.user_id")
            ->whereColumn('v.team_id', "{$left}.team_id")
            ->whereColumn('v.course_id', "{$left}.course_id")
            ->whereColumn('v.lesson_id', "{$left}.lesson_id")
            ->whereColumn('v.company_id', "{$left}.company_id");
    }

    private function actionId(int $companyId, string $code): ?int
    {
        $id = DB::table('actions')->where('company_id', $companyId)->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return array<int, string>
     */
    private function keyColumns(string $prefix): array
    {
        return [
            "{$prefix}.user_id",
            "{$prefix}.team_id",
            "{$prefix}.course_id",
            "{$prefix}.lesson_id",
        ];
    }
}
