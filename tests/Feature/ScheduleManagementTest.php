<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    private function schedule(string $level, int $id, $availableAt): void
    {
        Schedule::query()->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'schedulable_type' => $level,
            'schedulable_id' => $id,
            'available_at' => $availableAt,
        ]);
    }

    public function test_admin_can_schedule_an_item_and_read_the_tree(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->team = $this->enroll($this->student, $data['course'], $this->company->id);
        $when = now()->addDays(3)->startOfMinute();

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/teams/{$this->team->id}/schedules", [
                'schedulable_type' => 'lesson',
                'schedulable_id' => $data['lesson']->id,
                'available_at' => $when->toDateTimeString(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('schedules', [
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'schedulable_type' => 'lesson',
            'schedulable_id' => $data['lesson']->id,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/teams/{$this->team->id}/schedules")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'course')
            ->assertJsonPath('data.0.modules.0.lessons.0.id', $data['lesson']->id)
            ->assertJsonPath('data.0.modules.0.lessons.0.available_at', $when->toIso8601String());
    }

    public function test_admin_cannot_schedule_an_item_from_another_team(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->team = $this->enroll($this->student, $data['course'], $this->company->id);

        $other = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/teams/{$this->team->id}/schedules", [
                'schedulable_type' => 'lesson',
                'schedulable_id' => $other['lesson']->id,
                'available_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_clearing_a_schedule_removes_it(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->team = $this->enroll($this->student, $data['course'], $this->company->id);
        $this->schedule('lesson', $data['lesson']->id, now()->addDay());

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/teams/{$this->team->id}/schedules", [
                'schedulable_type' => 'lesson',
                'schedulable_id' => $data['lesson']->id,
                'available_at' => null,
            ])
            ->assertOk();

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_detaching_a_course_removes_its_schedule(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->team = $this->enroll($this->student, $data['course'], $this->company->id);
        $this->schedule('course', $data['course']->id, now()->addDay());

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/teams/{$this->team->id}/courses/{$data['course']->id}")
            ->assertOk();

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_student_cannot_start_a_lesson_released_in_the_future(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->team = $this->enroll($this->student, $data['course'], $this->company->id);
        $this->schedule('lesson', $data['lesson']->id, now()->addDay());

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/start', [
                'lesson_id' => $data['lesson']->id,
                'course_id' => $data['course']->id,
                'team_id' => $this->team->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule');

        $this->assertDatabaseCount('timelines', 0);
    }

    public function test_student_can_start_a_lesson_after_its_release_date(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->team = $this->enroll($this->student, $data['course'], $this->company->id);
        $this->schedule('lesson', $data['lesson']->id, now()->subDay());

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/start', [
                'lesson_id' => $data['lesson']->id,
                'course_id' => $data['course']->id,
                'team_id' => $this->team->id,
            ])
            ->assertOk();
    }

    public function test_the_most_restrictive_schedule_wins(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->team = $this->enroll($this->student, $data['course'], $this->company->id);

        $this->schedule('course', $data['course']->id, now()->addDays(5));
        $this->schedule('lesson', $data['lesson']->id, now()->subDay());

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $data['lesson']->id,
                'course_id' => $data['course']->id,
                'module_id' => $data['module']->id,
                'team_id' => $this->team->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule');
    }

    public function test_student_course_content_exposes_locked_and_available_at(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->team = $this->enroll($this->student, $data['course'], $this->company->id);
        $when = now()->addDays(2)->startOfMinute();
        $this->schedule('course', $data['course']->id, $when);

        $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/courses/{$data['course']->id}")
            ->assertOk()
            ->assertJsonPath('data.is_locked', true)
            ->assertJsonPath('data.available_at', $when->toIso8601String())
            ->assertJsonPath('data.modules.0.is_locked', true)
            ->assertJsonPath('data.modules.0.topics.0.lessons.0.is_locked', true)
            ->assertJsonPath('data.modules.0.topics.0.lessons.0.available_at', $when->toIso8601String());
    }
}
