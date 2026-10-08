<?php

namespace Tests\Feature;

use App\Models\Schedule;
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

        Schedule::query()->create([
            'company_id' => $this->company->id,
            'team_id' => $team->id,
            'schedulable_type' => 'course',
            'schedulable_id' => $course->id,
            'available_at' => now()->addDay(),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/teams')
            ->assertOk()
            ->assertJsonPath('data.0.users_count', 1)
            ->assertJsonPath('data.0.courses_count', 1)
            ->assertJsonPath('data.0.schedules_count', 1);
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

    public function test_team_students_list_exposes_enrollment_status_and_expiry(): void
    {
        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $expires = now()->addMonth();

        $team->users()->attach($this->student->id, [
            'company_id' => $this->company->id,
            'status' => 'active',
            'expired_at' => $expires,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/teams/{$team->id}/students")
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->student->id)
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.expired_at', $expires->toIso8601String());
    }

    public function test_admin_can_update_student_expiry_and_status_without_touching_account(): void
    {
        $token = $this->apiLogin($this->admin);
        $team = Team::factory()->create(['company_id' => $this->company->id]);

        $this->withToken($token)
            ->postJson("/api/v1/admin/teams/{$team->id}/students", ['user_id' => $this->student->id])
            ->assertOk();

        $accountStatus = $this->student->fresh()->status;

        $this->withToken($token)
            ->putJson("/api/v1/admin/teams/{$team->id}/students/{$this->student->id}", [
                'status' => 'inactive',
                'expired_at' => now()->addYear()->toDateTimeString(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('team_users', [
            'team_id' => $team->id,
            'user_id' => $this->student->id,
            'status' => 'inactive',
        ]);
        $this->assertNotNull($team->users()->whereKey($this->student->id)->first()->pivot->expired_at);
        $this->assertSame($accountStatus, $this->student->fresh()->status);

        $this->withToken($token)
            ->putJson("/api/v1/admin/teams/{$team->id}/students/{$this->student->id}", [
                'status' => 'active',
                'expired_at' => null,
            ])
            ->assertOk();

        $pivot = $team->users()->whereKey($this->student->id)->first()->pivot;
        $this->assertNull($pivot->expired_at);
        $this->assertSame('active', $pivot->status);
    }

    public function test_update_student_requires_membership(): void
    {
        $team = Team::factory()->create(['company_id' => $this->company->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/teams/{$team->id}/students/{$this->student->id}", [
                'expired_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertStatus(422);
    }

    public function test_admin_can_bulk_attach_courses_to_teams(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);
        $a = Team::factory()->create(['company_id' => $this->company->id]);
        $b = Team::factory()->create(['company_id' => $this->company->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/teams/bulk', [
                'action' => 'attach-courses',
                'team_ids' => [$a->id, $b->id],
                'course_ids' => [$course->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 2)
            ->assertJsonCount(0, 'data.failed');

        $this->assertDatabaseHas('team_courses', ['team_id' => $a->id, 'course_id' => $course->id]);
        $this->assertDatabaseHas('team_courses', ['team_id' => $b->id, 'course_id' => $course->id]);
    }

    public function test_admin_can_bulk_add_students_to_teams(): void
    {
        $a = Team::factory()->create(['company_id' => $this->company->id]);
        $b = Team::factory()->create(['company_id' => $this->company->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/teams/bulk', [
                'action' => 'add-students',
                'team_ids' => [$a->id, $b->id],
                'user_ids' => [$this->student->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 2)
            ->assertJsonCount(0, 'data.failed');

        $this->assertDatabaseHas('team_users', ['team_id' => $a->id, 'user_id' => $this->student->id]);
        $this->assertDatabaseHas('team_users', ['team_id' => $b->id, 'user_id' => $this->student->id]);
    }

    public function test_admin_can_bulk_update_team_status(): void
    {
        $a = Team::factory()->create(['company_id' => $this->company->id]);
        $b = Team::factory()->create(['company_id' => $this->company->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/teams/bulk', [
                'action' => 'deactivate',
                'team_ids' => [$a->id, $b->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 2);

        $this->assertSame('inactive', $a->fresh()->status);
        $this->assertSame('inactive', $b->fresh()->status);
    }

    public function test_admin_can_bulk_delete_teams(): void
    {
        $a = Team::factory()->create(['company_id' => $this->company->id]);
        $b = Team::factory()->create(['company_id' => $this->company->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/teams/bulk', [
                'action' => 'delete',
                'team_ids' => [$a->id, $b->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 2);

        $this->assertDatabaseMissing('teams', ['id' => $a->id]);
        $this->assertDatabaseMissing('teams', ['id' => $b->id]);
    }

    public function test_bulk_reports_foreign_teams_as_failures(): void
    {
        $other = Team::factory()->create();

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/teams/bulk', [
                'action' => 'delete',
                'team_ids' => [$other->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 0)
            ->assertJsonCount(1, 'data.failed');

        $this->assertDatabaseHas('teams', ['id' => $other->id]);
    }
}
