<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Team;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class CommentReplyTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_student_can_reply_to_comment_when_enabled(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);
        $rootId = $this->createComment($token, $course, $team, 'Comentário principal');

        $replyId = $this->withToken($token)
            ->postJson('/api/v1/comments', [
                'parent_id' => $rootId,
                'content' => 'Minha resposta',
            ])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $rootId)
            ->assertJsonPath('data.is_admin', false)
            ->json('data.id');

        $this->assertDatabaseHas('comments', [
            'id' => $replyId,
            'parent_id' => $rootId,
            'lesson_id' => $course['lesson']->id,
            'user_id' => $this->student->id,
        ]);

        $this->withToken($token)
            ->getJson("/api/v1/comments?lesson_id={$course['lesson']->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $rootId)
            ->assertJsonPath('data.0.replies.0.id', $replyId)
            ->assertJsonPath('data.0.replies.0.content', 'Minha resposta');
    }

    public function test_student_reply_is_blocked_when_disabled_and_admin_can_still_reply(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $studentToken = $this->apiLogin($this->student);
        $rootId = $this->createComment($studentToken, $course, $team, 'Comentário principal');

        $course['lesson']->update(['student_replies_enabled' => false]);

        $this->withToken($studentToken)
            ->postJson('/api/v1/comments', [
                'parent_id' => $rootId,
                'content' => 'Resposta bloqueada',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'As respostas de alunos estão desativadas para esta aula.');

        $replyId = $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/comments', [
                'parent_id' => $rootId,
                'content' => 'Resposta da equipe',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_admin', true)
            ->json('data.id');

        $this->withToken($studentToken)
            ->getJson("/api/v1/comments?lesson_id={$course['lesson']->id}")
            ->assertOk()
            ->assertJsonPath('data.0.replies.0.id', $replyId)
            ->assertJsonPath('data.0.replies.0.is_admin', true);
    }

    public function test_student_cannot_reply_to_another_reply(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);
        $rootId = $this->createComment($token, $course, $team, 'Comentário principal');

        $replyId = $this->withToken($token)
            ->postJson('/api/v1/comments', [
                'parent_id' => $rootId,
                'content' => 'Primeira resposta',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/comments', [
                'parent_id' => $replyId,
                'content' => 'Resposta aninhada',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_student_must_be_enrolled_to_reply(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $rootId = $this->createComment($this->apiLogin($this->student), $course, $team, 'Comentário principal');

        $outsider = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $this->withToken($this->apiLogin($outsider))
            ->postJson('/api/v1/comments', [
                'parent_id' => $rootId,
                'content' => 'Tentativa externa',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não possui acesso a este curso.');
    }

    public function test_admin_can_list_roots_with_replies(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);
        $rootId = $this->createComment($token, $course, $team, 'Comentário principal');

        $replyId = $this->withToken($token)
            ->postJson('/api/v1/comments', [
                'parent_id' => $rootId,
                'content' => 'Resposta do aluno',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/comments?roots_only=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $rootId)
            ->assertJsonPath('data.0.lesson.id', $course['lesson']->id)
            ->assertJsonPath('data.0.replies.0.id', $replyId);
    }

    public function test_student_cannot_reply_through_admin_endpoint(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);
        $rootId = $this->createComment($token, $course, $team, 'Comentário principal');

        $this->withToken($token)
            ->postJson('/api/v1/admin/comments', [
                'parent_id' => $rootId,
                'content' => 'Resposta indevida',
            ])
            ->assertForbidden();
    }

    public function test_deleting_root_comment_removes_replies(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);
        $rootId = $this->createComment($token, $course, $team, 'Comentário principal');

        $replyId = $this->withToken($token)
            ->postJson('/api/v1/comments', [
                'parent_id' => $rootId,
                'content' => 'Resposta do aluno',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/comments/{$rootId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $rootId]);
        $this->assertDatabaseMissing('comments', ['id' => $replyId]);
    }

    public function test_student_can_delete_own_comment_and_all_replies(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);
        $rootId = $this->createComment($token, $course, $team, 'Comentário principal');

        $studentReplyId = $this->withToken($token)
            ->postJson('/api/v1/comments', [
                'parent_id' => $rootId,
                'content' => 'Resposta do aluno',
            ])
            ->assertCreated()
            ->json('data.id');

        $adminReplyId = $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/comments', [
                'parent_id' => $rootId,
                'content' => 'Resposta da equipe',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->deleteJson("/api/v1/comments/{$rootId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $rootId]);
        $this->assertDatabaseMissing('comments', ['id' => $studentReplyId]);
        $this->assertDatabaseMissing('comments', ['id' => $adminReplyId]);
    }

    public function test_student_can_delete_own_reply(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);
        $rootId = $this->createComment($token, $course, $team, 'Comentário principal');

        $replyId = $this->withToken($token)
            ->postJson('/api/v1/comments', [
                'parent_id' => $rootId,
                'content' => 'Minha resposta',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->deleteJson("/api/v1/comments/{$replyId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $replyId]);
        $this->assertDatabaseHas('comments', ['id' => $rootId]);
    }

    public function test_student_cannot_delete_another_students_comment(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $rootId = $this->createComment($this->apiLogin($this->student), $course, $team, 'Comentário principal');

        $otherStudent = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $this->withToken($this->apiLogin($otherStudent))
            ->deleteJson("/api/v1/comments/{$rootId}")
            ->assertForbidden();

        $this->assertDatabaseHas('comments', ['id' => $rootId]);
    }

    public function test_student_cannot_delete_a_reply_from_another_author(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);
        $rootId = $this->createComment($token, $course, $team, 'Comentário principal');

        $adminReplyId = $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/comments', [
                'parent_id' => $rootId,
                'content' => 'Resposta da equipe',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->deleteJson("/api/v1/comments/{$adminReplyId}")
            ->assertForbidden();

        $this->assertDatabaseHas('comments', ['id' => $adminReplyId]);
    }

    /**
     * @param  array{course: Course, module: Module, topic: Topic, lesson: Lesson}  $course
     */
    private function createComment(string $token, array $course, Team $team, string $content): int
    {
        return $this->withToken($token)
            ->postJson('/api/v1/comments', [
                'content' => $content,
                'lesson_id' => $course['lesson']->id,
                'module_id' => $course['module']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
            ])
            ->assertCreated()
            ->json('data.id');
    }
}
