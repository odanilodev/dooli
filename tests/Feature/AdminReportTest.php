<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Schedule;
use App\Models\Team;
use App\Models\User;
use App\Services\ReportService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_report_counts_completed_started_and_pending(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $second = $this->buildModuleLesson($this->company->id, $module);

        $team = $this->enroll($this->student, $course, $this->company->id);
        $token = $this->apiLogin($this->student);

        $this->withToken($token)->postJson('/api/v1/me/progress/complete', [
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'module_id' => $module->id,
        ])->assertOk();

        $this->withToken($token)->postJson('/api/v1/me/progress/start', [
            'lesson_id' => $second->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
        ])->assertOk();

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/reports/progress?group_by=team')
            ->assertOk()
            ->assertJsonPath('data.0.lessons', 2)
            ->assertJsonPath('data.0.students', 1)
            ->assertJsonPath('data.0.completed', 1)
            ->assertJsonPath('data.0.in_progress', 1)
            ->assertJsonPath('data.0.pending', 0)
            ->assertJsonPath('data.0.percentage', 50);
    }

    public function test_report_ignores_inactive_content(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $course, $this->company->id);

        $lesson->update(['status' => GeneralStatus::Inactive->value]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/reports/progress?group_by=team')
            ->assertOk()
            ->assertJsonPath('data.0.lessons', 0);
    }

    public function test_report_isolates_tenants(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $course, $this->company->id);

        $other = Company::factory()->create();
        $data = $this->buildCourse($other->id);
        $otherTeam = Team::factory()->create(['company_id' => $other->id]);
        $otherTeam->courses()->attach($data['course']->id, ['company_id' => $other->id, 'status' => 'active']);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/reports/progress?group_by=team')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lessons', 1);
    }

    public function test_report_filters_by_student_account_status(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course, $this->company->id);

        $this->student->update(['status' => GeneralStatus::Inactive->value]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/reports/progress?group_by=team&student_status=active')
            ->assertOk()
            ->assertJsonPath('data.0.students', 0)
            ->assertJsonPath('data.0.pending', 0);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/reports/progress?group_by=team&student_status=inactive')
            ->assertOk()
            ->assertJsonPath('data.0.students', 1);
    }

    public function test_report_counts_scheduled_lessons_as_blocked(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course, $this->company->id);

        Schedule::query()->create([
            'company_id' => $this->company->id,
            'team_id' => $team->id,
            'schedulable_type' => 'course',
            'schedulable_id' => $course->id,
            'available_at' => now()->addDay(),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/reports/progress?group_by=team')
            ->assertOk()
            ->assertJsonPath('data.0.lessons', 1)
            ->assertJsonPath('data.0.blocked', 1)
            ->assertJsonPath('data.0.pending', 0);
    }

    public function test_report_drills_by_course_and_module(): void
    {
        ['course' => $course, 'module' => $module] = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course, $this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=course&team_id={$team->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $course->id)
            ->assertJsonPath('data.0.lessons', 1);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=module&course_id={$course->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $module->id)
            ->assertJsonPath('data.0.lessons', 1);
    }

    public function test_student_detail_returns_lesson_statuses(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course, $this->company->id);

        $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/progress/complete', [
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'module_id' => $module->id,
        ])->assertOk();

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/students/{$this->student->id}")
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->student->id)
            ->assertJsonPath('data.items.0.completed', 1)
            ->assertJsonPath('data.items.0.lessons.0.status', 'completed');
    }

    public function test_lesson_detail_returns_students(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course, $this->company->id);

        $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/progress/complete', [
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'module_id' => $module->id,
        ])->assertOk();

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/lessons/{$lesson->id}")
            ->assertOk()
            ->assertJsonPath('data.lesson.id', $lesson->id)
            ->assertJsonPath('data.students.0.user_id', $this->student->id)
            ->assertJsonPath('data.students.0.status', 'completed');
    }

    public function test_report_filters_by_multiple_students(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);

        $team = $this->enroll($this->student, $course, $this->company->id);
        $other = $this->addStudentToTeam($team);

        $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/progress/complete', [
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'module_id' => $module->id,
        ])->assertOk();

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/reports/progress?group_by=team')
            ->assertOk()
            ->assertJsonPath('data.0.students', 2)
            ->assertJsonPath('data.0.completed', 1);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=team&user_ids[]={$this->student->id}")
            ->assertOk()
            ->assertJsonPath('data.0.students', 1)
            ->assertJsonPath('data.0.completed', 1)
            ->assertJsonPath('data.0.pending', 0);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=team&user_ids[]={$other->id}")
            ->assertOk()
            ->assertJsonPath('data.0.students', 1)
            ->assertJsonPath('data.0.completed', 0)
            ->assertJsonPath('data.0.pending', 1);
    }

    public function test_scope_totals_count_distinct_students_across_teams(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);

        $this->enroll($this->student, $course, $this->company->id);
        $this->enroll($this->student, $course, $this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/reports/progress?group_by=team')
            ->assertOk()
            ->assertJsonPath('meta.totals.students', 1);
    }

    public function test_report_group_by_student_respects_the_student_filter(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);

        $team = $this->enroll($this->student, $course, $this->company->id);
        $this->addStudentToTeam($team);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=student&user_ids[]={$this->student->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->student->id);
    }

    public function test_lesson_detail_respects_the_student_filter(): void
    {
        ['course' => $course, 'lesson' => $lesson] = $this->buildCourse($this->company->id);

        $team = $this->enroll($this->student, $course, $this->company->id);
        $other = $this->addStudentToTeam($team);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/lessons/{$lesson->id}?user_ids[]={$other->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.students')
            ->assertJsonPath('data.students.0.user_id', $other->id);
    }

    public function test_report_rejects_students_from_another_company(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $course, $this->company->id);

        $foreign = User::factory()->create(['company_id' => Company::factory()->create()->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=team&user_ids[]={$foreign->id}")
            ->assertStatus(422);
    }

    public function test_student_filter_hides_groups_without_enrollment(): void
    {
        ['course' => $courseA] = $this->buildCourse($this->company->id);
        $teamA = $this->enroll($this->student, $courseA, $this->company->id);

        $other = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);
        ['course' => $courseB] = $this->buildCourse($this->company->id);
        $teamB = $this->enroll($other, $courseB, $this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=team&user_ids[]={$this->student->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $teamA->id);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=team&user_ids[]={$this->student->id}&user_ids[]={$other->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_student_filter_limits_course_module_and_lesson_levels(): void
    {
        ['course' => $courseA, 'module' => $moduleA, 'lesson' => $lessonA] = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $courseA, $this->company->id);

        $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=course&user_ids[]={$this->student->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $courseA->id);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=module&user_ids[]={$this->student->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $moduleA->id);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=lesson&user_ids[]={$this->student->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lessonA->id);
    }

    public function test_student_filter_without_enrollment_returns_empty(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $course, $this->company->id);

        $lone = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=team&user_ids[]={$lone->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.totals.students', 0);
    }

    public function test_student_in_multiple_teams_is_counted_once_per_group(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);

        $this->enroll($this->student, $course, $this->company->id);
        $this->enroll($this->student, $course, $this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/reports/progress?group_by=course')
            ->assertOk()
            ->assertJsonPath('data.0.students', 1)
            ->assertJsonPath('data.0.lessons', 1);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=lesson&user_ids[]={$this->student->id}")
            ->assertOk()
            ->assertJsonPath('data.0.students', 1);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/reports/progress?group_by=team&user_ids[]={$this->student->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_student_status_filter_does_not_hide_groups(): void
    {
        ['course' => $courseA] = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $courseA, $this->company->id);

        $inactive = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
            'status' => GeneralStatus::Inactive->value,
        ]);
        ['course' => $courseB] = $this->buildCourse($this->company->id);
        $this->enroll($inactive, $courseB, $this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/reports/progress?group_by=team&student_status=active')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_students_cannot_access_reports(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/reports/progress')
            ->assertForbidden();
    }

    public function test_report_export_returns_excel_compatible_csv(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course, $this->company->id);

        $response = $this->withToken($this->apiLogin($this->admin))
            ->get('/api/v1/admin/reports/export?group_by=team')
            ->assertOk();

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $lines = preg_split('/\r\n|\n|\r/', trim(substr($content, 3)));
        $headers = str_getcsv($lines[0], ';', '"', '\\');
        $row = str_getcsv($lines[1], ';', '"', '\\');

        $this->assertSame([
            'Turma', 'Alunos', 'Aulas', 'Concluídas', 'Em andamento', 'Pendentes', 'Bloqueadas', 'Progresso (%)', 'Última atividade',
        ], $headers);
        $this->assertSame($team->name, $row[0]);
        $this->assertSame('1', $row[1]);
        $this->assertSame('1', $row[2]);
        $this->assertSame('0', $row[3]);
        $this->assertSame('0,00', $row[7]);
    }

    public function test_report_export_includes_all_pages(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);

        $this->enroll($this->student, $course, $this->company->id);
        $this->enroll($this->student, $course, $this->company->id);
        $this->enroll($this->student, $course, $this->company->id);

        app(CompanyContext::class)->set($this->company->id);

        $export = app(ReportService::class)->export(['group_by' => 'team'], 2);

        $this->assertCount(3, $export['rows']);
    }

    public function test_snapshot_is_maintained_by_progress_events(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course, $this->company->id);

        $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/progress/complete', [
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'module_id' => $module->id,
        ])->assertOk();

        $this->assertDatabaseHas('student_lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'status' => 'completed',
        ]);
    }

    public function test_rebuild_command_restores_the_snapshot(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course, $this->company->id);

        $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/progress/complete', [
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'module_id' => $module->id,
        ])->assertOk();

        DB::table('student_lesson_progress')->delete();

        Artisan::call('progress:rebuild', ['--company' => $this->company->id, '--fresh' => true]);

        $this->assertDatabaseHas('student_lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
        ]);
    }

    public function test_report_uses_a_bounded_number_of_queries(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $course, $this->company->id);

        $token = $this->apiLogin($this->admin);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->withToken($token)
            ->getJson('/api/v1/admin/reports/progress?group_by=team')
            ->assertOk();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(30, $count, "Report issued {$count} queries.");
    }

    public function test_removed_student_is_excluded_from_report_counts(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course, $this->company->id);

        $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/progress/complete', [
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'module_id' => $module->id,
        ])->assertOk();

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/teams/{$team->id}/students/{$this->student->id}")
            ->assertOk();

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/reports/progress?group_by=team')
            ->assertOk()
            ->assertJsonPath('data.0.students', 0)
            ->assertJsonPath('data.0.completed', 0)
            ->assertJsonPath('data.0.percentage', 0);
    }

    public function test_inactive_lesson_does_not_count_stale_completions(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course, $this->company->id);

        $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/progress/complete', [
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'module_id' => $module->id,
        ])->assertOk();

        $lesson->update(['status' => GeneralStatus::Inactive->value]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/reports/progress?group_by=team')
            ->assertOk()
            ->assertJsonPath('data.0.lessons', 0)
            ->assertJsonPath('data.0.completed', 0)
            ->assertJsonPath('data.0.percentage', 0);
    }

    public function test_report_totals_never_exceed_reachable_pairs(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course, $this->company->id);

        $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/progress/complete', [
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'module_id' => $module->id,
        ])->assertOk();

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/teams/{$team->id}/students/{$this->student->id}")
            ->assertOk();

        $response = $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/reports/progress?group_by=team')
            ->assertOk();

        $totals = $response->json('meta.totals');

        $accounted = $totals['completed'] + $totals['in_progress'] + $totals['pending'] + $totals['blocked'];

        $this->assertSame(0, $totals['completed']);
        $this->assertSame(0, $totals['in_progress']);
        $this->assertGreaterThanOrEqual(0, $totals['pending']);
        $this->assertLessThanOrEqual(100, $totals['percentage']);
        $this->assertLessThanOrEqual($totals['students'] * $totals['lessons'], $accounted);
    }

    public function test_deleting_a_lesson_clears_its_progress_snapshot(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course, $this->company->id);

        $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/progress/complete', [
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'module_id' => $module->id,
        ])->assertOk();

        $this->assertDatabaseHas('student_lesson_progress', ['lesson_id' => $lesson->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/lessons/{$lesson->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('student_lesson_progress', ['lesson_id' => $lesson->id]);
    }

    public function test_deleting_a_team_clears_its_progress(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $this->buildModuleLesson($this->company->id, $module);
        $team = $this->enroll($this->student, $course, $this->company->id);

        $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/progress/complete', [
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'module_id' => $module->id,
        ])->assertOk();

        $this->assertDatabaseHas('student_lesson_progress', ['team_id' => $team->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/teams/{$team->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('student_lesson_progress', ['team_id' => $team->id]);
    }

    public function test_deleting_a_user_clears_their_progress(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $this->buildModuleLesson($this->company->id, $module);

        $student = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $team = $this->enroll($student, $course, $this->company->id);

        $this->withToken($this->apiLogin($student))->postJson('/api/v1/me/progress/complete', [
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'module_id' => $module->id,
        ])->assertOk();

        $this->assertDatabaseHas('student_lesson_progress', ['user_id' => $student->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/users/{$student->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('student_lesson_progress', ['user_id' => $student->id]);
    }

    public function test_starting_a_lesson_in_two_teams_is_rebuilt_for_both(): void
    {
        ['course' => $course, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $teamA = $this->enroll($this->student, $course, $this->company->id);
        $teamB = $this->enroll($this->student, $course, $this->company->id);

        $token = $this->apiLogin($this->student);

        foreach ([$teamA, $teamB] as $team) {
            $this->withToken($token)->postJson('/api/v1/me/progress/start', [
                'lesson_id' => $lesson->id,
                'course_id' => $course->id,
                'team_id' => $team->id,
            ])->assertOk();
        }

        $rows = fn () => DB::table('student_lesson_progress')
            ->where('user_id', $this->student->id)
            ->where('lesson_id', $lesson->id)
            ->count();

        $this->assertSame(2, $rows());

        DB::table('student_lesson_progress')->delete();

        Artisan::call('progress:rebuild', ['--company' => $this->company->id, '--fresh' => true]);

        $this->assertSame(2, $rows());
    }

    public function test_progress_verify_detects_and_rebuild_fixes_drift(): void
    {
        ['course' => $course, 'module' => $module, 'lesson' => $lesson] = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course, $this->company->id);

        $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/progress/complete', [
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'team_id' => $team->id,
            'module_id' => $module->id,
        ])->assertOk();

        DB::table('student_lesson_progress')->delete();

        $this->assertSame(1, Artisan::call('progress:verify', ['--company' => $this->company->id]));

        Artisan::call('progress:rebuild', ['--company' => $this->company->id, '--fresh' => true]);

        $this->assertSame(0, Artisan::call('progress:verify', ['--company' => $this->company->id]));
    }

    private function addStudentToTeam(Team $team): User
    {
        $student = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $team->users()->attach($student->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
            'expired_at' => null,
        ]);

        return $student;
    }
}
