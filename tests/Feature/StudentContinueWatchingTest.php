<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StudentContinueWatchingTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_continue_watching_returns_the_next_incomplete_lesson(): void
    {
        $data = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $data['course'], $this->company->id);

        $video = Video::factory()->create(['company_id' => $this->company->id, 'time' => 1250]);

        $second = Lesson::factory()->create([
            'company_id' => $this->company->id,
            'topic_id' => $data['topic']->id,
            'sort_order' => 1,
            'video_id' => $video->id,
        ]);
        Lesson::factory()->create([
            'company_id' => $this->company->id,
            'topic_id' => $data['topic']->id,
            'sort_order' => 2,
        ]);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $data['lesson']->id,
                'course_id' => $data['course']->id,
                'team_id' => $team->id,
                'module_id' => $data['module']->id,
            ])
            ->assertOk();

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/continue-watching')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.course_id', $data['course']->id)
            ->assertJsonPath('data.0.lesson_id', $second->id)
            ->assertJsonPath('data.0.lesson_name', $second->name)
            ->assertJsonPath('data.0.module_name', $data['module']->name)
            ->assertJsonPath('data.0.duration', 1250)
            ->assertJsonPath('data.0.percentage', 33.33);
    }

    public function test_continue_watching_prefers_the_lesson_with_saved_video_progress(): void
    {
        $data = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $data['course'], $this->company->id);

        $video = Video::factory()->create(['company_id' => $this->company->id, 'time' => 500]);

        $second = Lesson::factory()->create([
            'company_id' => $this->company->id,
            'topic_id' => $data['topic']->id,
            'sort_order' => 1,
            'video_id' => $video->id,
        ]);

        $token = $this->apiLogin($this->student);

        $this->withToken($token)
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $data['lesson']->id,
                'course_id' => $data['course']->id,
                'team_id' => $team->id,
                'module_id' => $data['module']->id,
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/me/progress/video-position', [
                'lesson_id' => $second->id,
                'course_id' => $data['course']->id,
                'team_id' => $team->id,
                'position' => 42,
                'duration' => 500,
            ])
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/me/continue-watching')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lesson_id', $second->id)
            ->assertJsonPath('data.0.lesson_name', $second->name)
            ->assertJsonPath('data.0.resume_from', 42)
            ->assertJsonPath('data.0.duration', 500);
    }

    public function test_continue_watching_shows_a_started_course_without_completed_lessons(): void
    {
        $data = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $data['course'], $this->company->id);

        $video = Video::factory()->create(['company_id' => $this->company->id, 'time' => 300]);
        $data['lesson']->update(['video_id' => $video->id]);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/video-position', [
                'lesson_id' => $data['lesson']->id,
                'course_id' => $data['course']->id,
                'team_id' => $team->id,
                'position' => 77,
                'duration' => 300,
            ])
            ->assertOk();

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/continue-watching')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.course_id', $data['course']->id)
            ->assertJsonPath('data.0.lesson_id', $data['lesson']->id)
            ->assertJsonPath('data.0.lesson_name', $data['lesson']->name)
            ->assertJsonPath('data.0.resume_from', 77)
            ->assertJsonPath('data.0.duration', 300);
    }

    public function test_continue_watching_ignores_not_started_and_finished_courses(): void
    {
        $notStarted = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $notStarted['course'], $this->company->id);

        $finished = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $finished['course'], $this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $finished['lesson']->id,
                'course_id' => $finished['course']->id,
                'team_id' => $team->id,
                'module_id' => $finished['module']->id,
            ])
            ->assertOk();

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/continue-watching')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_continue_watching_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/continue-watching')->assertStatus(401);
    }
}
