<?php

namespace Tests\Feature;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminTeamTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_admin_can_list_teams_with_counts(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);

        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $team->courses()->attach($course->id, ['company_id' => $this->company->id, 'status' => 'active']);
        $team->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => null]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/teams')
            ->assertOk()
            ->assertJsonPath('data.0.users_count', 1)
            ->assertJsonPath('data.0.courses_count', 1);
    }

    public function test_admin_can_create_team_with_courses(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);

        $response = $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/teams', [
                'name' => 'Turma Alpha',
                'course_ids' => [$course->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Turma Alpha');

        $teamId = $response->json('data.id');

        $this->assertDatabaseHas('team_courses', [
            'team_id' => $teamId,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_update_team(): void
    {
        $team = Team::factory()->create(['company_id' => $this->company->id, 'name' => 'Antiga']);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/teams/{$team->id}", ['name' => 'Nova', 'status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nova')
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_admin_can_delete_team_and_detach_relations(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);

        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $team->courses()->attach($course->id, ['company_id' => $this->company->id, 'status' => 'active']);
        $team->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active', 'expired_at' => null]);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/teams/{$team->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertDatabaseMissing('team_courses', ['team_id' => $team->id]);
        $this->assertDatabaseMissing('team_users', ['team_id' => $team->id]);
    }

    public function test_admin_can_add_and_remove_student(): void
    {
        $team = Team::factory()->create(['company_id' => $this->company->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson("/api/v1/admin/teams/{$team->id}/students", [
                'user_id' => $this->student->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('team_users', [
            'team_id' => $team->id,
            'user_id' => $this->student->id,
            'status' => 'active',
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/teams/{$team->id}/students")
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->student->id);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/teams/{$team->id}/students/{$this->student->id}")
            ->assertOk();

        $this->assertDatabaseMissing('team_users', [
            'team_id' => $team->id,
            'user_id' => $this->student->id,
        ]);
    }

    public function test_admin_can_attach_and_detach_course(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);

        $team = Team::factory()->create(['company_id' => $this->company->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson("/api/v1/admin/teams/{$team->id}/courses", ['course_id' => $course->id])
            ->assertOk();

        $this->assertDatabaseHas('team_courses', ['team_id' => $team->id, 'course_id' => $course->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/teams/{$team->id}/courses")
            ->assertOk()
            ->assertJsonPath('data.0.id', $course->id);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/teams/{$team->id}/courses/{$course->id}")
            ->assertOk();

        $this->assertDatabaseMissing('team_courses', ['team_id' => $team->id, 'course_id' => $course->id]);
    }

    public function test_student_cannot_manage_teams(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/teams')
            ->assertStatus(403);
    }
}
