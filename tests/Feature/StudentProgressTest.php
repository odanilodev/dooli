<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StudentProgressTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_student_sees_only_enrolled_courses(): void
    {
        $course = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $course['course'], $this->company->id);

        // Another course the student is NOT enrolled in.
        $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_my_courses_list_includes_progress_summary(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);

        $this->withToken($token)
            ->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('data.0.total_lessons', 1)
            ->assertJsonPath('data.0.completed_lessons', 0)
            ->assertJsonPath('data.0.percentage', 0);

        $this->withToken($token)
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $course['lesson']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
                'module_id' => $course['module']->id,
            ])
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('data.0.completed_lessons', 1)
            ->assertJsonPath('data.0.percentage', 100);
    }

    public function test_student_cannot_access_course_without_enrollment(): void
    {
        $course = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/courses/{$course['course']->id}")
            ->assertStatus(403);
    }

    public function test_completing_all_lessons_issues_a_certificate(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);

        $this->withToken($token)
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $course['lesson']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
                'module_id' => $course['module']->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.progress.percentage', 100);

        $this->withToken($token)
            ->getJson('/api/v1/me/certificates')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_completing_lesson_is_idempotent(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);

        $payload = [
            'lesson_id' => $course['lesson']->id,
            'course_id' => $course['course']->id,
            'team_id' => $team->id,
            'module_id' => $course['module']->id,
        ];

        $this->withToken($token)->postJson('/api/v1/me/progress/complete', $payload)->assertOk();
        $this->withToken($token)->postJson('/api/v1/me/progress/complete', $payload)
            ->assertOk()
            ->assertJsonPath('data.already_completed', true);

        $this->assertDatabaseCount('timelines', 1);
    }

    public function test_progress_endpoint_requires_enrollment(): void
    {
        $course = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/courses/{$course['course']->id}/progress")
            ->assertStatus(403);
    }
}
