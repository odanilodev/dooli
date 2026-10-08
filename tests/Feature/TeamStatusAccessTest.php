<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Models\StoreProduct;
use App\Models\Team;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class TeamStatusAccessTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_student_loses_access_when_their_team_is_deactivated(): void
    {
        $data = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $data['course'], $this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $team->update(['status' => GeneralStatus::Inactive->value]);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/courses/{$data['course']->id}")
            ->assertForbidden();
    }

    public function test_reactivating_the_team_restores_access(): void
    {
        $data = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $data['course'], $this->company->id);
        $team->update(['status' => GeneralStatus::Inactive->value]);

        $team->update(['status' => GeneralStatus::Active->value]);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_enrollment_does_not_grant_access_through_an_inactive_team(): void
    {
        $data = $this->buildCourse($this->company->id);
        $team = Team::factory()->create([
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Inactive->value,
        ]);

        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $data['course']->id,
        ]);

        $product->teams()->attach($team->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);

        app(EnrollmentService::class)->grantAccess($this->student->id, $product);

        $this->assertDatabaseMissing('team_courses', [
            'team_id' => $team->id,
            'course_id' => $data['course']->id,
        ]);
        $this->assertDatabaseMissing('team_users', [
            'team_id' => $team->id,
            'user_id' => $this->student->id,
        ]);
    }

    public function test_enrollment_grants_access_through_an_active_team(): void
    {
        $data = $this->buildCourse($this->company->id);
        $team = Team::factory()->create([
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);

        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $data['course']->id,
        ]);

        $product->teams()->attach($team->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);

        app(EnrollmentService::class)->grantAccess($this->student->id, $product);

        $this->assertDatabaseHas('team_courses', [
            'team_id' => $team->id,
            'course_id' => $data['course']->id,
        ]);
        $this->assertDatabaseHas('team_users', [
            'team_id' => $team->id,
            'user_id' => $this->student->id,
        ]);
    }
}
