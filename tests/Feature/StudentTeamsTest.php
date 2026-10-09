<?php

namespace Tests\Feature;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StudentTeamsTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_student_sees_only_active_enrollments_ordered_by_sort_order(): void
    {
        $second = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'active', 'sort_order' => 1, 'name' => 'Turma B']);
        $first = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'active', 'sort_order' => 0, 'name' => 'Turma A']);

        $second->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => null]);
        $first->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => null]);

        $inactiveEnrollment = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'active', 'sort_order' => 2, 'name' => 'Turma C']);
        $inactiveEnrollment->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'inactive', 'expired_at' => null]);

        $expired = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'active', 'sort_order' => 3, 'name' => 'Turma D']);
        $expired->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => now()->subDay()]);

        $inactiveTeam = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'inactive', 'sort_order' => 4, 'name' => 'Turma E']);
        $inactiveTeam->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => null]);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/teams')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.name', 'Turma A')
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.1.name', 'Turma B');
    }

    public function test_student_teams_expose_course_counts(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/teams')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $team->id)
            ->assertJsonPath('data.0.courses_count', 1);
    }

    public function test_guest_cannot_list_teams(): void
    {
        $this->getJson('/api/v1/me/teams')->assertStatus(401);
    }
}
