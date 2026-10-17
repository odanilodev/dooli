<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Company;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Team;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    /**
     * @param  array{course: Course, module: Module, topic: Topic, lesson: Lesson}  $course
     */
    private function completeCourse(array $course): Team
    {
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $course['lesson']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
                'module_id' => $course['module']->id,
            ])
            ->assertOk();

        return $team;
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

    public function test_admin_can_create_a_certificate_template(): void
    {
        $course = $this->buildCourse($this->company->id)['course'];

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/certificates', [
                'name' => 'Modelo Padrão',
                'title' => 'Certificado de Conclusão',
                'course_id' => $course->id,
                'time' => 8,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Modelo Padrão')
            ->assertJsonPath('data.course_id', $course->id);

        $this->assertDatabaseHas('certificates', [
            'name' => 'Modelo Padrão',
            'course_id' => $course->id,
        ]);
    }

    public function test_admin_can_create_a_certificate_with_visual_fields(): void
    {
        Storage::fake('public');
        $course = $this->buildCourse($this->company->id)['course'];

        $response = $this->withToken($this->apiLogin($this->admin))
            ->post('/api/v1/admin/certificates', [
                'name' => 'Modelo Visual',
                'title' => 'Certificado',
                'course_id' => $course->id,
                'body' => 'Certificamos que {aluno} concluiu {curso}.',
                'signatory_name' => 'Maria Silva',
                'signatory_title' => 'Diretora',
                'background' => UploadedFile::fake()->image('bg.png', 1600, 900),
                'signature' => UploadedFile::fake()->image('sign.png', 400, 150),
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Certificamos que {aluno} concluiu {curso}.')
            ->assertJsonPath('data.signatory_name', 'Maria Silva');

        $this->assertNotNull($response->json('data.background_url'));
        $this->assertNotNull($response->json('data.signature_url'));

        Storage::disk('public')->assertExists($response->json('data.background'));
        Storage::disk('public')->assertExists($response->json('data.signature'));
    }

    public function test_admin_can_update_a_certificate_template(): void
    {
        $course = $this->buildCourse($this->company->id)['course'];
        $certificate = Certificate::where('course_id', $course->id)->firstOrFail();

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/certificates/{$certificate->id}", [
                'title' => 'Certificado Atualizado',
                'body' => 'Novo corpo do certificado.',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Certificado Atualizado')
            ->assertJsonPath('data.body', 'Novo corpo do certificado.');

        $this->assertDatabaseHas('certificates', [
            'id' => $certificate->id,
            'title' => 'Certificado Atualizado',
        ]);
    }

    public function test_admin_can_delete_a_certificate_template(): void
    {
        $course = $this->buildCourse($this->company->id)['course'];
        $certificate = Certificate::where('course_id', $course->id)->firstOrFail();

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/certificates/{$certificate->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('certificates', ['id' => $certificate->id]);
    }

    public function test_certificate_course_must_belong_to_company(): void
    {
        $otherCompany = Company::factory()->create();
        $otherCourse = Course::factory()->create(['company_id' => $otherCompany->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/certificates', [
                'name' => 'Modelo',
                'title' => 'Certificado',
                'course_id' => $otherCourse->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('course_id');
    }

    public function test_admin_can_filter_issued_certificates_by_course(): void
    {
        $first = $this->buildCourse($this->company->id);
        $this->completeCourse($first);
        $second = $this->buildCourse($this->company->id);
        $this->completeCourse($second);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/certificate-users?course_id='.$first['course']->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.certificate.course_id', $first['course']->id);
    }

    public function test_admin_can_create_a_new_version_cloning_the_current_template(): void
    {
        Storage::fake('public');
        $course = $this->buildCourse($this->company->id)['course'];

        $source = $this->withToken($this->apiLogin($this->admin))
            ->post('/api/v1/admin/certificates', [
                'name' => 'Modelo',
                'title' => 'Certificado',
                'course_id' => $course->id,
                'version' => '1.0',
                'background' => UploadedFile::fake()->image('bg.png', 1600, 900),
                'signature' => UploadedFile::fake()->image('sign.png', 400, 150),
            ])
            ->assertCreated()
            ->json('data');

        $clone = $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/certificates', [
                'name' => 'Modelo',
                'title' => 'Certificado',
                'course_id' => $course->id,
                'version' => '2.0',
                'copy_from' => $source['id'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', '2.0')
            ->json('data');

        $this->assertNotSame($source['background'], $clone['background']);
        $this->assertNotSame($source['signature'], $clone['signature']);

        Storage::disk('public')->assertExists($source['background']);
        Storage::disk('public')->assertExists($source['signature']);
        Storage::disk('public')->assertExists($clone['background']);
        Storage::disk('public')->assertExists($clone['signature']);
    }

    public function test_copy_from_must_belong_to_company(): void
    {
        $course = $this->buildCourse($this->company->id)['course'];

        $otherCompany = Company::factory()->create();
        $otherCourse = Course::factory()->create(['company_id' => $otherCompany->id]);
        $foreign = Certificate::factory()->create([
            'company_id' => $otherCompany->id,
            'course_id' => $otherCourse->id,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/certificates', [
                'name' => 'Modelo',
                'title' => 'Certificado',
                'course_id' => $course->id,
                'copy_from' => $foreign->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('copy_from');
    }

    public function test_student_cannot_manage_certificate_templates(): void
    {
        $course = $this->buildCourse($this->company->id)['course'];

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/admin/certificates', [
                'name' => 'Modelo',
                'title' => 'Certificado',
                'course_id' => $course->id,
            ])
            ->assertStatus(403);
    }
}
