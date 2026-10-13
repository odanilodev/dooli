<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Enums\LessonProgressStatus;
use App\Models\Schedule;
use App\Repositories\ReportRepository;
use App\Support\CompanyContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(
        private readonly ReportRepository $reports,
        private readonly ScheduleService $schedules,
        private readonly CompanyContext $context,
    ) {}

    /**
     * Drill one level of the progress report.
     *
     * @param  array<string, mixed>  $filters
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function progress(array $filters): array
    {
        $companyId = (int) $this->context->id();
        $groupBy = $filters['group_by'];
        $perPage = (int) $filters['per_page'];
        $status = $filters['student_status'] ?? null;
        $search = $filters['search'] ?? null;
        $userIds = $filters['userIds'] ?? [];

        $moduleId = $filters['module_id'] ?? null;
        $lessonId = $filters['lesson_id'] ?? null;

        $courseIds = $this->resolveCourseIds($companyId, $filters['course_id'] ?? null, $moduleId, $lessonId);
        $teamIds = $this->resolveTeamIds($companyId, $filters['team_id'] ?? null, $courseIds);

        $restrictToStudents = $userIds !== [];

        if ($restrictToStudents) {
            $teamIds = $this->teamsEnrolledBy($companyId, $userIds, $teamIds);
        }

        $coursesByTeam = $this->reports->coursesByTeam($companyId, $teamIds, $courseIds);

        if ($restrictToStudents) {
            $courseIds = $this->flattenCourseIds($coursesByTeam);

            if ($courseIds === []) {
                return $this->emptyResult($groupBy, $perPage);
            }
        }

        $scope = [
            'companyId' => $companyId,
            'teamIds' => $teamIds,
            'courseIds' => $courseIds,
            'moduleId' => $moduleId,
            'lessonId' => $lessonId,
            'studentStatus' => $status,
            'search' => $search,
            'userIds' => $userIds,
        ];

        $eligible = $this->reports->eligibleLessons($companyId, $courseIds, $moduleId, $lessonId);

        $courseLessons = [];
        $moduleLessons = [];
        $lessonCourses = [];

        foreach ($eligible as $row) {
            $courseLessons[(int) $row->course_id][(int) $row->lesson_id] = (int) $row->module_id;
            $moduleLessons[(int) $row->module_id][(int) $row->lesson_id] = true;
            $lessonCourses[(int) $row->lesson_id][(int) $row->course_id] = true;
        }

        $locked = $this->lockedByTeamCourse($teamIds, $courseLessons);

        $enrollments = $this->reports->enrollmentsByUser($companyId, $teamIds, $status, $search, $userIds);

        $reach = $this->buildReach($enrollments, $coursesByTeam, $courseLessons, $lessonCourses, $locked);

        $paginator = match ($groupBy) {
            'course' => $this->reports->paginateCourses($teamIds, $filters['course_id'] ?? null, $moduleId, $lessonId, $search, $perPage),
            'module' => $this->reports->paginateModules($courseIds, $moduleId, $lessonId, $search, $perPage),
            'lesson' => $this->reports->paginateLessons($courseIds, $moduleId, $lessonId, $search, $perPage),
            'student' => $this->reports->paginateStudents($teamIds, $status, $search, $perPage, $userIds),
            default => $this->reports->paginateTeams($filters['course_id'] ?? null, $search, $perPage, $restrictToStudents ? $teamIds : null),
        };

        $ids = collect($paginator->items())->pluck('id')->map(fn ($id) => (int) $id)->all();

        $counts = $this->reports->progressCounts($this->columnFor($groupBy), $ids, $scope);

        $rows = match ($groupBy) {
            'course' => $this->buildCourseRows($paginator->items(), $counts, $reach['course'], $courseLessons),
            'module' => $this->buildModuleRows($paginator->items(), $counts, $reach['module'], $moduleLessons),
            'lesson' => $this->buildLessonRows($paginator->items(), $counts, $reach['lesson']),
            'student' => $this->buildStudentRows($companyId, $paginator->items(), $counts, $teamIds, $coursesByTeam, $courseLessons, $locked),
            default => $this->buildTeamRows($paginator->items(), $counts, $reach['studentsByTeam'], $coursesByTeam, $courseLessons, $locked),
        };

        return [
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'group_by' => $groupBy,
                'totals' => $this->totals($scope, $reach, $coursesByTeam, $courseLessons),
            ],
        ];
    }

    /**
     * Detail of a single student's progress inside the current scope.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function student(int $userId, array $filters): array
    {
        $companyId = (int) $this->context->id();
        $courseIds = $this->resolveCourseIds($companyId, $filters['course_id'] ?? null, null, null);
        $teamIds = $this->resolveTeamIds($companyId, $filters['team_id'] ?? null, $courseIds);

        $scope = ['companyId' => $companyId, 'teamIds' => $teamIds, 'courseIds' => $courseIds];

        $user = DB::table('users')->where('id', $userId)->where('company_id', $companyId)
            ->first(['id', 'name', 'email', 'status']);

        abort_if($user === null, 404);

        $teamsByUser = $this->reports->teamsByUser($companyId, [$userId], $teamIds);
        $teamIdsOfUser = $teamsByUser[$userId] ?? [];
        $coursesByTeam = $this->reports->coursesByTeam($companyId, $teamIdsOfUser, $courseIds);

        $relevantCourseIds = [];

        foreach ($coursesByTeam as $ids) {
            $relevantCourseIds = array_merge($relevantCourseIds, $ids);
        }

        $relevantCourseIds = array_values(array_unique($relevantCourseIds));
        $eligible = $this->reports->eligibleLessons($companyId, $relevantCourseIds, null, null);

        $courseLessons = [];
        $lessonMeta = [];

        foreach ($eligible as $row) {
            $courseLessons[(int) $row->course_id][(int) $row->lesson_id] = (int) $row->module_id;
            $lessonMeta[(int) $row->lesson_id] = [
                'name' => $row->lesson_name,
                'module' => $row->module_name,
            ];
        }

        $locked = $this->lockedByTeamCourse($teamIdsOfUser, $courseLessons);
        $snapshot = $this->reports->studentLessonStatuses($userId, $scope)->keyBy(
            fn ($row) => $row->course_id.':'.$row->lesson_id,
        );

        $courseNames = $this->reports->courseNames($relevantCourseIds);
        $teamNames = $this->reports->teamNames($teamIdsOfUser);

        $items = [];

        foreach ($teamIdsOfUser as $teamId) {
            foreach ($coursesByTeam[$teamId] ?? [] as $courseId) {
                $lessons = $courseLessons[$courseId] ?? [];
                $total = count($lessons);
                $completed = 0;
                $inProgress = 0;
                $watched = 0;
                $lastActivity = null;
                $lessonRows = [];

                foreach ($lessons as $lessonId => $moduleId) {
                    $row = $snapshot->get($courseId.':'.$lessonId);
                    $status = $row?->status === LessonProgressStatus::Completed->value
                        ? 'completed'
                        : ($row !== null ? 'in_progress' : 'pending');
                    $completed += $status === 'completed' ? 1 : 0;
                    $inProgress += $status === 'in_progress' ? 1 : 0;
                    $watched += (int) ($row?->watched_seconds ?? 0);
                    $lastActivity = $this->latestDate($lastActivity, $row?->last_activity_at);

                    $lessonRows[] = [
                        'id' => $lessonId,
                        'name' => $lessonMeta[$lessonId]['name'] ?? null,
                        'module' => $lessonMeta[$lessonId]['module'] ?? null,
                        'status' => $status,
                        'blocked' => isset($locked[$teamId][$courseId][$lessonId]),
                        'watched_seconds' => (int) ($row?->watched_seconds ?? 0),
                        'last_activity_at' => $row?->last_activity_at,
                    ];
                }

                $blocked = isset($locked[$teamId][$courseId]) ? count($locked[$teamId][$courseId]) : 0;

                $items[] = [
                    'team_id' => $teamId,
                    'team_name' => $teamNames[$teamId] ?? null,
                    'course_id' => $courseId,
                    'course_name' => $courseNames[$courseId] ?? null,
                    'total_lessons' => $total,
                    'completed' => $completed,
                    'in_progress' => $inProgress,
                    'blocked' => $blocked,
                    'pending' => max(0, $total - $completed - $inProgress - $blocked),
                    'percentage' => $total > 0 ? round($completed / $total * 100, 2) : 0.0,
                    'watched_seconds' => $watched,
                    'last_activity_at' => $lastActivity,
                    'lessons' => $lessonRows,
                ];
            }
        }

        return [
            'user' => $user,
            'items' => $items,
        ];
    }

    /**
     * Detail of a single lesson: how each student is progressing on it.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function lesson(int $lessonId, array $filters): array
    {
        $companyId = (int) $this->context->id();

        $lesson = DB::table('lessons')
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->where('lessons.id', $lessonId)
            ->where('lessons.company_id', $companyId)
            ->first(['lessons.id', 'lessons.name', 'modules.name as module_name']);

        abort_if($lesson === null, 404);

        $courseIds = $this->reports->coursesForLesson($companyId, $lessonId);
        $teamIds = $this->resolveTeamIds($companyId, $filters['team_id'] ?? null, $courseIds);

        $scope = ['companyId' => $companyId, 'teamIds' => $teamIds, 'courseIds' => $courseIds, 'lessonId' => $lessonId];

        $students = $this->reports->studentsForLesson(
            $companyId,
            $lessonId,
            $teamIds,
            $filters['student_status'] ?? null,
            $filters['search'] ?? null,
            $filters['userIds'] ?? [],
        );

        $statuses = $this->reports->lessonStudentStatuses($lessonId, $scope)->keyBy('user_id');

        $locked = $this->lockedForLesson($teamIds, $courseIds, $lessonId);

        $rows = $students->map(function ($row) use ($statuses, $locked) {
            $snapshot = $statuses->get($row->user_id);
            $status = $snapshot === null
                ? 'pending'
                : ($snapshot->status === LessonProgressStatus::Completed->value ? 'completed' : 'in_progress');

            return [
                'user_id' => (int) $row->user_id,
                'name' => $row->name,
                'email' => $row->email,
                'status' => $status,
                'blocked' => $locked[(int) $row->team_id] ?? false,
                'watched_seconds' => (int) ($snapshot->watched_seconds ?? 0),
                'last_activity_at' => $snapshot->last_activity_at ?? null,
            ];
        })->values();

        return [
            'lesson' => $lesson,
            'students' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function activity(array $filters): array
    {
        $companyId = (int) $this->context->id();
        $courseIds = $this->resolveCourseIds($companyId, $filters['course_id'] ?? null, null, null);
        $teamIds = $this->resolveTeamIds($companyId, $filters['team_id'] ?? null, $courseIds);

        $scope = [
            'companyId' => $companyId,
            'teamIds' => $teamIds,
            'courseIds' => $courseIds,
            'userId' => $filters['user_id'] ?? null,
            'userIds' => $filters['userIds'] ?? [],
        ];

        return $this->reports->activity($scope, (int) ($filters['limit'] ?? 50))
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'user_name' => $row->user_name,
                'lesson_id' => (int) $row->lesson_id,
                'lesson_name' => $row->lesson_name,
                'action' => $row->action,
                'created_at' => $row->created_at,
            ])
            ->all();
    }

    /**
     * Flat rows for CSV export following the current drill level.
     *
     * @param  array<string, mixed>  $filters
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function export(array $filters): array
    {
        $filters['per_page'] = 100;
        $result = $this->progress($filters);

        $headers = ['Nome', 'Alunos', 'Aulas', 'Concluídas', 'Em andamento', 'Pendentes', 'Bloqueadas', 'Progresso (%)', 'Última atividade'];

        $rows = array_map(fn (array $row) => [
            $row['name'],
            $row['students'],
            $row['lessons'],
            $row['completed'],
            $row['in_progress'],
            $row['pending'],
            $row['blocked'],
            $row['percentage'],
            $row['last_activity_at'],
        ], $result['data']);

        return compact('headers', 'rows');
    }

    /**
     * @param  array<int, object>  $items
     * @param  Collection<string, object>  $counts
     * @param  array<int, int>  $studentsByTeam
     * @param  array<int, array<int, int>>  $coursesByTeam
     * @param  array<int, array<int, int>>  $courseLessons
     * @param  array<int, array<int, array<int, bool>>>  $locked
     * @return array<int, array<string, mixed>>
     */
    private function buildTeamRows(array $items, $counts, array $studentsByTeam, array $coursesByTeam, array $courseLessons, array $locked): array
    {
        return array_map(function ($team) use ($counts, $studentsByTeam, $coursesByTeam, $courseLessons, $locked) {
            $id = (int) $team->id;
            $lessons = [];
            $blockedSet = [];

            foreach ($coursesByTeam[$id] ?? [] as $courseId) {
                foreach (array_keys($courseLessons[$courseId] ?? []) as $lessonId) {
                    $lessons[$lessonId] = true;
                }

                foreach (array_keys($locked[$id][$courseId] ?? []) as $lessonId) {
                    $blockedSet[$lessonId] = true;
                }
            }

            $students = $studentsByTeam[$id] ?? 0;
            $total = count($lessons);

            return $this->row([
                'id' => $id,
                'name' => $team->name,
                'subtitle' => null,
                'students' => $students,
                'lessons' => $total,
                'pairs' => $students * $total,
                'blocked' => $students * count($blockedSet),
                'count' => $counts->get($id),
            ]);
        }, $items);
    }

    /**
     * @param  array<int, object>  $items
     * @param  Collection<string, object>  $counts
     * @param  array<int, array<string, int>>  $stats
     * @param  array<int, array<int, int>>  $courseLessons
     * @return array<int, array<string, mixed>>
     */
    private function buildCourseRows(array $items, $counts, array $stats, array $courseLessons): array
    {
        return array_map(function ($course) use ($counts, $stats, $courseLessons) {
            $id = (int) $course->id;
            $stat = $stats[$id] ?? [];

            return $this->row([
                'id' => $id,
                'name' => $course->name,
                'subtitle' => null,
                'students' => $stat['students'] ?? 0,
                'lessons' => count($courseLessons[$id] ?? []),
                'pairs' => $stat['pairs'] ?? 0,
                'blocked' => $stat['blocked'] ?? 0,
                'count' => $counts->get($id),
            ]);
        }, $items);
    }

    /**
     * @param  array<int, object>  $items
     * @param  Collection<string, object>  $counts
     * @param  array<int, array<string, int>>  $stats
     * @param  array<int, array<int, bool>>  $moduleLessons
     * @return array<int, array<string, mixed>>
     */
    private function buildModuleRows(array $items, $counts, array $stats, array $moduleLessons): array
    {
        return array_map(function ($module) use ($counts, $stats, $moduleLessons) {
            $id = (int) $module->id;
            $stat = $stats[$id] ?? [];

            return $this->row([
                'id' => $id,
                'name' => $module->name,
                'subtitle' => null,
                'students' => $stat['students'] ?? 0,
                'lessons' => count($moduleLessons[$id] ?? []),
                'pairs' => $stat['pairs'] ?? 0,
                'blocked' => $stat['blocked'] ?? 0,
                'count' => $counts->get($id),
            ]);
        }, $items);
    }

    /**
     * @param  array<int, object>  $items
     * @param  Collection<string, object>  $counts
     * @param  array<int, array<string, int>>  $stats
     * @return array<int, array<string, mixed>>
     */
    private function buildLessonRows(array $items, $counts, array $stats): array
    {
        return array_map(function ($lesson) use ($counts, $stats) {
            $id = (int) $lesson->id;
            $students = $stats[$id]['students'] ?? 0;

            return $this->row([
                'id' => $id,
                'name' => $lesson->name,
                'subtitle' => $lesson->module?->name,
                'students' => $students,
                'lessons' => 1,
                'pairs' => $students,
                'blocked' => $stats[$id]['blocked'] ?? 0,
                'count' => $counts->get($id),
            ]);
        }, $items);
    }

    /**
     * @param  array<int, object>  $items
     * @param  Collection<string, object>  $counts
     * @param  array<int, int>  $teamIds
     * @param  array<int, array<int, int>>  $coursesByTeam
     * @param  array<int, array<int, int>>  $courseLessons
     * @param  array<int, array<int, array<int, bool>>>  $locked
     * @return array<int, array<string, mixed>>
     */
    private function buildStudentRows(int $companyId, array $items, $counts, array $teamIds, array $coursesByTeam, array $courseLessons, array $locked): array
    {
        $userIds = array_map(fn ($user) => (int) $user->id, $items);
        $teamsByUser = $this->reports->teamsByUser($companyId, $userIds, $teamIds);

        return array_map(function ($user) use ($teamsByUser, $counts, $coursesByTeam, $courseLessons, $locked) {
            $id = (int) $user->id;
            $lessons = [];
            $blockedSet = [];

            foreach ($teamsByUser[$id] ?? [] as $teamId) {
                foreach ($coursesByTeam[$teamId] ?? [] as $courseId) {
                    foreach (array_keys($courseLessons[$courseId] ?? []) as $lessonId) {
                        $lessons[$lessonId] = true;
                    }

                    foreach (array_keys($locked[$teamId][$courseId] ?? []) as $lessonId) {
                        $blockedSet[$lessonId] = true;
                    }
                }
            }

            return $this->row([
                'id' => $id,
                'name' => $user->name,
                'subtitle' => $user->email,
                'students' => 1,
                'lessons' => count($lessons),
                'pairs' => count($lessons),
                'blocked' => count($blockedSet),
                'count' => $counts->get($id),
            ]);
        }, $items);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function row(array $row): array
    {
        $count = $row['count'];
        $completed = (int) ($count->completed ?? 0);
        $inProgress = (int) ($count->in_progress ?? 0);
        $pairs = (int) $row['pairs'];
        $blocked = (int) $row['blocked'];
        $pending = max(0, $pairs - $completed - $inProgress - $blocked);

        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'subtitle' => $row['subtitle'],
            'students' => (int) $row['students'],
            'lessons' => (int) $row['lessons'],
            'completed' => $completed,
            'in_progress' => $inProgress,
            'pending' => $pending,
            'blocked' => $blocked,
            'percentage' => $pairs > 0 ? round($completed / $pairs * 100, 2) : 0.0,
            'last_activity_at' => $count->last_activity_at ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $reach
     * @param  array<int, array<int, int>>  $coursesByTeam
     * @param  array<int, array<int, int>>  $courseLessons
     * @return array<string, mixed>
     */
    private function totals(array $scope, array $reach, array $coursesByTeam, array $courseLessons): array
    {
        $scopeSums = $this->reports->scopeTotals($scope);

        $lessons = [];

        foreach ($coursesByTeam as $courseIds) {
            foreach ($courseIds as $courseId) {
                foreach (array_keys($courseLessons[$courseId] ?? []) as $lessonId) {
                    $lessons[$lessonId] = true;
                }
            }
        }

        $completed = (int) ($scopeSums->completed ?? 0);
        $inProgress = (int) ($scopeSums->in_progress ?? 0);
        $pairs = (int) $reach['dataset']['pairs'];
        $blocked = (int) $reach['dataset']['blocked'];

        return [
            'students' => (int) $reach['dataset']['students'],
            'lessons' => count($lessons),
            'completed' => $completed,
            'in_progress' => $inProgress,
            'pending' => max(0, $pairs - $completed - $inProgress - $blocked),
            'blocked' => $blocked,
            'percentage' => $pairs > 0 ? round($completed / $pairs * 100, 2) : 0.0,
        ];
    }

    private function columnFor(string $groupBy): string
    {
        return match ($groupBy) {
            'course' => 'course_id',
            'module' => 'module_id',
            'lesson' => 'lesson_id',
            'student' => 'user_id',
            default => 'team_id',
        };
    }

    /**
     * @param  array<int, int>  $teamIds
     * @param  array<int, int>  $courseIds
     * @return array<int, int>
     */
    private function resolveCourseIds(int $companyId, ?int $courseId, ?int $moduleId, ?int $lessonId): array
    {
        if ($courseId) {
            return [$courseId];
        }

        if ($moduleId) {
            return DB::table('course_modules')
                ->where('company_id', $companyId)
                ->where('module_id', $moduleId)
                ->where('status', GeneralStatus::Active->value)
                ->pluck('course_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($lessonId) {
            return $this->reports->coursesForLesson($companyId, $lessonId);
        }

        return DB::table('courses')
            ->where('company_id', $companyId)
            ->where('status', GeneralStatus::Active->value)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $courseIds
     * @return array<int, int>
     */
    private function resolveTeamIds(int $companyId, ?int $teamId, array $courseIds): array
    {
        if ($teamId) {
            return [$teamId];
        }

        return DB::table('teams')
            ->join('team_courses', function ($join) {
                $join->on('team_courses.team_id', '=', 'teams.id')
                    ->where('team_courses.status', GeneralStatus::Active->value);
            })
            ->where('teams.company_id', $companyId)
            ->where('teams.status', GeneralStatus::Active->value)
            ->when($courseIds !== [], fn ($q) => $q->whereIn('team_courses.course_id', $courseIds))
            ->distinct()
            ->pluck('teams.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Lesson ids locked by schedule, per team/course.
     *
     * @param  array<int, int>  $teamIds
     * @param  array<int, array<int, int>>  $courseLessons
     * @return array<int, array<int, array<int, bool>>>
     */
    private function lockedByTeamCourse(array $teamIds, array $courseLessons): array
    {
        if ($teamIds === []) {
            return [];
        }

        $maps = $this->scheduleMaps($teamIds);
        $locked = [];

        foreach ($teamIds as $teamId) {
            $map = $maps[$teamId] ?? ['course' => [], 'module' => [], 'lesson' => []];

            foreach ($courseLessons as $courseId => $lessons) {
                foreach ($lessons as $lessonId => $moduleId) {
                    if ($this->schedules->locked($this->schedules->effectiveAt($map, $courseId, $moduleId, $lessonId))) {
                        $locked[$teamId][$courseId][$lessonId] = true;
                    }
                }
            }
        }

        return $locked;
    }

    /**
     * @param  array<int, int>  $teamIds
     * @param  array<int, int>  $courseIds
     * @return array<int, bool>
     */
    private function lockedForLesson(array $teamIds, array $courseIds, int $lessonId): array
    {
        $maps = $this->scheduleMaps($teamIds);
        $moduleId = (int) DB::table('lessons')->where('id', $lessonId)->value('module_id');
        $locked = [];

        foreach ($teamIds as $teamId) {
            $map = $maps[$teamId] ?? ['course' => [], 'module' => [], 'lesson' => []];

            foreach ($courseIds as $courseId) {
                if ($this->schedules->locked($this->schedules->effectiveAt($map, $courseId, $moduleId, $lessonId))) {
                    $locked[$teamId] = true;
                    break;
                }
            }
        }

        return $locked;
    }

    /**
     * Schedule map (course/module/lesson => Carbon) for each team, in a single query.
     *
     * @param  array<int, int>  $teamIds
     * @return array<int, array{course: array<int, Carbon>, module: array<int, Carbon>, lesson: array<int, Carbon>}>
     */
    private function scheduleMaps(array $teamIds): array
    {
        if ($teamIds === []) {
            return [];
        }

        $maps = [];

        Schedule::query()
            ->whereIn('team_id', $teamIds)
            ->get()
            ->each(function ($schedule) use (&$maps) {
                $level = $schedule->schedulable_type;

                if ($schedule->available_at !== null && in_array($level, ['course', 'module', 'lesson'], true)) {
                    $maps[(int) $schedule->team_id][$level][(int) $schedule->schedulable_id] = $schedule->available_at;
                }
            });

        return $maps;
    }

    private function latestDate($current, $candidate)
    {
        if ($candidate === null) {
            return $current;
        }

        if ($current === null) {
            return $candidate;
        }

        return (string) $candidate > (string) $current ? $candidate : $current;
    }

    /**
     * Teams the given students are actively enrolled in, within the team scope.
     *
     * @param  array<int, int>  $userIds
     * @param  array<int, int>  $teamIds
     * @return array<int, int>
     */
    private function teamsEnrolledBy(int $companyId, array $userIds, array $teamIds): array
    {
        $allowed = [];

        foreach ($this->reports->teamsByUser($companyId, $userIds, $teamIds) as $ids) {
            foreach ($ids as $id) {
                $allowed[$id] = true;
            }
        }

        return array_map('intval', array_keys($allowed));
    }

    /**
     * Distinct course ids reachable through the given team/course map.
     *
     * @param  array<int, array<int, int>>  $coursesByTeam
     * @return array<int, int>
     */
    private function flattenCourseIds(array $coursesByTeam): array
    {
        $ids = [];

        foreach ($coursesByTeam as $courseIds) {
            foreach ($courseIds as $id) {
                $ids[$id] = true;
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * Per-student reachable scope and per-group distinct aggregates.
     *
     * Each student is counted once per course/module/lesson regardless of how many
     * teams grant access, so a student enrolled in several teams is not duplicated.
     *
     * @param  array<int, array<int, int>>  $enrollments  user_id => team ids
     * @param  array<int, array<int, int>>  $coursesByTeam
     * @param  array<int, array<int, int>>  $courseLessons  course_id => [lesson_id => module_id]
     * @param  array<int, array<int, bool>>  $lessonCourses  lesson_id => [course_id => true]
     * @param  array<int, array<int, array<int, bool>>>  $locked
     * @return array<string, mixed>
     */
    private function buildReach(array $enrollments, array $coursesByTeam, array $courseLessons, array $lessonCourses, array $locked): array
    {
        $studentsByTeam = [];
        $courseStats = [];
        $moduleStats = [];
        $lessonStats = [];
        $pairsTotal = 0;
        $blockedTotal = 0;

        foreach ($enrollments as $teamIds) {
            $userCourses = [];

            foreach ($teamIds as $teamId) {
                $studentsByTeam[$teamId] = ($studentsByTeam[$teamId] ?? 0) + 1;

                foreach ($coursesByTeam[$teamId] ?? [] as $courseId) {
                    $userCourses[$courseId] = true;
                }
            }

            $userLessons = [];

            foreach (array_keys($userCourses) as $courseId) {
                foreach ($courseLessons[$courseId] ?? [] as $lessonId => $moduleId) {
                    $userLessons[$lessonId] = $moduleId;
                }
            }

            $userBlocked = [];

            foreach ($teamIds as $teamId) {
                foreach ($locked[$teamId] ?? [] as $lessonSet) {
                    foreach (array_keys($lessonSet) as $lessonId) {
                        if (isset($userLessons[$lessonId])) {
                            $userBlocked[$lessonId] = true;
                        }
                    }
                }
            }

            foreach (array_keys($userCourses) as $courseId) {
                $courseStats[$courseId]['students'] = ($courseStats[$courseId]['students'] ?? 0) + 1;
            }

            $userModules = [];

            foreach ($userLessons as $lessonId => $moduleId) {
                $userModules[$moduleId] = true;
                $lessonStats[$lessonId]['students'] = ($lessonStats[$lessonId]['students'] ?? 0) + 1;
                $moduleStats[$moduleId]['pairs'] = ($moduleStats[$moduleId]['pairs'] ?? 0) + 1;
                $pairsTotal++;

                foreach ($lessonCourses[$lessonId] ?? [] as $courseId => $_) {
                    $courseStats[$courseId]['pairs'] = ($courseStats[$courseId]['pairs'] ?? 0) + 1;
                }
            }

            foreach (array_keys($userModules) as $moduleId) {
                $moduleStats[$moduleId]['students'] = ($moduleStats[$moduleId]['students'] ?? 0) + 1;
            }

            foreach (array_keys($userBlocked) as $lessonId) {
                $moduleId = $userLessons[$lessonId];
                $lessonStats[$lessonId]['blocked'] = ($lessonStats[$lessonId]['blocked'] ?? 0) + 1;
                $moduleStats[$moduleId]['blocked'] = ($moduleStats[$moduleId]['blocked'] ?? 0) + 1;
                $blockedTotal++;

                foreach ($lessonCourses[$lessonId] ?? [] as $courseId => $_) {
                    $courseStats[$courseId]['blocked'] = ($courseStats[$courseId]['blocked'] ?? 0) + 1;
                }
            }
        }

        return [
            'studentsByTeam' => $studentsByTeam,
            'course' => $courseStats,
            'module' => $moduleStats,
            'lesson' => $lessonStats,
            'dataset' => [
                'students' => count($enrollments),
                'pairs' => $pairsTotal,
                'blocked' => $blockedTotal,
            ],
        ];
    }

    /**
     * Empty, zeroed report payload (e.g. the selected students have no reachable content).
     *
     * @return array{data: array<int, mixed>, meta: array<string, mixed>}
     */
    private function emptyResult(string $groupBy, int $perPage): array
    {
        return [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
                'group_by' => $groupBy,
                'totals' => [
                    'students' => 0,
                    'lessons' => 0,
                    'completed' => 0,
                    'in_progress' => 0,
                    'pending' => 0,
                    'blocked' => 0,
                    'percentage' => 0,
                ],
            ],
        ];
    }
}
