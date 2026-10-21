<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->getJson('/api/v1/admin/comments?roots_only=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.stats.total', 1)
            ->assertJsonPath('meta.stats.awaiting', 1)
            ->assertJsonPath('meta.stats.answered', 0)
            ->assertJsonPath('data.0.course.id', $course['course']->id)
            ->assertJsonPath('data.0.course.name', $course['course']->name)
            ->assertJsonPath('data.0.module.id', $course['module']->id)
            ->assertJsonPath('data.0.lesson.name', $course['lesson']->name)
            ->assertJsonPath('data.0.awaiting_reply', true)
            ->assertJsonPath('data.0.replies_count', 0);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/comments/{$commentId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $commentId]);
    }

    public function test_admin_comment_list_tracks_team_replies_and_status_filters(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $studentToken = $this->apiLogin($this->student);

        $rootId = $this->withToken($studentToken)
            ->postJson('/api/v1/comments', [
                'content' => 'Comentario sem resposta',
                'lesson_id' => $course['lesson']->id,
                'module_id' => $course['module']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/comments', [
                'parent_id' => $rootId,
                'content' => 'Resposta da equipe',
            ])
            ->assertCreated();

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/comments?roots_only=1')
            ->assertOk()
            ->assertJsonPath('meta.stats.awaiting', 0)
            ->assertJsonPath('meta.stats.answered', 1)
            ->assertJsonPath('data.0.awaiting_reply', false)
            ->assertJsonPath('data.0.replies_count', 1)
            ->assertJsonPath('data.0.replies.0.is_admin', true);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/comments?roots_only=1&status=awaiting')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/comments?roots_only=1&status=answered')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $rootId);
    }

    public function test_admin_can_bulk_delete_comments_and_replies(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);

        $rootId = $this->withToken($token)
            ->postJson('/api/v1/comments', [
                'content' => 'Comentario principal',
                'lesson_id' => $course['lesson']->id,
                'module_id' => $course['module']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $replyId = $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/comments', [
                'parent_id' => $rootId,
                'content' => 'Resposta da equipe',
            ])
            ->assertCreated()
            ->json('data.id');

        $secondId = $this->withToken($token)
            ->postJson('/api/v1/comments', [
                'content' => 'Outro comentario principal',
                'lesson_id' => $course['lesson']->id,
                'module_id' => $course['module']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson('/api/v1/admin/comments/bulk', ['ids' => [$rootId, $secondId]])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        $this->assertDatabaseMissing('comments', ['id' => $rootId]);
        $this->assertDatabaseMissing('comments', ['id' => $replyId]);
        $this->assertDatabaseMissing('comments', ['id' => $secondId]);
    }

    public function test_student_cannot_bulk_delete_comments(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->deleteJson('/api/v1/admin/comments/bulk', ['ids' => [1]])
            ->assertForbidden();
    }

    public function test_bulk_delete_cannot_reach_another_company(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $rootId = $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/comments', [
                'content' => 'Comentario da empresa',
                'lesson_id' => $course['lesson']->id,
                'module_id' => $course['module']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $otherCompany = Company::factory()->create();
        $foreignId = DB::table('comments')->insertGetId([
            'company_id' => $otherCompany->id,
            'content' => 'Comentario de outra empresa',
            'lesson_id' => $course['lesson']->id,
            'module_id' => $course['module']->id,
            'course_id' => $course['course']->id,
            'team_id' => $team->id,
            'user_id' => $this->student->id,
            'parent_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson('/api/v1/admin/comments/bulk', ['ids' => [$rootId, $foreignId]])
            ->assertNotFound();

        $this->assertDatabaseHas('comments', ['id' => $rootId]);
        $this->assertDatabaseHas('comments', ['id' => $foreignId]);
    }

    public function test_disabled_lesson_comments_are_hidden_and_blocked(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);

        $payload = [
            'content' => 'Comentário da aula',
            'lesson_id' => $course['lesson']->id,
            'module_id' => $course['module']->id,
            'course_id' => $course['course']->id,
            'team_id' => $team->id,
        ];

        $this->withToken($token)->postJson('/api/v1/comments', $payload)->assertCreated();

        $course['lesson']->update(['comments_enabled' => false]);

        $this->withToken($token)
            ->getJson("/api/v1/comments?lesson_id={$course['lesson']->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withToken($token)->postJson('/api/v1/comments', $payload)->assertForbidden();

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/comments')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $course['lesson']->update(['comments_enabled' => true]);

        $this->withToken($token)
            ->getJson("/api/v1/comments?lesson_id={$course['lesson']->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
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
