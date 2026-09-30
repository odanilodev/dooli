<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Topic;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class LessonPrerequisiteTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    /**
     * @param  array{course: Course, module: Module, topic: Topic, lesson: Lesson}  $data
     */
    private function addSecondLesson(array $data): Lesson
    {
        return Lesson::factory()->create([
            'company_id' => $this->company->id,
            'topic_id' => $data['topic']->id,
            'sort_order' => 1,
            'status' => GeneralStatus::Active->value,
        ]);
    }

    public function test_admin_can_set_a_prerequisite_lesson(): void
    {
        $data = $this->buildCourse($this->company->id);
        $second = $this->addSecondLesson($data);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/lessons/{$second->id}", ['required_lesson_id' => $data['lesson']->id])
            ->assertOk()
            ->assertJsonPath('data.required_lesson_id', $data['lesson']->id);

        $this->assertSame($data['lesson']->id, $second->fresh()->required_lesson_id);
    }

    public function test_admin_can_create_a_lesson_with_a_prerequisite(): void
    {
        $data = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/lessons', [
                'name' => 'Aula nova',
                'topic_id' => $data['topic']->id,
                'required_lesson_id' => $data['lesson']->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.required_lesson_id', $data['lesson']->id);
    }

    public function test_prerequisite_must_be_an_earlier_lesson(): void
    {
        $data = $this->buildCourse($this->company->id);
        $second = $this->addSecondLesson($data);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/lessons/{$data['lesson']->id}", ['required_lesson_id' => $second->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule');
    }

    public function test_prerequisite_cannot_be_the_lesson_itself(): void
    {
        $data = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/lessons/{$data['lesson']->id}", ['required_lesson_id' => $data['lesson']->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule');
    }

    public function test_prerequisite_must_belong_to_the_same_course(): void
    {
        $data = $this->buildCourse($this->company->id);
        $second = $this->addSecondLesson($data);

        $other = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/lessons/{$second->id}", ['required_lesson_id' => $other['lesson']->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule');
    }

    public function test_prerequisite_must_belong_to_the_same_company(): void
    {
        $data = $this->buildCourse($this->company->id);
        $second = $this->addSecondLesson($data);

        $foreign = Lesson::factory()->create();

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/lessons/{$second->id}", ['required_lesson_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('required_lesson_id');
    }

    public function test_reordering_that_breaks_the_prerequisite_asks_for_confirmation(): void
    {
        $data = $this->buildCourse($this->company->id);
        $second = $this->addSecondLesson($data);
        $second->update(['required_lesson_id' => $data['lesson']->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/topics/{$data['topic']->id}/lessons/reorder", [
                'ids' => [$second->id, $data['lesson']->id],
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'prerequisite_conflict')
            ->assertJsonPath('errors.conflicts.0.lesson_id', $second->id);

        $this->assertSame($data['lesson']->id, $second->fresh()->required_lesson_id);
    }

    public function test_force_reordering_removes_the_prerequisite_lock(): void
    {
        $data = $this->buildCourse($this->company->id);
        $second = $this->addSecondLesson($data);
        $second->update(['required_lesson_id' => $data['lesson']->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/topics/{$data['topic']->id}/lessons/reorder", [
                'ids' => [$second->id, $data['lesson']->id],
                'force' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.cleared.0', $second->id);

        $this->assertNull($second->fresh()->required_lesson_id);
    }

    public function test_student_cannot_complete_a_locked_lesson_until_prerequisite_is_done(): void
    {
        $data = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $data['course'], $this->company->id);
        $second = $this->addSecondLesson($data);
        $second->update(['required_lesson_id' => $data['lesson']->id]);

        $token = $this->apiLogin($this->student);

        $payload = [
            'course_id' => $data['course']->id,
            'team_id' => $team->id,
            'module_id' => $data['module']->id,
        ];

        $this->withToken($token)
            ->postJson('/api/v1/me/progress/complete', $payload + ['lesson_id' => $second->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule');

        $this->withToken($token)
            ->postJson('/api/v1/me/progress/complete', $payload + ['lesson_id' => $data['lesson']->id])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/me/progress/complete', $payload + ['lesson_id' => $second->id])
            ->assertOk();
    }

    public function test_student_cannot_open_the_video_of_a_locked_lesson(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $data['course'], $this->company->id);
        $second = $this->addSecondLesson($data);

        $video = Video::factory()->create(['company_id' => $this->company->id, 'player' => 'external', 'link' => 'https://example.com/video']);

        $second->update([
            'required_lesson_id' => $data['lesson']->id,
            'video_id' => $video->id,
        ]);

        $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/courses/{$data['course']->id}/lessons/{$second->id}/video")
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule');
    }
}
