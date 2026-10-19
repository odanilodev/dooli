<?php

namespace Tests\Feature;

use App\Console\Commands\RestoreRecalculatedCertificates;
use App\DTO\CompleteLessonDTO;
use App\Enums\GeneralStatus;
use App\Models\Certificate;
use App\Models\CertificateUser;
use App\Models\Course;
use App\Models\CourseCompletion;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Team;
use App\Models\Topic;
use App\Repositories\ActionRepository;
use App\Repositories\CourseCompletionRepository;
use App\Repositories\LessonRepository;
use App\Repositories\StudentLessonProgressRepository;
use App\Repositories\TimelineRepository;
use App\Repositories\VideoProgressRepository;
use App\Services\CertificateService;
use App\Services\DatalogService;
use App\Services\ProgressService;
use App\Services\StudentCourseService;
use App\Support\CompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class CourseCompletionIntegrityTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    /**
     * @param  array{course: Course, module: Module, topic: Topic, lesson: Lesson}  $course
     */
    private function complete(array $course, Team $team, Lesson $lesson, bool $assertOk = true): void
    {
        $request = $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $lesson->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
                'module_id' => $lesson->module_id,
            ]);

        if ($assertOk) {
            $request->assertOk();
        }
    }

    public function test_adding_a_lesson_after_completion_keeps_the_certificate_valid(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->complete($course, $team, $course['lesson']);

        $this->assertSame(1, CertificateUser::query()->count());
        $this->assertSame(1, CourseCompletion::query()->count());

        $certificate = CertificateUser::query()->firstOrFail();
        $this->buildModuleLesson($this->company->id, $course['module']);

        $this->assertNull($certificate->fresh()->invalidated_at);
        $this->assertSame(1, CertificateUser::query()->count());

        $this->withHeaders(['X-Company' => (string) $this->company->id])
            ->getJson("/api/v1/certificates/{$certificate->code}/verify")
            ->assertOk();
    }

    public function test_completing_new_lessons_replaces_certificate_with_updated_grid(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->complete($course, $team, $course['lesson']);

        $old = CertificateUser::query()->firstOrFail();
        $oldCode = $old->code;

        $newModule = Module::factory()->create([
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);
        $newLesson = $this->buildModuleLesson($this->company->id, $newModule);
        $course['course']->modules()->attach($newModule->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
            'sort_order' => 1,
        ]);

        $this->assertNull($old->fresh()->invalidated_at);

        $this->complete($course, $team, $newLesson);

        $old->refresh();
        $new = CertificateUser::query()->whereKeyNot($old->id)->firstOrFail();

        $this->assertSame(CertificateUser::STATUS_REPLACED, $old->status());
        $this->assertSame(CertificateUser::INVALIDATION_REPLACED, $old->invalidation_reason);
        $this->assertSame(CertificateUser::STATUS_VALID, $new->status());
        $this->assertNotSame($oldCode, $new->code);
        $this->assertSame(2, $new->lessons_count);
        $this->assertSame([$course['module']->name], array_column($old->modules, 'name'));
        $this->assertSame(
            [$course['module']->name, $newModule->name],
            array_column($new->modules, 'name'),
        );
        $this->assertSame(1, CertificateUser::query()->whereNull('invalidated_at')->count());

        $this->withHeaders(['X-Company' => (string) $this->company->id])
            ->getJson("/api/v1/certificates/{$oldCode}/verify")
            ->assertStatus(404);

        $this->withHeaders(['X-Company' => (string) $this->company->id])
            ->getJson("/api/v1/certificates/{$new->code}/verify")
            ->assertOk();

        $statuses = collect(
            $this->withToken($this->apiLogin($this->student))
                ->getJson('/api/v1/me/certificates')
                ->assertOk()
                ->json('data'),
        )->pluck('status', 'code');

        $this->assertSame(CertificateUser::STATUS_REPLACED, $statuses[$oldCode]);
        $this->assertSame(CertificateUser::STATUS_VALID, $statuses[$new->code]);

        $completion = CourseCompletion::query()->firstOrFail();
        $this->assertSame(2, $completion->required_lessons);
        $this->assertCount(2, $completion->lesson_ids);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('data.0.required_lessons', 2)
            ->assertJsonPath('data.0.new_lessons_since_completion', 0);
    }

    public function test_new_lesson_in_existing_module_reissues_the_certificate(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->complete($course, $team, $course['lesson']);

        $old = CertificateUser::query()->firstOrFail();
        $newLesson = $this->buildModuleLesson($this->company->id, $course['module']);

        $this->complete($course, $team, $newLesson);

        $new = CertificateUser::query()->whereKeyNot($old->id)->firstOrFail();

        $this->assertSame(CertificateUser::STATUS_REPLACED, $old->fresh()->status());
        $this->assertSame(2, $new->lessons_count);
        $this->assertSame([$course['module']->name], array_column($new->modules, 'name'));
        $this->assertSame(1, CertificateUser::query()->whereNull('invalidated_at')->count());
    }

    public function test_replacement_is_idempotent(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->complete($course, $team, $course['lesson']);

        $newLesson = $this->buildModuleLesson($this->company->id, $course['module']);
        $this->complete($course, $team, $newLesson);

        app(CertificateService::class)->issueIfEligible($this->student, $course['course']->id, $team->id);
        $this->complete($course, $team, $newLesson);

        $this->assertSame(2, CertificateUser::query()->count());
        $this->assertSame(1, CertificateUser::query()->whereNull('invalidated_at')->count());
    }

    public function test_course_remains_marked_completed_after_new_lessons(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->complete($course, $team, $course['lesson']);
        $this->buildModuleLesson($this->company->id, $course['module']);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('data.0.id', $course['course']->id)
            ->assertJsonPath('data.0.completed', true)
            ->assertJsonPath('data.0.total_lessons', 2)
            ->assertJsonPath('data.0.completed_lessons', 1)
            ->assertJsonPath('data.0.new_lessons_since_completion', 1);
    }

    public function test_student_who_has_not_finished_must_complete_new_lessons(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $extra = $this->buildModuleLesson($this->company->id, $course['module']);

        $this->complete($course, $team, $course['lesson']);
        $this->assertSame(0, CertificateUser::query()->count());
        $this->assertSame(0, CourseCompletion::query()->count());

        $this->complete($course, $team, $extra);
        $this->assertSame(1, CertificateUser::query()->count());
        $this->assertSame(1, CourseCompletion::query()->count());
    }

    public function test_invalidated_certificate_is_not_reissued_automatically(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->complete($course, $team, $course['lesson']);

        $certificate = CertificateUser::query()->firstOrFail();
        $certificate->forceFill([
            'invalidated_at' => now(),
            'invalidation_reason' => 'Dados incorretos',
        ])->save();

        $extra = $this->buildModuleLesson($this->company->id, $course['module']);
        $this->complete($course, $team, $extra);

        $this->assertSame(1, CertificateUser::query()->count());
        $this->assertNotNull($certificate->fresh()->invalidated_at);
    }

    public function test_manual_revocation_keeps_the_course_completed(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->complete($course, $team, $course['lesson']);

        $certificate = CertificateUser::query()->firstOrFail();

        $this->withToken($this->apiLogin($this->admin))
            ->postJson("/api/v1/admin/certificate-users/{$certificate->id}/revoke", ['reason' => 'Dados incorretos'])
            ->assertOk();

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('data.0.completed', true);

        // Re-completing never re-issues a revoked certificate.
        $this->complete($course, $team, $course['lesson']);
        $this->assertSame(1, CertificateUser::query()->count());
    }

    public function test_creating_a_template_late_issues_certificate_retroactively(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        Certificate::query()->where('course_id', $course['course']->id)->delete();

        $this->complete($course, $team, $course['lesson']);

        $this->assertSame(0, CertificateUser::query()->count());
        $this->assertSame(1, CourseCompletion::query()->count());

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/certificates', [
                'name' => 'Modelo',
                'title' => 'Certificado',
                'course_id' => $course['course']->id,
            ])
            ->assertCreated();

        $this->assertSame(1, CertificateUser::query()->count());
    }

    public function test_issuance_failure_keeps_completion_and_heals_later(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        app(CompanyContext::class)->set((int) $this->company->id);

        $mock = Mockery::mock(CertificateService::class);
        $mock->shouldReceive('issueIfEligible')->andThrow(new RuntimeException('boom'));

        $service = $this->progressServiceWith($mock);

        try {
            $service->completeLesson($this->student, new CompleteLessonDTO(
                $course['lesson']->id,
                $course['course']->id,
                $team->id,
                $course['module']->id,
            ));

            $this->fail('Issuance should have thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        // Completion is not lost even though issuance failed.
        $this->assertSame(1, CourseCompletion::query()->count());
        $this->assertSame(0, CertificateUser::query()->count());

        // Healing: a later issuance for the completed student succeeds.
        app(CertificateService::class)->issueIfEligible($this->student, $course['course']->id, $team->id);
        $this->assertSame(1, CertificateUser::query()->count());
    }

    private function progressServiceWith(CertificateService $certificates): ProgressService
    {
        return new ProgressService(
            app(TimelineRepository::class),
            app(LessonRepository::class),
            app(ActionRepository::class),
            app(StudentCourseService::class),
            $certificates,
            app(DatalogService::class),
            app(VideoProgressRepository::class),
            app(StudentLessonProgressRepository::class),
            app(CourseCompletionRepository::class),
        );
    }

    public function test_concurrent_completion_is_idempotent(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->complete($course, $team, $course['lesson']);

        // Duplicate completion row is rejected by the DB unique.
        $timeline = DB::table('timelines')->first();

        try {
            DB::table('timelines')->insert([
                'user_id' => $timeline->user_id,
                'company_id' => $timeline->company_id,
                'module_id' => $timeline->module_id,
                'lesson_id' => $timeline->lesson_id,
                'course_id' => $timeline->course_id,
                'action_id' => $timeline->action_id,
                'team_id' => $timeline->team_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('A duplicate timeline should be rejected by the unique index.');
        } catch (QueryException $e) {
            $this->assertTrue(true);
        }

        // Duplicate completion and issuance are idempotent.
        app(CourseCompletionRepository::class)->markCompleted(
            $this->student->id,
            $team->id,
            $course['course']->id,
            $this->company->id,
            1,
            [$course['lesson']->id],
        );

        app(CertificateService::class)->issueIfEligible($this->student, $course['course']->id, $team->id);

        $this->assertSame(1, CourseCompletion::query()->count());
        $this->assertSame(1, CertificateUser::query()->count());
    }

    public function test_counted_lessons_ignore_inactive_module(): void
    {
        $course = $this->buildCourse($this->company->id);

        $inactiveModule = Module::factory()->create([
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Inactive->value,
        ]);
        $topic = Topic::factory()->create([
            'company_id' => $this->company->id,
            'module_id' => $inactiveModule->id,
        ]);
        Lesson::factory()->create([
            'company_id' => $this->company->id,
            'topic_id' => $topic->id,
            'status' => GeneralStatus::Active->value,
        ]);
        $course['course']->modules()->attach($inactiveModule->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Inactive->value,
            'sort_order' => 1,
        ]);

        $this->assertSame(1, app(LessonRepository::class)->countPublishedByCourse($course['course']->id));
    }

    public function test_backfill_recovers_automatically_revoked_certificates(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->complete($course, $team, $course['lesson']);

        $certificate = CertificateUser::query()->firstOrFail();
        $certificate->forceFill([
            'invalidated_at' => now(),
            'invalidation_reason' => RestoreRecalculatedCertificates::AUTOMATIC_REASON,
        ])->save();

        // Simulate a legacy state with certificates but no completion records.
        CourseCompletion::query()->delete();

        $this->artisan('completions:backfill', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, CourseCompletion::query()->count());

        $this->artisan('completions:backfill')->assertSuccessful();
        $this->assertSame(1, CourseCompletion::query()->count());
    }

    public function test_backfill_does_not_create_completion_for_manual_revocation(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->complete($course, $team, $course['lesson']);

        CertificateUser::query()->firstOrFail()->forceFill([
            'invalidated_at' => now(),
            'invalidation_reason' => 'Dados incorretos',
        ])->save();

        CourseCompletion::query()->delete();

        $this->artisan('completions:backfill')->assertSuccessful();
        $this->assertSame(0, CourseCompletion::query()->count());
    }

    public function test_restore_command_revalidates_only_automatically_revoked_certificates(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->complete($course, $team, $course['lesson']);

        $automatic = CertificateUser::query()->firstOrFail();
        $automatic->forceFill([
            'invalidated_at' => now(),
            'invalidation_reason' => RestoreRecalculatedCertificates::AUTOMATIC_REASON,
        ])->save();

        $manual = CertificateUser::query()->create([
            'code' => (string) Str::uuid(),
            'company_id' => $this->company->id,
            'user_id' => $this->student->id,
            'team_id' => $team->id,
            'certificate_id' => $automatic->certificate_id,
            'lessons_count' => 1,
            'invalidated_at' => now(),
            'invalidation_reason' => 'Dados incorretos',
        ]);

        $this->artisan('certificates:restore-recalculated', ['--dry-run' => true])->assertSuccessful();
        $this->assertNotNull($automatic->fresh()->invalidated_at);

        $this->artisan('certificates:restore-recalculated')->assertSuccessful();

        $this->assertNull($automatic->fresh()->invalidated_at);
        $this->assertNotNull($manual->fresh()->invalidated_at);
    }
}
