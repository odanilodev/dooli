<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StudentHomeTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_home_groups_courses_by_team_ordered_by_sort_order(): void
    {
        $a = $this->buildCourse($this->company->id);
        $teamA = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'active', 'sort_order' => 1, 'name' => 'Turma A']);
        $teamA->courses()->attach($a['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);
        $teamA->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => null]);

        $b = $this->buildCourse($this->company->id);
        $teamB = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'active', 'sort_order' => 0, 'name' => 'Turma B']);
        $teamB->courses()->attach($b['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);
        $teamB->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => null]);

        $token = $this->apiLogin($this->student);

        $this->withToken($token)
            ->getJson('/api/v1/me/home')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $teamB->id)
            ->assertJsonPath('data.0.name', 'Turma B')
            ->assertJsonPath('data.0.courses.0.id', $b['course']->id)
            ->assertJsonPath('data.0.courses.0.total_lessons', 1)
            ->assertJsonPath('data.0.courses.0.completed_lessons', 0)
            ->assertJsonPath('data.1.id', $teamA->id)
            ->assertJsonPath('data.1.courses.0.id', $a['course']->id);

        $this->withToken($token)
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $a['lesson']->id,
                'course_id' => $a['course']->id,
                'team_id' => $teamA->id,
                'module_id' => $a['module']->id,
            ])
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/me/home')
            ->assertOk()
            ->assertJsonPath('data.1.courses.0.completed_lessons', 1)
            ->assertJsonPath('data.1.courses.0.percentage', 100);
    }

    public function test_home_exposes_continue_watching_per_team(): void
    {
        $a = $this->buildCourse($this->company->id);
        $teamA = $this->enroll($this->student, $a['course'], $this->company->id);
        Lesson::factory()->create(['company_id' => $this->company->id, 'topic_id' => $a['topic']->id, 'sort_order' => 1]);

        $b = $this->buildCourse($this->company->id);
        $teamB = $this->enroll($this->student, $b['course'], $this->company->id);
        Lesson::factory()->create(['company_id' => $this->company->id, 'topic_id' => $b['topic']->id, 'sort_order' => 1]);

        $token = $this->apiLogin($this->student);

        $this->withToken($token)
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $a['lesson']->id,
                'course_id' => $a['course']->id,
                'team_id' => $teamA->id,
                'module_id' => $a['module']->id,
            ])
            ->assertOk();

        $sections = collect($this->withToken($token)->getJson('/api/v1/me/home')->json('data'))->keyBy('id');

        $this->assertCount(1, $sections[$teamA->id]['continue_watching']);
        $this->assertSame($a['course']->id, $sections[$teamA->id]['continue_watching'][0]['course_id']);
        $this->assertCount(0, $sections[$teamB->id]['continue_watching']);
    }

    public function test_home_continue_watching_falls_back_to_the_course_cover_image(): void
    {
        $a = $this->buildCourse($this->company->id);
        $a['course']->update(['image' => 'courses/'.$this->company->id.'/cover.jpg']);
        $team = $this->enroll($this->student, $a['course'], $this->company->id);
        Lesson::factory()->create(['company_id' => $this->company->id, 'topic_id' => $a['topic']->id, 'sort_order' => 1]);

        $token = $this->apiLogin($this->student);

        $this->withToken($token)
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $a['lesson']->id,
                'course_id' => $a['course']->id,
                'team_id' => $team->id,
                'module_id' => $a['module']->id,
            ])
            ->assertOk();

        $sections = collect($this->withToken($token)->getJson('/api/v1/me/home')->json('data'))->keyBy('id');

        $this->assertSame(
            Storage::disk('public')->url('courses/'.$this->company->id.'/cover.jpg'),
            $sections[$team->id]['continue_watching'][0]['image'],
        );
    }

    public function test_home_ignores_inactive_and_expired_enrollments(): void
    {
        $course = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $course['course'], $this->company->id);

        $inactiveEnrollment = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'active', 'sort_order' => 1]);
        $inactiveEnrollment->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'inactive', 'expired_at' => null]);

        $expired = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'active', 'sort_order' => 2]);
        $expired->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => now()->subDay()]);

        $inactiveTeam = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'inactive', 'sort_order' => 3]);
        $inactiveTeam->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => null]);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/home')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.courses.0.id', $course['course']->id);
    }

    public function test_same_course_in_two_teams_keeps_progress_per_team(): void
    {
        $data = $this->buildCourse($this->company->id);

        $teamA = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'active', 'sort_order' => 0, 'name' => 'Turma A']);
        $teamB = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'active', 'sort_order' => 1, 'name' => 'Turma B']);

        foreach ([$teamA, $teamB] as $team) {
            $team->courses()->attach($data['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);
            $team->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => null]);
        }

        $token = $this->apiLogin($this->student);

        $this->withToken($token)
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $data['lesson']->id,
                'course_id' => $data['course']->id,
                'team_id' => $teamA->id,
                'module_id' => $data['module']->id,
            ])
            ->assertOk();

        $sections = collect($this->withToken($token)->getJson('/api/v1/me/home')->json('data'))->keyBy('id');

        $this->assertSame($data['course']->id, $sections[$teamA->id]['courses'][0]['id']);
        $this->assertEquals(1, $sections[$teamA->id]['courses'][0]['completed_lessons']);
        $this->assertEquals(100, $sections[$teamA->id]['courses'][0]['percentage']);
        $this->assertEquals(0, $sections[$teamB->id]['courses'][0]['completed_lessons']);
        $this->assertEquals(0, $sections[$teamB->id]['courses'][0]['percentage']);
    }

    public function test_home_limits_courses_per_team_but_exposes_all_course_ids(): void
    {
        $team = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'active']);

        $courseIds = [];

        for ($i = 0; $i < 8; $i++) {
            $data = $this->buildCourse($this->company->id);
            $courseIds[] = $data['course']->id;
            $team->courses()->attach($data['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);
        }

        $team->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => null]);

        $response = $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/home')
            ->assertOk()
            ->assertJsonCount(6, 'data.0.courses')
            ->assertJsonCount(8, 'data.0.course_ids');

        $this->assertEqualsCanonicalizing($courseIds, $response->json('data.0.course_ids'));
    }

    public function test_home_limits_continue_watching_per_team(): void
    {
        $team = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'active']);
        $team->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => null]);

        $token = $this->apiLogin($this->student);

        for ($i = 0; $i < 6; $i++) {
            $data = $this->buildCourse($this->company->id);
            $team->courses()->attach($data['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);
            Lesson::factory()->create(['company_id' => $this->company->id, 'topic_id' => $data['topic']->id, 'sort_order' => 1]);

            $this->withToken($token)
                ->postJson('/api/v1/me/progress/complete', [
                    'lesson_id' => $data['lesson']->id,
                    'course_id' => $data['course']->id,
                    'team_id' => $team->id,
                    'module_id' => $data['module']->id,
                ])
                ->assertOk();
        }

        $sections = collect($this->withToken($token)->getJson('/api/v1/me/home')->json('data'))->keyBy('id');

        $this->assertCount(4, $sections[$team->id]['continue_watching']);
    }

    public function test_home_uses_a_bounded_number_of_queries(): void
    {
        for ($t = 0; $t < 5; $t++) {
            $team = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'active', 'sort_order' => $t, 'name' => "Turma {$t}"]);

            for ($c = 0; $c < 3; $c++) {
                $data = $this->buildCourse($this->company->id);
                $team->courses()->attach($data['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);
            }

            $team->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => null]);
        }

        $token = $this->apiLogin($this->student);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->withToken($token)
            ->getJson('/api/v1/me/home')
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(20, $queries, "A home executou {$queries} queries.");
    }

    public function test_home_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/home')->assertStatus(401);
    }
}
