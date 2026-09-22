<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminModerationTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_admin_can_list_and_delete_comments(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $commentId = $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/comments', [
                'content' => 'Comentário de teste',
                'lesson_id' => $course['lesson']->id,
                'module_id' => $course['module']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/comments')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/comments/{$commentId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $commentId]);
    }

    public function test_admin_can_list_and_delete_ratings(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $ratingId = $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/ratings', [
                'rating' => 9,
                'lesson_id' => $course['lesson']->id,
                'module_id' => $course['module']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/ratings')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/ratings/{$ratingId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('ratings', ['id' => $ratingId]);
    }

    public function test_student_cannot_moderate_comments(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/comments')
            ->assertStatus(403);
    }

    public function test_admin_can_read_audit_log(): void
    {
        // Any audited action writes to "datalog" (profile update is audited).
        $this->withToken($this->apiLogin($this->admin))
            ->putJson('/api/v1/me/profile', ['name' => 'Admin Auditado'])
            ->assertOk();

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/datalogs')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('datalogs', 1);
    }
}
