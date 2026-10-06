<?php

namespace Tests\Feature;

use App\Models\Timeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class ModuleLessonTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_admin_can_create_a_lesson_without_a_topic(): void
    {
        $data = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/lessons', [
                'name' => 'Aula solta',
                'module_id' => $data['module']->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.module_id', $data['module']->id)
            ->assertJsonPath('data.topic_id', null);
    }

    public function test_admin_can_derive_the_module_from_the_topic(): void
    {
        $data = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/lessons', [
                'name' => 'Aula com tópico',
                'topic_id' => $data['topic']->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.module_id', $data['module']->id)
            ->assertJsonPath('data.topic_id', $data['topic']->id);
    }

    public function test_lesson_rejects_a_topic_from_another_module(): void
    {
        $data = $this->buildCourse($this->company->id);
        $other = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/lessons', [
                'name' => 'Aula inválida',
                'module_id' => $data['module']->id,
                'topic_id' => $other['topic']->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('topic_id');
    }

    public function test_admin_can_reorder_module_lessons(): void
    {
        $data = $this->buildCourse($this->company->id);
        $first = $this->buildModuleLesson($this->company->id, $data['module'], ['name' => 'Primeira']);
        $second = $this->buildModuleLesson($this->company->id, $data['module'], ['name' => 'Segunda', 'sort_order' => 1]);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/modules/{$data['module']->id}/lessons/reorder", [
                'ids' => [$second->id, $first->id],
            ])
            ->assertOk();

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(0, $second->fresh()->sort_order);
    }

    public function test_student_curriculum_includes_module_lessons(): void
    {
        $data = $this->buildCourse($this->company->id);
        $loose = $this->buildModuleLesson($this->company->id, $data['module']);
        $this->enroll($this->student, $data['course'], $this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/courses/{$data['course']->id}")
            ->assertOk()
            ->assertJsonPath('data.modules.0.lessons.0.id', $loose->id)
            ->assertJsonPath('data.modules.0.topics.0.lessons.0.id', $data['lesson']->id);
    }

    public function test_student_can_complete_a_module_lesson(): void
    {
        $data = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $data['course'], $this->company->id);
        $loose = $this->buildModuleLesson($this->company->id, $data['module']);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/complete', [
                'course_id' => $data['course']->id,
                'team_id' => $team->id,
                'module_id' => $data['module']->id,
                'lesson_id' => $loose->id,
            ])
            ->assertOk();

        $this->assertTrue(
            Timeline::query()
                ->where('user_id', $this->student->id)
                ->where('lesson_id', $loose->id)
                ->where('module_id', $data['module']->id)
                ->exists(),
        );
    }

    public function test_prerequisite_between_module_lessons_is_enforced(): void
    {
        $data = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $data['course'], $this->company->id);
        $first = $this->buildModuleLesson($this->company->id, $data['module'], ['name' => 'Primeira']);
        $second = $this->buildModuleLesson($this->company->id, $data['module'], ['name' => 'Segunda', 'sort_order' => 1, 'required_lesson_id' => $first->id]);

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
            ->postJson('/api/v1/me/progress/complete', $payload + ['lesson_id' => $first->id])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/me/progress/complete', $payload + ['lesson_id' => $second->id])
            ->assertOk();
    }

    public function test_admin_course_show_includes_module_lessons(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->buildModuleLesson($this->company->id, $data['module']);

        $response = $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/courses/{$data['course']->id}")
            ->assertOk();

        $this->assertSame(2, $this->countLessons($response->json('data.modules.0')));
    }

    public function test_moving_a_module_lesson_into_a_topic(): void
    {
        $data = $this->buildCourse($this->company->id);
        $loose = $this->buildModuleLesson($this->company->id, $data['module'], ['name' => 'Solta']);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/topics/{$data['topic']->id}/lessons/reorder", [
                'ids' => [$loose->id, $data['lesson']->id],
            ])
            ->assertOk();

        $this->assertSame($data['topic']->id, $loose->fresh()->topic_id);
        $this->assertSame(0, $loose->fresh()->sort_order);
    }

    public function test_moving_a_topic_lesson_back_to_the_module(): void
    {
        $data = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/modules/{$data['module']->id}/lessons/reorder", [
                'ids' => [$data['lesson']->id],
            ])
            ->assertOk();

        $this->assertNull($data['lesson']->fresh()->topic_id);
        $this->assertSame($data['module']->id, $data['lesson']->fresh()->module_id);
    }

    public function test_reorder_does_not_move_a_lesson_from_another_module(): void
    {
        $data = $this->buildCourse($this->company->id);
        $foreign = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/topics/{$data['topic']->id}/lessons/reorder", [
                'ids' => [$foreign['lesson']->id],
            ])
            ->assertOk();

        $this->assertSame($foreign['topic']->id, $foreign['lesson']->fresh()->topic_id);
    }

    /**
     * @param  array<string, mixed>  $module
     */
    private function countLessons(array $module): int
    {
        $topics = collect($module['topics'] ?? [])->sum(fn (array $topic) => count($topic['lessons'] ?? []));

        return $topics + count($module['lessons'] ?? []);
    }
}
