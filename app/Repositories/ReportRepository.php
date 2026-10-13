<?php

namespace App\Repositories;

use App\Enums\GeneralStatus;
use App\Enums\LessonProgressStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\StudentLessonProgress;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new StudentLessonProgress);
    }

    /**
     * @param  array<int, int>|null  $restrictTeamIds
     */
    public function paginateTeams(?int $courseId, ?string $search, int $perPage, ?array $restrictTeamIds = null): LengthAwarePaginator
    {
        return Team::query()
            ->where('status', GeneralStatus::Active->value)
            ->when($restrictTeamIds !== null, fn ($q) => $q->whereIn('id', $restrictTeamIds))
            ->when($search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($courseId, fn ($q, $c) => $q->whereHas(
                'courses',
                fn ($inner) => $inner->where('courses.id', $c)->where('team_courses.status', GeneralStatus::Active->value),
            ))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<int, int>  $teamIds
     */
    public function paginateCourses(array $teamIds, ?int $courseId, ?int $moduleId, ?int $lessonId, ?string $search, int $perPage): LengthAwarePaginator
    {
        return Course::query()
            ->where('status', GeneralStatus::Active->value)
            ->when($courseId, fn ($q, $id) => $q->whereKey($id))
            ->when($teamIds, fn ($q) => $q->whereHas(
                'teams',
                fn ($inner) => $inner->whereIn('teams.id', $teamIds)->where('team_courses.status', GeneralStatus::Active->value),
            ))
            ->when($moduleId, fn ($q, $id) => $q->whereHas(
                'modules',
                fn ($inner) => $inner->where('modules.id', $id)->where('course_modules.status', GeneralStatus::Active->value),
            ))
            ->when($lessonId, fn ($q, $id) => $q->whereHas('modules.lessons', fn ($inner) => $inner->where('lessons.id', $id)))
            ->when($search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<int, int>  $courseIds
     */
    public function paginateModules(array $courseIds, ?int $moduleId, ?int $lessonId, ?string $search, int $perPage): LengthAwarePaginator
    {
        return Module::query()
            ->where('status', GeneralStatus::Active->value)
            ->when($moduleId, fn ($q, $id) => $q->whereKey($id))
            ->when($courseIds, fn ($q) => $q->whereHas(
                'courses',
                fn ($inner) => $inner->whereIn('courses.id', $courseIds)->where('course_modules.status', GeneralStatus::Active->value),
            ))
            ->when($lessonId, fn ($q, $id) => $q->whereHas('lessons', fn ($inner) => $inner->where('lessons.id', $id)))
            ->when($search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @param  array<int, int>  $courseIds
     */
    public function paginateLessons(array $courseIds, ?int $moduleId, ?int $lessonId, ?string $search, int $perPage): LengthAwarePaginator
    {
        return Lesson::query()
            ->where('lessons.status', GeneralStatus::Active->value)
            ->when($lessonId, fn ($q, $id) => $q->whereKey($id))
            ->when($moduleId, fn ($q, $id) => $q->where('module_id', $id))
            ->when($courseIds, fn ($q) => $q->whereHas(
                'module.courses',
                fn ($inner) => $inner->whereIn('courses.id', $courseIds)->where('course_modules.status', GeneralStatus::Active->value),
            ))
            ->when($search, fn ($q, $s) => $q->where('lessons.name', 'like', "%{$s}%"))
            ->with('module:id,name')
            ->orderBy('lessons.name')
            ->paginate($perPage);
    }

    /**
     * @param  array<int, int>  $teamIds
     * @param  array<int, int>  $userIds
     */
    public function paginateStudents(array $teamIds, ?string $status, ?string $search, int $perPage, array $userIds = []): LengthAwarePaginator
    {
        return User::query()
            ->when($userIds, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->whereHas('teamLinks', fn ($q) => $q
                ->whereIn('team_id', $teamIds)
                ->where('status', GeneralStatus::Active->value)
                ->where(fn ($e) => $e->whereNull('expired_at')->orWhere('expired_at', '>', now())))
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Active enrollments grouped per user (user_id => team ids), within scope.
     *
     * @param  array<int, int>  $teamIds
     * @param  array<int, int>  $userIds
     * @return array<int, array<int, int>>
     */
    public function enrollmentsByUser(int $companyId, array $teamIds, ?string $status, ?string $search, array $userIds = []): array
    {
        if ($teamIds === []) {
            return [];
        }

        $rows = DB::table('team_users')
            ->join('users', 'users.id', '=', 'team_users.user_id')
            ->where('team_users.company_id', $companyId)
            ->whereIn('team_users.team_id', $teamIds)
            ->where('team_users.status', GeneralStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('team_users.expired_at')->orWhere('team_users.expired_at', '>', now()))
            ->when($userIds, fn ($q, $ids) => $q->whereIn('team_users.user_id', $ids))
            ->when($status, fn ($q, $s) => $q->where('users.status', $s))
            ->when($search, fn ($q, $s) => $q->where('users.name', 'like', "%{$s}%"))
            ->get(['team_users.user_id', 'team_users.team_id']);

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->user_id][] = (int) $row->team_id;
        }

        return $map;
    }

    /**
     * Active courses per team, restricted to the given course scope when provided.
     *
     * @param  array<int, int>  $teamIds
     * @param  array<int, int>  $courseIds
     * @return array<int, array<int, int>> team_id => course ids
     */
    public function coursesByTeam(int $companyId, array $teamIds, array $courseIds): array
    {
        if ($teamIds === []) {
            return [];
        }

        $rows = DB::table('team_courses')
            ->where('company_id', $companyId)
            ->whereIn('team_id', $teamIds)
            ->where('status', GeneralStatus::Active->value)
            ->when($courseIds !== [], fn ($q) => $q->whereIn('course_id', $courseIds))
            ->get(['team_id', 'course_id']);

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->team_id][] = (int) $row->course_id;
        }

        return $map;
    }

    /**
     * Teams each user belongs to (within the given team scope).
     *
     * @param  array<int, int>  $userIds
     * @param  array<int, int>  $teamIds
     * @return array<int, array<int, int>> user_id => team ids
     */
    public function teamsByUser(int $companyId, array $userIds, array $teamIds): array
    {
        if ($userIds === [] || $teamIds === []) {
            return [];
        }

        $rows = DB::table('team_users')
            ->where('company_id', $companyId)
            ->whereIn('user_id', $userIds)
            ->whereIn('team_id', $teamIds)
            ->where('status', GeneralStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('expired_at')->orWhere('expired_at', '>', now()))
            ->get(['user_id', 'team_id']);

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->user_id][] = (int) $row->team_id;
        }

        return $map;
    }

    /**
     * Active lessons reachable through each course, with module information.
     *
     * @param  array<int, int>  $courseIds
     * @return Collection<int, object>
     */
    public function eligibleLessons(int $companyId, array $courseIds, ?int $moduleId, ?int $lessonId): Collection
    {
        if ($courseIds === []) {
            return collect();
        }

        return DB::table('course_modules')
            ->join('modules', 'modules.id', '=', 'course_modules.module_id')
            ->join('lessons', 'lessons.module_id', '=', 'modules.id')
            ->leftJoin('topics', 'topics.id', '=', 'lessons.topic_id')
            ->where('course_modules.company_id', $companyId)
            ->whereIn('course_modules.course_id', $courseIds)
            ->where('course_modules.status', GeneralStatus::Active->value)
            ->where('modules.status', GeneralStatus::Active->value)
            ->where('lessons.status', GeneralStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('lessons.topic_id')->orWhere('topics.status', GeneralStatus::Active->value))
            ->when($moduleId, fn ($q, $id) => $q->where('lessons.module_id', $id))
            ->when($lessonId, fn ($q, $id) => $q->where('lessons.id', $id))
            ->distinct()
            ->get([
                'course_modules.course_id as course_id',
                'lessons.module_id as module_id',
                'lessons.id as lesson_id',
                'lessons.name as lesson_name',
                'modules.name as module_name',
            ]);
    }

    /**
     * Progress counts grouped by the given dimension for the requested ids.
     *
     * Only rows that belong to the report universe (active enrollment granting
     * the course, plus eligible content) are counted, so completed/in-progress
     * never exceed the reachable pairs.
     *
     * @param  array<int, int>  $ids
     * @param  array<string, mixed>  $scope
     * @return Collection<string, object>
     */
    public function progressCounts(string $column, array $ids, array $scope): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $completed = LessonProgressStatus::Completed->value;
        $started = LessonProgressStatus::Started->value;

        $group = $this->groupExpression($column);

        return $this->applyUniverse($this->query(), $scope)
            ->whereIn($group, $ids)
            ->when($scope['teamIds'] ?? [], fn ($q, $v) => $q->whereIn('student_lesson_progress.team_id', $v))
            ->when($scope['courseIds'] ?? [], fn ($q, $v) => $q->whereIn('student_lesson_progress.course_id', $v))
            ->when($scope['moduleId'] ?? null, fn ($q, $v) => $q->where('lessons.module_id', $v))
            ->when($scope['lessonId'] ?? null, fn ($q, $v) => $q->where('student_lesson_progress.lesson_id', $v))
            ->when($scope['userIds'] ?? [], fn ($q, $v) => $q->whereIn('student_lesson_progress.user_id', $v))
            ->when($scope['studentStatus'] ?? null, fn ($q, $v) => $q->whereHas('user', fn ($u) => $u->where('users.status', $v)))
            ->when($scope['search'] ?? null, fn ($q, $v) => $q->whereHas('user', fn ($u) => $u->where('users.name', 'like', "%{$v}%")))
            ->groupBy($group)
            ->selectRaw("{$group} as group_id")
            ->selectRaw('SUM(CASE WHEN student_lesson_progress.status = ? THEN 1 ELSE 0 END) as completed', [$completed])
            ->selectRaw('SUM(CASE WHEN student_lesson_progress.status = ? THEN 1 ELSE 0 END) as in_progress', [$started])
            ->selectRaw('COUNT(DISTINCT student_lesson_progress.lesson_id) as lessons_touched')
            ->selectRaw('COUNT(DISTINCT student_lesson_progress.user_id) as users_touched')
            ->selectRaw('MAX(student_lesson_progress.last_activity_at) as last_activity_at')
            ->get()
            ->keyBy('group_id');
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function scopeTotals(array $scope): object
    {
        $completed = LessonProgressStatus::Completed->value;
        $started = LessonProgressStatus::Started->value;

        $row = $this->applyUniverse($this->query(), $scope)
            ->when($scope['teamIds'] ?? [], fn ($q, $v) => $q->whereIn('student_lesson_progress.team_id', $v))
            ->when($scope['courseIds'] ?? [], fn ($q, $v) => $q->whereIn('student_lesson_progress.course_id', $v))
            ->when($scope['moduleId'] ?? null, fn ($q, $v) => $q->where('lessons.module_id', $v))
            ->when($scope['lessonId'] ?? null, fn ($q, $v) => $q->where('student_lesson_progress.lesson_id', $v))
            ->when($scope['userIds'] ?? [], fn ($q, $v) => $q->whereIn('student_lesson_progress.user_id', $v))
            ->when($scope['studentStatus'] ?? null, fn ($q, $v) => $q->whereHas('user', fn ($u) => $u->where('users.status', $v)))
            ->when($scope['search'] ?? null, fn ($q, $v) => $q->whereHas('user', fn ($u) => $u->where('users.name', 'like', "%{$v}%")))
            ->selectRaw('SUM(CASE WHEN student_lesson_progress.status = ? THEN 1 ELSE 0 END) as completed', [$completed])
            ->selectRaw('SUM(CASE WHEN student_lesson_progress.status = ? THEN 1 ELSE 0 END) as in_progress', [$started])
            ->first();

        return $row ?? (object) ['completed' => 0, 'in_progress' => 0];
    }

    /**
     * Restricts the snapshot query to the report universe: the student is
     * actively enrolled in a team that actively grants the course, and the
     * lesson chain (lesson -> module -> topic) is active and linked to that
     * course. This makes the stored counts match the reachable pairs.
     *
     * @param  array<string, mixed>  $scope
     */
    private function applyUniverse(Builder $query, array $scope): Builder
    {
        $companyId = (int) ($scope['companyId'] ?? 0);

        return $query
            ->where('student_lesson_progress.company_id', $companyId)
            ->whereExists(function ($sub) use ($companyId) {
                $sub->select(DB::raw(1))
                    ->from('team_users')
                    ->whereColumn('team_users.user_id', 'student_lesson_progress.user_id')
                    ->whereColumn('team_users.team_id', 'student_lesson_progress.team_id')
                    ->where('team_users.company_id', $companyId)
                    ->where('team_users.status', GeneralStatus::Active->value)
                    ->where(fn ($w) => $w->whereNull('team_users.expired_at')->orWhere('team_users.expired_at', '>', now()));
            })
            ->whereExists(function ($sub) use ($companyId) {
                $sub->select(DB::raw(1))
                    ->from('team_courses')
                    ->whereColumn('team_courses.team_id', 'student_lesson_progress.team_id')
                    ->whereColumn('team_courses.course_id', 'student_lesson_progress.course_id')
                    ->where('team_courses.company_id', $companyId)
                    ->where('team_courses.status', GeneralStatus::Active->value);
            })
            ->join('lessons', 'lessons.id', '=', 'student_lesson_progress.lesson_id')
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->join('course_modules', function ($join) use ($companyId) {
                $join->on('course_modules.module_id', '=', 'lessons.module_id')
                    ->on('course_modules.course_id', '=', 'student_lesson_progress.course_id')
                    ->where('course_modules.company_id', $companyId)
                    ->where('course_modules.status', GeneralStatus::Active->value);
            })
            ->leftJoin('topics', 'topics.id', '=', 'lessons.topic_id')
            ->where('lessons.status', GeneralStatus::Active->value)
            ->where('modules.status', GeneralStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('lessons.topic_id')->orWhere('topics.status', GeneralStatus::Active->value));
    }

    private function groupExpression(string $column): string
    {
        return match ($column) {
            'module_id' => 'lessons.module_id',
            'lesson_id' => 'student_lesson_progress.lesson_id',
            'course_id' => 'student_lesson_progress.course_id',
            'user_id' => 'student_lesson_progress.user_id',
            default => 'student_lesson_progress.team_id',
        };
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Collection<int, object>
     */
    public function studentLessonStatuses(int $userId, array $scope): Collection
    {
        return DB::table('student_lesson_progress')
            ->where('company_id', $scope['companyId'])
            ->where('user_id', $userId)
            ->when($scope['teamIds'] ?? [], fn ($q, $ids) => $q->whereIn('team_id', $ids))
            ->when($scope['courseIds'] ?? [], fn ($q, $ids) => $q->whereIn('course_id', $ids))
            ->get(['course_id', 'lesson_id', 'status', 'watched_seconds', 'last_activity_at']);
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Collection<int, object>
     */
    public function lessonStudentStatuses(int $lessonId, array $scope): Collection
    {
        return DB::table('student_lesson_progress')
            ->where('company_id', $scope['companyId'])
            ->where('lesson_id', $lessonId)
            ->when($scope['teamIds'] ?? [], fn ($q, $ids) => $q->whereIn('team_id', $ids))
            ->when($scope['courseIds'] ?? [], fn ($q, $ids) => $q->whereIn('course_id', $ids))
            ->get(['user_id', 'status', 'watched_seconds', 'last_activity_at']);
    }

    /**
     * @return array<int, int>
     */
    public function coursesForLesson(int $companyId, int $lessonId): array
    {
        return DB::table('course_modules')
            ->join('lessons', 'lessons.module_id', '=', 'course_modules.module_id')
            ->where('course_modules.company_id', $companyId)
            ->where('lessons.id', $lessonId)
            ->where('course_modules.status', GeneralStatus::Active->value)
            ->pluck('course_modules.course_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $teamIds
     * @param  array<int, int>  $userIds
     * @return Collection<int, object>
     */
    public function studentsForLesson(int $companyId, int $lessonId, array $teamIds, ?string $status, ?string $search, array $userIds = []): Collection
    {
        if ($teamIds === []) {
            return collect();
        }

        return DB::table('team_users')
            ->join('users', 'users.id', '=', 'team_users.user_id')
            ->where('team_users.company_id', $companyId)
            ->whereIn('team_users.team_id', $teamIds)
            ->where('team_users.status', GeneralStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('team_users.expired_at')->orWhere('team_users.expired_at', '>', now()))
            ->when($userIds, fn ($q, $ids) => $q->whereIn('team_users.user_id', $ids))
            ->when($status, fn ($q, $s) => $q->where('users.status', $s))
            ->when($search, fn ($q, $s) => $q->where('users.name', 'like', "%{$s}%"))
            ->groupBy('team_users.user_id', 'users.name', 'users.email')
            ->selectRaw('team_users.user_id as user_id, users.name as name, users.email as email, MIN(team_users.team_id) as team_id')
            ->orderBy('users.name')
            ->get();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    public function courseNames(array $ids): array
    {
        return DB::table('courses')->whereIn('id', $ids)->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    public function teamNames(array $ids): array
    {
        return DB::table('teams')->whereIn('id', $ids)->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])->all();
    }

    /**
     * Recent activity feed derived from the timeline.
     *
     * @param  array<string, mixed>  $scope
     * @return Collection<int, object>
     */
    public function activity(array $scope, int $limit): Collection
    {
        return DB::table('timelines')
            ->join('users', 'users.id', '=', 'timelines.user_id')
            ->join('lessons', 'lessons.id', '=', 'timelines.lesson_id')
            ->leftJoin('actions', 'actions.id', '=', 'timelines.action_id')
            ->where('timelines.company_id', $scope['companyId'])
            ->when($scope['teamIds'] ?? [], fn ($q, $ids) => $q->whereIn('timelines.team_id', $ids))
            ->when($scope['courseIds'] ?? [], fn ($q, $ids) => $q->whereIn('timelines.course_id', $ids))
            ->when($scope['userId'] ?? null, fn ($q, $id) => $q->where('timelines.user_id', $id))
            ->when($scope['userIds'] ?? [], fn ($q, $ids) => $q->whereIn('timelines.user_id', $ids))
            ->orderByDesc('timelines.id')
            ->limit($limit)
            ->get([
                'timelines.id as id',
                'timelines.created_at as created_at',
                'timelines.updated_at as updated_at',
                'users.id as user_id',
                'users.name as user_name',
                'lessons.id as lesson_id',
                'lessons.name as lesson_name',
                'actions.code as action',
            ]);
    }
}
