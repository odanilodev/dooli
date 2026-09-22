<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_student_cannot_access_admin_area(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/courses')
            ->assertStatus(403);
    }

    public function test_admin_can_access_admin_area(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/courses')
            ->assertOk();
    }

    public function test_admin_cannot_view_course_from_another_company(): void
    {
        $otherCompany = Company::factory()->create();
        $otherCourse = Course::factory()->create(['company_id' => $otherCompany->id]);

        // Cross-tenant access is hidden by the tenant scope (404, no data leak).
        $this->withToken($this->apiLogin($this->admin))
            ->getJson("/api/v1/admin/courses/{$otherCourse->id}")
            ->assertStatus(404);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/admin/courses')->assertStatus(401);
    }

    public function test_unauthenticated_request_without_json_accept_header_returns_json_401(): void
    {
        // Regression: without "Accept: application/json" the framework must not
        // try to redirect guests to a non-existent "login" route (500).
        $this->get('/api/v1/admin/courses')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'unauthenticated');
    }
}
