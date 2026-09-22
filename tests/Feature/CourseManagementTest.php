<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_admin_can_create_a_course(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/courses', ['name' => 'Curso de Laravel'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Curso de Laravel');
    }

    public function test_admin_can_publish_and_unpublish(): void
    {
        $token = $this->apiLogin($this->admin);

        $id = $this->withToken($token)
            ->postJson('/api/v1/admin/courses', ['name' => 'Curso'])
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/v1/admin/courses/{$id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->withToken($token)
            ->postJson("/api/v1/admin/courses/{$id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_admin_can_update_and_delete_a_course(): void
    {
        $token = $this->apiLogin($this->admin);

        $id = $this->withToken($token)
            ->postJson('/api/v1/admin/courses', ['name' => 'Antigo'])
            ->json('data.id');

        $this->withToken($token)
            ->putJson("/api/v1/admin/courses/{$id}", ['name' => 'Novo'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo');

        $this->withToken($token)
            ->deleteJson("/api/v1/admin/courses/{$id}")
            ->assertNoContent();
    }

    public function test_store_requires_valid_payload(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/courses', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name']]);
    }
}
