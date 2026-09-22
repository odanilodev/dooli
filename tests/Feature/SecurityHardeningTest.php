<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Models\CertificateUser;
use App\Models\Company;
use App\Models\Notification;
use App\Models\StoreProduct;
use App\Models\Team;
use App\Models\TypeNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_student_cannot_comment_without_enrollment(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = Team::factory()->create(['company_id' => $this->company->id]);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/comments', [
                'content' => 'Sem matrícula',
                'lesson_id' => $course['lesson']->id,
                'module_id' => $course['module']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
            ])
            ->assertStatus(403);
    }

    public function test_student_cannot_rate_without_enrollment(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = Team::factory()->create(['company_id' => $this->company->id]);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/ratings', [
                'rating' => 9,
                'lesson_id' => $course['lesson']->id,
                'module_id' => $course['module']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
            ])
            ->assertStatus(403);
    }

    public function test_comment_listing_only_shows_enrolled_products(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/comments', [
                'content' => 'Comentário válido',
                'lesson_id' => $course['lesson']->id,
                'module_id' => $course['module']->id,
                'course_id' => $course['course']->id,
                'team_id' => $team->id,
            ])
            ->assertCreated();

        // A different student (not enrolled) sees nothing.
        $otherStudent = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $this->withToken($this->apiLogin($otherStudent))
            ->getJson('/api/v1/comments')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_student_cannot_mark_another_users_notification_as_read(): void
    {
        $notification = $this->createNotificationFor($this->admin);

        $this->withToken($this->apiLogin($this->student))
            ->postJson("/api/v1/me/notifications/{$notification->id}/read")
            ->assertStatus(404);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson("/api/v1/me/notifications/{$notification->id}/read")
            ->assertOk();
    }

    public function test_certificate_verification_does_not_leak_personal_data(): void
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

        $code = CertificateUser::query()->firstOrFail()->code;

        $response = $this->withHeaders(['X-Company' => (string) $this->company->id])
            ->getJson("/api/v1/certificates/{$code}/verify")
            ->assertOk()
            ->assertJsonPath('data.valid', true);

        $this->assertArrayNotHasKey('email', $response->json('data'));
        $this->assertArrayNotHasKey('phone', $response->json('data'));
        $this->assertArrayNotHasKey('user', $response->json('data'));
    }

    public function test_duplicate_rating_returns_conflict(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = $this->enroll($this->student, $course['course'], $this->company->id);

        $payload = [
            'rating' => 8,
            'lesson_id' => $course['lesson']->id,
            'module_id' => $course['module']->id,
            'course_id' => $course['course']->id,
            'team_id' => $team->id,
        ];

        $token = $this->apiLogin($this->student);

        $this->withToken($token)->postJson('/api/v1/ratings', $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/v1/ratings', $payload)->assertStatus(409);
    }

    public function test_store_catalog_hides_unreleased_products(): void
    {
        $course = $this->buildCourse($this->company->id);

        $storeProduct = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'status' => GeneralStatus::Active->value,
            'release_at' => now()->addDay(),
        ]);

        $this->withHeaders(['X-Company' => (string) $this->company->id])
            ->getJson('/api/v1/store/products')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withHeaders(['X-Company' => (string) $this->company->id])
            ->getJson("/api/v1/store/products/{$storeProduct->slug}")
            ->assertStatus(404);
    }

    public function test_inactive_company_is_rejected_on_public_routes(): void
    {
        $company = Company::factory()->create(['status' => GeneralStatus::Inactive->value]);

        $this->withHeaders(['X-Company' => (string) $company->id])
            ->getJson('/api/v1/store/products')
            ->assertStatus(404)
            ->assertJsonPath('code', 'company_not_found');
    }

    private function createNotificationFor(User $user): Notification
    {
        $team = Team::factory()->create(['company_id' => $this->company->id]);

        $type = TypeNotification::query()->create([
            'company_id' => $this->company->id,
            'slug' => 'system',
            'name' => 'Sistema',
        ]);

        $notification = Notification::query()->create([
            'company_id' => $this->company->id,
            'team_id' => $team->id,
            'type_notification_id' => $type->id,
            'status' => GeneralStatus::Active->value,
        ]);

        $notification->users()->attach($user->id, ['company_id' => $this->company->id]);

        return $notification;
    }
}
