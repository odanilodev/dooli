<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Models\Action;
use App\Models\Comment;
use App\Models\Course;
use App\Models\Document;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Rating;
use App\Models\Team;
use App\Models\Timeline;
use App\Models\Topic;
use App\Models\Video;
use App\Models\VideoProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class LessonManagementTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    /**
     * Seeds one row in every table that constrains a lesson by foreign key, so
     * deletion must cascade instead of hitting a foreign-key restriction.
     *
     * @param  array{course: Course, module: Module, topic: Topic, lesson: Lesson}  $data
     */
    private function seedLessonActivity(array $data): void
    {
        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $actionId = Action::query()->value('id');

        Comment::create([
            'company_id' => $this->company->id,
            'content' => 'Comentário da aula',
            'lesson_id' => $data['lesson']->id,
            'module_id' => $data['module']->id,
            'course_id' => $data['course']->id,
            'team_id' => $team->id,
            'user_id' => $this->student->id,
        ]);

        Rating::create([
            'company_id' => $this->company->id,
            'rating' => 5,
            'lesson_id' => $data['lesson']->id,
            'module_id' => $data['module']->id,
            'course_id' => $data['course']->id,
            'team_id' => $team->id,
            'user_id' => $this->student->id,
        ]);

        Timeline::create([
            'user_id' => $this->student->id,
            'company_id' => $this->company->id,
            'module_id' => $data['module']->id,
            'lesson_id' => $data['lesson']->id,
            'course_id' => $data['course']->id,
            'action_id' => $actionId,
            'team_id' => $team->id,
        ]);

        VideoProgress::create([
            'user_id' => $this->student->id,
            'company_id' => $this->company->id,
            'lesson_id' => $data['lesson']->id,
            'course_id' => $data['course']->id,
            'team_id' => $team->id,
            'position_seconds' => 30,
        ]);
    }

    public function test_admin_can_delete_lesson_with_related_data(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->seedLessonActivity($data);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/lessons/{$data['lesson']->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('lessons', ['id' => $data['lesson']->id]);
        $this->assertDatabaseMissing('comments', ['lesson_id' => $data['lesson']->id]);
        $this->assertDatabaseMissing('ratings', ['lesson_id' => $data['lesson']->id]);
        $this->assertDatabaseMissing('timelines', ['lesson_id' => $data['lesson']->id]);
        $this->assertDatabaseMissing('video_progresses', ['lesson_id' => $data['lesson']->id]);
    }

    public function test_admin_can_delete_topic_with_lessons_having_related_data(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->seedLessonActivity($data);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/topics/{$data['topic']->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('topics', ['id' => $data['topic']->id]);
        $this->assertDatabaseMissing('lessons', ['id' => $data['lesson']->id]);
        $this->assertDatabaseMissing('timelines', ['lesson_id' => $data['lesson']->id]);
        $this->assertDatabaseMissing('video_progresses', ['lesson_id' => $data['lesson']->id]);
    }

    public function test_admin_can_delete_module_with_lessons_having_related_data(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->seedLessonActivity($data);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/modules/{$data['module']->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('modules', ['id' => $data['module']->id]);
        $this->assertDatabaseMissing('topics', ['id' => $data['topic']->id]);
        $this->assertDatabaseMissing('lessons', ['id' => $data['lesson']->id]);
        $this->assertDatabaseMissing('comments', ['lesson_id' => $data['lesson']->id]);
        $this->assertDatabaseMissing('ratings', ['lesson_id' => $data['lesson']->id]);
    }

    public function test_admin_can_flag_lesson_as_requiring_full_watch(): void
    {
        $data = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/lessons/{$data['lesson']->id}", [
                'require_full_watch' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.require_full_watch', true);

        $this->assertDatabaseHas('lessons', [
            'id' => $data['lesson']->id,
            'require_full_watch' => true,
        ]);
    }

    public function test_admin_can_disable_lesson_comments(): void
    {
        $data = $this->buildCourse($this->company->id);

        $this->assertTrue($data['lesson']->fresh()->comments_enabled);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/lessons/{$data['lesson']->id}", [
                'comments_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.comments_enabled', false);

        $this->assertDatabaseHas('lessons', [
            'id' => $data['lesson']->id,
            'comments_enabled' => false,
        ]);
    }

    public function test_student_course_content_exposes_comments_enabled(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $data['course'], $this->company->id);

        $data['lesson']->update(['comments_enabled' => false]);

        $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/courses/{$data['course']->id}")
            ->assertOk()
            ->assertJsonPath('data.modules.0.topics.0.lessons.0.comments_enabled', false);
    }

    public function test_admin_can_disable_student_replies(): void
    {
        $data = $this->buildCourse($this->company->id);

        $this->assertTrue($data['lesson']->fresh()->student_replies_enabled);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/lessons/{$data['lesson']->id}", [
                'student_replies_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.student_replies_enabled', false);

        $this->assertDatabaseHas('lessons', [
            'id' => $data['lesson']->id,
            'student_replies_enabled' => false,
        ]);
    }

    public function test_student_course_content_exposes_student_replies_enabled(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $data['course'], $this->company->id);

        $data['lesson']->update(['student_replies_enabled' => false]);

        $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/courses/{$data['course']->id}")
            ->assertOk()
            ->assertJsonPath('data.modules.0.topics.0.lessons.0.student_replies_enabled', false);
    }

    public function test_lesson_content_type_is_derived_from_media_on_create(): void
    {
        $data = $this->buildCourse($this->company->id);
        $video = Video::factory()->create(['company_id' => $this->company->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/lessons', [
                'name' => 'Aula com vídeo',
                'module_id' => $data['module']->id,
                'video_id' => $video->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.content_type', 'video');
    }

    public function test_document_content_type_requires_a_document(): void
    {
        $data = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/lessons/{$data['lesson']->id}", [
                'content_type' => 'document',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document_id');
    }

    public function test_switching_lesson_to_document_clears_video_and_full_watch(): void
    {
        $data = $this->buildCourse($this->company->id);
        $video = Video::factory()->create(['company_id' => $this->company->id]);
        $document = Document::create([
            'company_id' => $this->company->id,
            'path' => 'documentos/aula.pdf',
            'status' => GeneralStatus::Active->value,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/lessons/{$data['lesson']->id}", [
                'content_type' => 'video',
                'video_id' => $video->id,
                'require_full_watch' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.content_type', 'video')
            ->assertJsonPath('data.require_full_watch', true);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/lessons/{$data['lesson']->id}", [
                'content_type' => 'document',
                'document_id' => $document->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.content_type', 'document')
            ->assertJsonPath('data.require_full_watch', false);

        $this->assertDatabaseHas('lessons', [
            'id' => $data['lesson']->id,
            'content_type' => 'document',
            'video_id' => null,
            'document_id' => $document->id,
            'require_full_watch' => false,
        ]);
    }

    public function test_switching_content_type_clears_html_content(): void
    {
        $data = $this->buildCourse($this->company->id);
        $video = Video::factory()->create(['company_id' => $this->company->id]);

        $data['lesson']->update([
            'content' => '<p>Conteúdo antigo</p>',
            'content_type' => null,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/lessons/{$data['lesson']->id}", [
                'content_type' => 'video',
                'video_id' => $video->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.content', null);

        $this->assertDatabaseHas('lessons', [
            'id' => $data['lesson']->id,
            'content_type' => 'video',
            'content' => null,
        ]);
    }

    public function test_keeping_content_type_preserves_html_content(): void
    {
        $data = $this->buildCourse($this->company->id);
        $video = Video::factory()->create(['company_id' => $this->company->id]);

        $data['lesson']->update([
            'content' => '<p>Legado</p>',
            'content_type' => 'video',
            'video_id' => $video->id,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/lessons/{$data['lesson']->id}", [
                'name' => 'Aula editada',
                'content_type' => 'video',
                'video_id' => $video->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.content', '<p>Legado</p>');

        $this->assertDatabaseHas('lessons', [
            'id' => $data['lesson']->id,
            'content_type' => 'video',
            'content' => '<p>Legado</p>',
        ]);
    }
}
