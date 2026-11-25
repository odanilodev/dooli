<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Enums\ScheduleLevel;
use App\Exceptions\BusinessRuleException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Schedule;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    /**
     * Scheduled release dates for a team, keyed by level and target id.
     *
     * @return array{course: array<int, Carbon>, module: array<int, Carbon>, lesson: array<int, Carbon>}
     */
    public function forTeam(Team $team): array
    {
        $map = $this->emptyMap();

        Schedule::query()
            ->where('team_id', $team->id)
            ->get()
            ->each(function (Schedule $schedule) use (&$map) {
                $this->addToMap($map, $schedule);
            });

        return $map;
    }

    /**
     * @return array{course: array, module: array, lesson: array, expires: array{course: array, module: array, lesson: array}}
     */
    public function emptyMap(): array
    {
        return [
            'course' => [], 'module' => [], 'lesson' => [],
            'expires' => ['course' => [], 'module' => [], 'lesson' => []],
        ];
    }

    public function addToMap(array &$map, Schedule $schedule): void
    {
        $level = $schedule->schedulable_type;

        if (! in_array($level, ['course', 'module', 'lesson'], true) || $schedule->available_at === null) {
            return;
        }

        $map[$level][(int) $schedule->schedulable_id] = $schedule->available_at;

        if ($schedule->expires_at !== null) {
            $map['expires'][$level][(int) $schedule->schedulable_id] = $schedule->expires_at;
        }
    }

    /**
     * Earliest expiration among the course, module and lesson schedules: once
     * any of them expires, the content is closed.
     */
    public function expiresAt(array $map, int $courseId, ?int $moduleId, ?int $lessonId): ?Carbon
    {
        $dates = array_filter([
            $map['expires']['course'][$courseId] ?? null,
            $moduleId ? ($map['expires']['module'][$moduleId] ?? null) : null,
            $lessonId ? ($map['expires']['lesson'][$lessonId] ?? null) : null,
        ]);

        if ($dates === []) {
            return null;
        }

        return collect($dates)->reduce(
            fn (?Carbon $carry, Carbon $date) => $carry === null || $date->lessThan($carry) ? $date : $carry,
        );
    }

    public function expired(?Carbon $expiresAt): bool
    {
        return $expiresAt !== null && $expiresAt->isPast();
    }

    /**
     * Not yet released or already expired.
     */
    public function isLocked(array $map, int $courseId, ?int $moduleId, ?int $lessonId): bool
    {
        return $this->locked($this->effectiveAt($map, $courseId, $moduleId, $lessonId))
            || $this->expired($this->expiresAt($map, $courseId, $moduleId, $lessonId));
    }

    /**
     * Most restrictive date among the course, module and lesson schedules. The
     * content is only released when every applicable date has passed.
     *
     * @param  array{course: array<int, Carbon>, module: array<int, Carbon>, lesson: array<int, Carbon>}  $map
     */
    public function effectiveAt(array $map, int $courseId, ?int $moduleId, ?int $lessonId): ?Carbon
    {
        $dates = array_filter([
            $map['course'][$courseId] ?? null,
            $moduleId ? ($map['module'][$moduleId] ?? null) : null,
            $lessonId ? ($map['lesson'][$lessonId] ?? null) : null,
        ]);

        if ($dates === []) {
            return null;
        }

        return collect($dates)->reduce(
            fn (?Carbon $carry, Carbon $date) => $carry === null || $date->greaterThan($carry) ? $date : $carry,
        );
    }

    /**
     * Most restrictive release date for a single lesson, fetching only the
     * three relevant rows (course, module and lesson) instead of every
     * schedule of the team.
     */
    public function effectiveForLesson(
        int $teamId,
        int $courseId,
        ?int $moduleId,
        ?int $lessonId,
        array $dripDates = [],
    ): ?Carbon {
        $map = $this->lessonMap($teamId, $courseId, $moduleId, $lessonId);

        return $this->effectiveAt($this->withDrip($map, $dripDates), $courseId, $moduleId, $lessonId);
    }

    public function expiresForLesson(int $teamId, int $courseId, ?int $moduleId, ?int $lessonId): ?Carbon
    {
        return $this->expiresAt($this->lessonMap($teamId, $courseId, $moduleId, $lessonId), $courseId, $moduleId, $lessonId);
    }

    private function lessonMap(int $teamId, int $courseId, ?int $moduleId, ?int $lessonId): array
    {
        $targets = [
            ScheduleLevel::Course->value => $courseId,
            ScheduleLevel::Module->value => $moduleId,
            ScheduleLevel::Lesson->value => $lessonId,
        ];

        $rows = Schedule::query()
            ->where('team_id', $teamId)
            ->where(function ($query) use ($targets) {
                foreach ($targets as $type => $id) {
                    if ($id) {
                        $query->orWhere(fn ($w) => $w->where('schedulable_type', $type)->where('schedulable_id', $id));
                    }
                }
            })
            ->get();

        $map = $this->emptyMap();

        foreach ($rows as $row) {
            $this->addToMap($map, $row);
        }

        return $map;
    }

    /**
     * Soma a liberação gradual (por aluno) às datas dos módulos: vale a mais restritiva.
     *
     * @param  array{course: array<int, Carbon>, module: array<int, Carbon>, lesson: array<int, Carbon>}  $map
     * @param  array<int, Carbon>  $dripDates
     * @return array{course: array<int, Carbon>, module: array<int, Carbon>, lesson: array<int, Carbon>}
     */
    private function withDrip(array $map, array $dripDates): array
    {
        foreach ($dripDates as $moduleId => $date) {
            $current = $map['module'][$moduleId] ?? null;

            if ($current === null || $date->greaterThan($current)) {
                $map['module'][$moduleId] = $date;
            }
        }

        return $map;
    }

    public function locked(?Carbon $availableAt): bool
    {
        return $availableAt !== null && $availableAt->isFuture();
    }

    public function assertLessonAvailable(?Carbon $availableAt): void
    {
        if ($this->locked($availableAt)) {
            throw new BusinessRuleException(
                'Aula disponível a partir de '.$availableAt->format('d/m/Y H:i').'.',
            );
        }
    }

    public function assertLessonNotExpired(?Carbon $expiresAt): void
    {
        if ($this->expired($expiresAt)) {
            throw new BusinessRuleException(
                'O acesso a esta aula expirou em '.$expiresAt->format('d/m/Y H:i').'.',
            );
        }
    }

    public function set(Team $team, ScheduleLevel $level, int $id, ?string $availableAt, ?int $durationDays = null): void
    {
        $this->assertTargetBelongsToTeam($team, $level, $id);

        $query = Schedule::query()
            ->where('team_id', $team->id)
            ->where('schedulable_type', $level->value)
            ->where('schedulable_id', $id);

        if ($availableAt === null || $availableAt === '') {
            $query->delete();

            return;
        }

        $schedule = $query->first();

        $expiresAt = $durationDays ? Carbon::parse($availableAt)->addDays($durationDays) : null;

        if ($schedule) {
            $schedule->update([
                'available_at' => $availableAt,
                'duration_days' => $durationDays,
                'expires_at' => $expiresAt,
            ]);

            return;
        }

        Schedule::query()->create([
            'team_id' => $team->id,
            'schedulable_type' => $level->value,
            'schedulable_id' => $id,
            'available_at' => $availableAt,
            'duration_days' => $durationDays,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Remove schedules whose target is no longer reachable through the team's
     * active courses (a detached course, or a module/lesson only reachable via
     * it). Prevents orphaned rows after detaching a course.
     */
    public function pruneUnreachable(Team $team): int
    {
        $courseIds = $team->courses()
            ->where('team_courses.status', GeneralStatus::Active->value)
            ->pluck('courses.id');

        $moduleIds = DB::table('course_modules')
            ->whereIn('course_id', $courseIds)
            ->pluck('module_id');

        $lessonIds = Lesson::query()
            ->whereIn('module_id', $moduleIds)
            ->pluck('id');

        return Schedule::query()
            ->where('team_id', $team->id)
            ->where(function ($query) use ($courseIds, $moduleIds, $lessonIds) {
                $query->where(function ($q) use ($courseIds) {
                    $q->where('schedulable_type', ScheduleLevel::Course->value)
                        ->whereNotIn('schedulable_id', $courseIds);
                })->orWhere(function ($q) use ($moduleIds) {
                    $q->where('schedulable_type', ScheduleLevel::Module->value)
                        ->whereNotIn('schedulable_id', $moduleIds);
                })->orWhere(function ($q) use ($lessonIds) {
                    $q->where('schedulable_type', ScheduleLevel::Lesson->value)
                        ->whereNotIn('schedulable_id', $lessonIds);
                });
            })
            ->delete();
    }

    private function assertTargetBelongsToTeam(Team $team, ScheduleLevel $level, int $id): void
    {
        $courseIds = $team->courses()->pluck('courses.id');

        $belongs = match ($level) {
            ScheduleLevel::Course => $courseIds->contains($id),
            ScheduleLevel::Module => Module::query()->whereKey($id)
                ->whereHas('courses', fn ($q) => $q->whereIn('courses.id', $courseIds))
                ->exists(),
            ScheduleLevel::Lesson => Lesson::query()->whereKey($id)
                ->whereHas('module.courses', fn ($q) => $q->whereIn('courses.id', $courseIds))
                ->exists(),
        };

        if (! $belongs) {
            throw new BusinessRuleException('O item agendado não pertence a esta turma.');
        }
    }

    /**
     * Course/module/lesson tree for the team with the raw schedule dates, used
     * by the admin scheduling tab.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(Team $team): array
    {
        $map = $this->forTeam($team);
        $durations = Schedule::query()->where('team_id', $team->id)->whereNotNull('duration_days')
            ->get()
            ->mapWithKeys(fn (Schedule $s) => [$s->schedulable_type.':'.$s->schedulable_id => $s->duration_days]);

        return $team->courses()
            ->where('team_courses.status', GeneralStatus::Active->value)
            ->with([
                'modules' => fn ($q) => $q->orderBy('course_modules.sort_order'),
                'modules.lessons',
            ])
            ->get()
            ->map(function (Course $course) use ($map, $durations) {
                return [
                    'type' => ScheduleLevel::Course->value,
                    'id' => $course->id,
                    'name' => $course->name,
                    'available_at' => ($map['course'][$course->id] ?? null)?->toIso8601String(),
                    'duration_days' => $durations->get('course:'.$course->id),
                    'expires_at' => ($map['expires']['course'][$course->id] ?? null)?->toIso8601String(),
                    'modules' => $course->modules->map(function (Module $module) use ($map, $durations) {
                        return [
                            'type' => ScheduleLevel::Module->value,
                            'id' => $module->id,
                            'name' => $module->name,
                            'available_at' => ($map['module'][$module->id] ?? null)?->toIso8601String(),
                            'duration_days' => $durations->get('module:'.$module->id),
                            'expires_at' => ($map['expires']['module'][$module->id] ?? null)?->toIso8601String(),
                            'lessons' => $module->lessons
                                ->map(fn (Lesson $lesson) => [
                                    'type' => ScheduleLevel::Lesson->value,
                                    'id' => $lesson->id,
                                    'name' => $lesson->name,
                                    'available_at' => ($map['lesson'][$lesson->id] ?? null)?->toIso8601String(),
                                    'duration_days' => $durations->get('lesson:'.$lesson->id),
                                    'expires_at' => ($map['expires']['lesson'][$lesson->id] ?? null)?->toIso8601String(),
                                ])
                                ->all(),
                        ];
                    })->all(),
                ];
            })
            ->all();
    }

    /**
     * Flags each course/module/lesson with its effective availability so the
     * student resources can expose what is still locked.
     */
    public function applyToCourseContent(Course $course, Team $team, array $dripDates = []): void
    {
        $map = $this->withDrip($this->forTeam($team), $dripDates);

        $courseAt = $map['course'][$course->id] ?? null;
        $courseExpires = $this->expiresAt($map, $course->id, null, null);
        $course->setAttribute('schedule_available_at', $courseAt);
        $course->setAttribute('schedule_expires_at', $courseExpires);
        $course->setAttribute('schedule_locked', $this->locked($courseAt) || $this->expired($courseExpires));

        foreach ($course->modules as $module) {
            $moduleAt = $this->effectiveAt($map, $course->id, $module->id, null);
            $moduleExpires = $this->expiresAt($map, $course->id, $module->id, null);
            $module->setAttribute('schedule_available_at', $moduleAt);
            $module->setAttribute('schedule_expires_at', $moduleExpires);
            $module->setAttribute('schedule_locked', $this->locked($moduleAt) || $this->expired($moduleExpires));

            foreach ($this->moduleLessons($module) as $lesson) {
                $lessonAt = $this->effectiveAt($map, $course->id, $module->id, $lesson->id);
                $lessonExpires = $this->expiresAt($map, $course->id, $module->id, $lesson->id);
                $lesson->setAttribute('schedule_available_at', $lessonAt);
                $lesson->setAttribute('schedule_expires_at', $lessonExpires);
                $lesson->setAttribute('schedule_locked', $this->locked($lessonAt) || $this->expired($lessonExpires));
            }
        }
    }

    /**
     * @return Collection<int, Lesson>
     */
    private function moduleLessons(Module $module): Collection
    {
        $loose = $module->relationLoaded('looseLessons') ? $module->looseLessons : collect();

        $insideTopics = $module->relationLoaded('topics')
            ? $module->topics->flatMap(fn ($topic) => $topic->relationLoaded('lessons') ? $topic->lessons : collect())
            : collect();

        return $loose->concat($insideTopics);
    }
}
