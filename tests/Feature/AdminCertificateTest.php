<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminCertificateTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    /**
     * @return array{0: int, 1: string} [certificateUser id, code]
     */
    private function issueCertificate(): array
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $course['lesson']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
                'module_id' => $course['module']->id,
            ])
            ->assertOk();

        $certificate = $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/certificates')
            ->assertOk()
            ->json('data.0');

        return [(int) $certificate['id'], (string) $certificate['code']];
    }

    public function test_admin_can_list_issued_certificates(): void
    {
        $this->issueCertificate();

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/certificate-users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_admin_can_revoke_an_issued_certificate(): void
    {
        [$id, $code] = $this->issueCertificate();

        $this->withToken($this->apiLogin($this->admin))
            ->postJson("/api/v1/admin/certificate-users/{$id}/revoke", ['reason' => 'Dados incorretos'])
            ->assertOk()
            ->assertJsonPath('data.invalidation_reason', 'Dados incorretos');

        $this->assertDatabaseHas('certificate_users', [
            'id' => $id,
            'invalidation_reason' => 'Dados incorretos',
        ]);

        // The public verification endpoint no longer finds the certificate.
        $this->withHeaders(['X-Company' => (string) $this->company->id])
            ->getJson("/api/v1/certificates/{$code}/verify")
            ->assertStatus(404);
    }

    public function test_student_cannot_list_issued_certificates(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/certificate-users')
            ->assertStatus(403);
    }
}
