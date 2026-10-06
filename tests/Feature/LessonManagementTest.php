<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\Comment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Rating;
use App\Models\Team;
use App\Models\Timeline;
use App\Models\Topic;
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
}
