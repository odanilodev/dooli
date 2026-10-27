<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class VideoPlatformTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_admin_can_manage_video_platforms_without_exposing_credentials(): void
    {
        $token = $this->apiLogin($this->admin);

        $id = $this->withToken($token)
            ->postJson('/api/v1/admin/video-platforms', [
                'name' => 'VdoCipher',
                'code' => 'vdocipher',
                'credentials' => ['api_secret' => 'super-secret'],
                'settings' => ['ttl' => 3600],
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'vdocipher')
            ->assertJsonPath('data.has_credentials', true)
            ->assertJsonMissingPath('data.credentials')
            ->json('data.id');

        $this->withToken($token)
            ->getJson('/api/v1/admin/video-platforms')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // Empty credentials must not overwrite the stored secret.
        $this->withToken($token)
            ->putJson("/api/v1/admin/video-platforms/{$id}", [
                'name' => 'VdoCipher DRM',
                'credentials' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'VdoCipher DRM')
            ->assertJsonPath('data.has_credentials', true);

        $this->withToken($token)
            ->deleteJson("/api/v1/admin/video-platforms/{$id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('video_platforms', ['id' => $id]);
    }

    public function test_panda_platform_is_not_supported(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/video-platforms', [
                'name' => 'Panda Video',
                'code' => 'panda',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_student_cannot_manage_video_platforms(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/video-platforms')
            ->assertStatus(403);
    }
}
