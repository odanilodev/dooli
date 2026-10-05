<?php

namespace Tests\Feature;

use App\Enums\ActionCode;
use App\Enums\GeneralStatus;
use App\Models\Action;
use App\Models\Lesson;
use App\Models\Team;
use App\Models\Timeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StudentLessonStartTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    private function actionId(ActionCode $code): int
    {
        return Action::query()
            ->where('company_id', $this->company->id)
            ->where('code', $code->value)
            ->value('id');
    }

    public function test_opening_a_lesson_records_started_and_viewed_actions(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/start', [
                'lesson_id' => $course['lesson']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.already_started', false);

        $base = [
            'user_id' => $this->student->id,
            'company_id' => $this->company->id,
            'module_id' => $course['module']->id,
            'lesson_id' => $course['lesson']->id,
            'course_id' => $course['course']->id,
            'team_id' => $team->id,
        ];

        $this->assertDatabaseHas('timelines', $base + ['action_id' => $this->actionId(ActionCode::Started)]);
        $this->assertDatabaseHas('timelines', $base + ['action_id' => $this->actionId(ActionCode::Viewed)]);
    }

    public function test_started_is_written_once_while_viewed_updates_the_same_row(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);

        $payload = [
            'lesson_id' => $course['lesson']->id,
            'course_id' => $course['course']->id,
            'team_id' => $team->id,
        ];

        $viewedId = fn () => Timeline::query()
            ->where('user_id', $this->student->id)
            ->where('lesson_id', $course['lesson']->id)
            ->where('action_id', $this->actionId(ActionCode::Viewed))
            ->value('id');

        $this->withToken($token)->postJson('/api/v1/me/progress/start', $payload)
            ->assertOk()
            ->assertJsonPath('data.already_started', false);

        $firstViewedId = $viewedId();

        $this->withToken($token)->postJson('/api/v1/me/progress/start', $payload)
            ->assertOk()
            ->assertJsonPath('data.already_started', true);

        // One "started" + one "viewed" row, and the viewed row is the same one.
        $this->assertDatabaseCount('timelines', 2);
        $this->assertSame(1, Timeline::query()
            ->where('user_id', $this->student->id)
            ->where('lesson_id', $course['lesson']->id)
            ->where('action_id', $this->actionId(ActionCode::Started))
            ->count());
        $this->assertSame($firstViewedId, $viewedId());
    }

    public function test_progress_endpoint_exposes_started_lesson_ids(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $token = $this->apiLogin($this->student);

        $this->withToken($token)
            ->getJson("/api/v1/me/courses/{$course['course']->id}/progress?team_id={$team->id}")
            ->assertOk()
            ->assertJsonPath('data.started_lesson_ids', []);

        $this->withToken($token)->postJson('/api/v1/me/progress/start', [
            'lesson_id' => $course['lesson']->id,
            'course_id' => $course['course']->id,
            'team_id' => $team->id,
        ])->assertOk();

        $this->withToken($token)
            ->getJson("/api/v1/me/courses/{$course['course']->id}/progress?team_id={$team->id}")
            ->assertOk()
            ->assertJsonPath('data.started_lesson_ids.0', $course['lesson']->id);
    }

    public function test_starting_a_lesson_requires_enrollment(): void
    {
        $course = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/start', [
                'lesson_id' => $course['lesson']->id,
                'course_id' => $course['course']->id,
                'team_id' => Team::factory()->create(['company_id' => $this->company->id])->id,
            ])
            ->assertStatus(403);
    }

    public function test_lesson_from_another_course_cannot_be_started(): void
    {
        $enrolled = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $enrolled['course'], $this->company->id);
        $other = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/start', [
                'lesson_id' => $other['lesson']->id,
                'course_id' => $enrolled['course']->id,
                'team_id' => $team->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('timelines', 0);
    }

    public function test_locked_lesson_cannot_be_started(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $lockedLesson = Lesson::factory()->create([
            'company_id' => $this->company->id,
            'topic_id' => $course['topic']->id,
            'status' => GeneralStatus::Active->value,
            'required_lesson_id' => $course['lesson']->id,
        ]);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/start', [
                'lesson_id' => $lockedLesson->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('timelines', 0);
    }
}
