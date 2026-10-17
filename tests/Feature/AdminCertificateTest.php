<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Models\Certificate;
use App\Models\CertificateUser;
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

    private function completeLessonInCourse(Course $course, Team $team, Lesson $lesson): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/complete', [
                'lesson_id' => $lesson->id,
                'course_id' => $course->id,
                'team_id' => $team->id,
                'module_id' => $lesson->module_id,
            ])
            ->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function studentCertificates(): array
    {
        return $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/certificates')
            ->assertOk()
            ->json('data');
    }

    public function test_issued_certificate_snapshots_course_modules(): void
    {
        $course = $this->buildCourse($this->company->id);
        $secondModule = Module::factory()->create([
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);
        $secondLesson = $this->buildModuleLesson($this->company->id, $secondModule);
        $course['course']->modules()->attach($secondModule->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
            'sort_order' => 1,
        ]);

        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $this->completeLessonInCourse($course['course'], $team, $course['lesson']);
        $this->completeLessonInCourse($course['course'], $team, $secondLesson);

        $modules = $this->studentCertificates()[0]['modules'];

        $this->assertSame([$course['module']->name, $secondModule->name], array_column($modules, 'name'));

        $newModule = Module::factory()->create([
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);
        $this->buildModuleLesson($this->company->id, $newModule);
        $course['course']->modules()->attach($newModule->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
            'sort_order' => 2,
        ]);

        $this->assertSame($modules, $this->studentCertificates()[0]['modules']);
    }

    public function test_retroactive_certificate_uses_modules_completed_before_the_new_module(): void
    {
        $course = $this->buildCourse($this->company->id);
        Certificate::query()->where('course_id', $course['course']->id)->delete();

        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $this->completeLessonInCourse($course['course'], $team, $course['lesson']);

        $newModule = Module::factory()->create([
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);
        $this->buildModuleLesson($this->company->id, $newModule);
        $course['course']->modules()->attach($newModule->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
            'sort_order' => 1,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/certificates', [
                'name' => 'Modelo',
                'title' => 'Certificado',
                'course_id' => $course['course']->id,
            ])
            ->assertCreated();

        $issued = $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/certificate-users')
            ->assertOk()
            ->json('data.0');

        $this->assertSame([$course['module']->name], array_column($issued['modules'], 'name'));
    }

    public function test_backfill_fills_module_snapshot_without_new_modules(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->completeLessonInCourse($course['course'], $team, $course['lesson']);

        $issued = CertificateUser::query()->firstOrFail();
        $issued->forceFill(['modules' => null])->save();

        $newModule = Module::factory()->create([
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);
        $this->buildModuleLesson($this->company->id, $newModule);
        $course['course']->modules()->attach($newModule->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
            'sort_order' => 1,
        ]);

        $this->artisan('certificates:backfill-modules', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($issued->fresh()->modules);

        $this->artisan('certificates:backfill-modules')->assertSuccessful();

        $this->assertSame(
            [$course['module']->name],
            array_column($issued->fresh()->modules, 'name'),
        );
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

    public function test_admin_can_create_a_certificate_with_layout(): void
    {
        $course = $this->buildCourse($this->company->id)['course'];

        $response = $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/certificates', [
                'name' => 'Modelo',
                'title' => 'Certificado',
                'course_id' => $course->id,
                'layout' => [
                    'title' => [
                        'x' => 42.5,
                        'y' => 36,
                        'width' => 65,
                        'color' => '#112233',
                        'font' => 'Lora',
                        'size' => 30,
                        'align' => 'left',
                        'bold' => true,
                        'italic' => false,
                    ],
                    'modules' => [
                        'x' => 50,
                        'y' => 60,
                        'width' => 70,
                    ],
                    'signature_line' => [
                        'x' => 50,
                        'y' => 74,
                        'width' => 40,
                    ],
                    'code' => [
                        'x' => 96,
                        'y' => 94,
                        'align' => 'right',
                    ],
                ],
            ])
            ->assertCreated();

        $certificate = Certificate::findOrFail((int) $response->json('data.id'));

        $this->assertSame(42.5, $certificate->layout['title']['x']);
        $this->assertEquals(65.0, $certificate->layout['title']['width']);
        $this->assertSame('#112233', $certificate->layout['title']['color']);
        $this->assertSame('Lora', $certificate->layout['title']['font']);
        $this->assertEquals(70.0, $certificate->layout['modules']['width']);
        $this->assertEquals(40.0, $certificate->layout['signature_line']['width']);
        $this->assertSame('right', $certificate->layout['code']['align']);
        $this->assertEquals(96.0, $certificate->layout['code']['x']);
        $this->assertNull($certificate->layout['code']['width']);
    }

    public function test_layout_position_is_validated(): void
    {
        $course = $this->buildCourse($this->company->id)['course'];

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/certificates', [
                'name' => 'Modelo',
                'title' => 'Certificado',
                'course_id' => $course->id,
                'layout' => [
                    'title' => ['x' => 150, 'y' => 50],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('layout.title.x');
    }

    public function test_layout_width_is_validated(): void
    {
        $course = $this->buildCourse($this->company->id)['course'];

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/certificates', [
                'name' => 'Modelo',
                'title' => 'Certificado',
                'course_id' => $course->id,
                'layout' => [
                    'title' => ['width' => 120],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('layout.title.width');
    }

    public function test_layout_can_be_sent_as_json_on_multipart(): void
    {
        Storage::fake('public');
        $course = $this->buildCourse($this->company->id)['course'];

        $response = $this->withToken($this->apiLogin($this->admin))
            ->post('/api/v1/admin/certificates', [
                'name' => 'Modelo',
                'title' => 'Certificado',
                'course_id' => $course->id,
                'background' => UploadedFile::fake()->image('bg.png', 1600, 900),
                'layout' => json_encode([
                    'title' => ['x' => 40, 'y' => 35, 'font' => 'Georgia', 'size' => 28],
                ]),
            ])
            ->assertCreated();

        $certificate = Certificate::findOrFail((int) $response->json('data.id'));

        $this->assertEquals(40.0, $certificate->layout['title']['x']);
        $this->assertEquals(80.0, $certificate->layout['title']['width']);
        $this->assertSame('Georgia', $certificate->layout['title']['font']);
    }

    public function test_clone_copies_layout(): void
    {
        $course = $this->buildCourse($this->company->id)['course'];

        $source = $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/certificates', [
                'name' => 'Modelo',
                'title' => 'Certificado',
                'course_id' => $course->id,
                'layout' => [
                    'title' => ['x' => 44, 'y' => 41, 'color' => '#abcdef'],
                ],
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
            ->json('data');

        $this->assertSame($source['layout'], $clone['layout']);
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
