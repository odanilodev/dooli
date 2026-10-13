<?php

namespace App\Console\Commands;

use App\Enums\ActionCode;
use App\Enums\LessonProgressStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class RebuildLessonProgress extends Command
{
    protected $signature = 'progress:rebuild
        {--company= : Rebuild only for a company id}
        {--user= : Rebuild only for a user id}
        {--team= : Rebuild only for a team id}
        {--course= : Rebuild only for a course id}
        {--lesson= : Rebuild only for a lesson id}
        {--fresh : Delete the affected snapshot rows before rebuilding}';

    protected $description = 'Rebuild the student_lesson_progress snapshot from timelines and video progress.';

    public function handle(): int
    {
        $companyIds = DB::table('companies')
            ->when($this->option('company'), fn ($q, $id) => $q->where('id', (int) $id))
            ->pluck('id');

        foreach ($companyIds as $companyId) {
            $count = $this->rebuildForCompany((int) $companyId);
            $this->info("Company {$companyId}: {$count} row(s).");
        }

        return self::SUCCESS;
    }

    private function rebuildForCompany(int $companyId): int
    {
        $targets = $this->targets();

        if ($this->option('fresh') || $targets !== []) {
            $this->deleteSnapshotRows($companyId, $targets);
        }

        $actions = DB::table('actions')
            ->where('company_id', $companyId)
            ->whereIn('code', [ActionCode::Completed->value, ActionCode::Started->value])
            ->pluck('id', 'code');

        $keys = ['user_id', 'team_id', 'course_id', 'lesson_id'];
        $rows = [];

        if ($actions->has(ActionCode::Completed->value)) {
            $completed = $this->applyTargets(
                DB::table('timelines')
                    ->where('company_id', $companyId)
                    ->where('action_id', $actions[ActionCode::Completed->value]),
                $targets,
            )
                ->groupBy($keys)
                ->select($keys)
                ->selectRaw('MAX(module_id) as module_id')
                ->selectRaw('MIN(created_at) as started_at')
                ->selectRaw('MAX(created_at) as completed_at')
                ->get();

            foreach ($completed as $row) {
                $rows[$this->key($row)] = [
                    'company_id' => $companyId,
                    'user_id' => $row->user_id,
                    'team_id' => $row->team_id,
                    'course_id' => $row->course_id,
                    'lesson_id' => $row->lesson_id,
                    'module_id' => $row->module_id,
                    'status' => LessonProgressStatus::Completed->value,
                    'started_at' => $row->started_at,
                    'completed_at' => $row->completed_at,
                    'position_seconds' => 0,
                    'watched_seconds' => 0,
                    'last_activity_at' => $row->completed_at,
                ];
            }
        }

        if ($actions->has(ActionCode::Started->value)) {
            $started = $this->applyTargets(
                DB::table('timelines')
                    ->where('company_id', $companyId)
                    ->where('action_id', $actions[ActionCode::Started->value]),
                $targets,
            )
                ->groupBy($keys)
                ->select($keys)
                ->selectRaw('MAX(module_id) as module_id')
                ->selectRaw('MIN(created_at) as started_at')
                ->selectRaw('MAX(created_at) as last_activity_at')
                ->get();

            foreach ($started as $row) {
                $key = $this->key($row);

                if (isset($rows[$key])) {
                    $rows[$key]['started_at'] = $this->earliest($rows[$key]['started_at'], $row->started_at);
                    $rows[$key]['last_activity_at'] = $this->latest($rows[$key]['last_activity_at'], $row->last_activity_at);

                    continue;
                }

                $rows[$key] = [
                    'company_id' => $companyId,
                    'user_id' => $row->user_id,
                    'team_id' => $row->team_id,
                    'course_id' => $row->course_id,
                    'lesson_id' => $row->lesson_id,
                    'module_id' => $row->module_id,
                    'status' => LessonProgressStatus::Started->value,
                    'started_at' => $row->started_at,
                    'completed_at' => null,
                    'position_seconds' => 0,
                    'watched_seconds' => 0,
                    'last_activity_at' => $row->last_activity_at,
                ];
            }
        }

        $watched = $this->applyTargets(
            DB::table('video_progresses')->where('company_id', $companyId),
            $targets,
        )
            ->groupBy($keys)
            ->select($keys)
            ->selectRaw('MAX(position_seconds) as position_seconds')
            ->selectRaw('MIN(created_at) as first_seen_at')
            ->selectRaw('MAX(updated_at) as last_activity_at')
            ->get();

        $lessonModules = DB::table('lessons')->where('company_id', $companyId)->pluck('module_id', 'id');

        foreach ($watched as $row) {
            $key = $this->key($row);
            $moduleId = $lessonModules[$row->lesson_id] ?? null;

            if (! $moduleId) {
                continue;
            }

            if (isset($rows[$key])) {
                $rows[$key]['position_seconds'] = max($rows[$key]['position_seconds'], (int) $row->position_seconds);
                $rows[$key]['watched_seconds'] = $rows[$key]['position_seconds'];
                $rows[$key]['started_at'] = $this->earliest($rows[$key]['started_at'], $row->first_seen_at);
                $rows[$key]['last_activity_at'] = $this->latest($rows[$key]['last_activity_at'], $row->last_activity_at);

                continue;
            }

            $rows[$key] = [
                'company_id' => $companyId,
                'user_id' => $row->user_id,
                'team_id' => $row->team_id,
                'course_id' => $row->course_id,
                'lesson_id' => $row->lesson_id,
                'module_id' => $moduleId,
                'status' => LessonProgressStatus::Started->value,
                'started_at' => $row->first_seen_at,
                'completed_at' => null,
                'position_seconds' => (int) $row->position_seconds,
                'watched_seconds' => (int) $row->position_seconds,
                'last_activity_at' => $row->last_activity_at,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        $now = now()->toDateTimeString();

        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            $chunk = array_map(fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now], $chunk);

            DB::table('student_lesson_progress')->upsert(
                $chunk,
                ['user_id', 'team_id', 'course_id', 'lesson_id'],
                ['module_id', 'status', 'started_at', 'completed_at', 'position_seconds', 'watched_seconds', 'last_activity_at', 'updated_at'],
            );
        }

        return count($rows);
    }

    /**
     * @return array<string, int>
     */
    private function targets(): array
    {
        $targets = [];

        foreach (['user' => 'user_id', 'team' => 'team_id', 'course' => 'course_id', 'lesson' => 'lesson_id'] as $option => $column) {
            if ($this->option($option) !== null) {
                $targets[$column] = (int) $this->option($option);
            }
        }

        return $targets;
    }

    /**
     * @param  array<string, int>  $targets
     */
    private function deleteSnapshotRows(int $companyId, array $targets): void
    {
        DB::table('student_lesson_progress')
            ->where('company_id', $companyId)
            ->where($targets)
            ->delete();
    }

    /**
     * @param  array<string, int>  $targets
     */
    private function applyTargets(Builder $query, array $targets): Builder
    {
        return $targets === [] ? $query : $query->where($targets);
    }

    private function key(object $row): string
    {
        return $row->user_id.':'.$row->team_id.':'.$row->course_id.':'.$row->lesson_id;
    }

    private function earliest(?string $a, ?string $b): ?string
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return $a < $b ? $a : $b;
    }

    private function latest(?string $a, ?string $b): ?string
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return $a > $b ? $a : $b;
    }
}
