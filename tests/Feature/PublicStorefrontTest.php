<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Enums\UserTypeCode;
use App\Mail\TransactionalMail;
use App\Models\Company;
use App\Models\Course;
use App\Models\StoreProduct;
use App\Models\Team;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class PublicStorefrontTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_public_company_resolves_by_slug_and_isolates_products(): void
    {
        $course = Course::factory()->create(['company_id' => $this->company->id]);
        StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course->id,
            'slug' => 'produto-da-empresa-a',
            'status' => GeneralStatus::Active->value,
        ]);

        $other = Company::factory()->create();
        $otherCourse = Course::factory()->create(['company_id' => $other->id]);
        StoreProduct::factory()->create([
            'company_id' => $other->id,
            'course_id' => $otherCourse->id,
            'slug' => 'produto-da-empresa-b',
            'status' => GeneralStatus::Active->value,
        ]);

        $this->getJson('/api/v1/public/companies/'.$this->company->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $this->company->slug);

        $this->getJson('/api/v1/public/companies/'.$this->company->slug.'/store/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'produto-da-empresa-a');

        $this->getJson('/api/v1/public/companies/'.$this->company->slug.'/store/products/produto-da-empresa-b')
            ->assertNotFound();
    }

    public function test_same_email_can_register_in_two_companies(): void
    {
        $other = Company::factory()->create();

        $studentType = UserType::factory()->create([
            'company_id' => $other->id,
            'code' => UserTypeCode::Student->value,
        ]);

        $payload = [
            'name' => 'Aluno Duplicado',
            'email' => 'mesmo@email.test',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'terms' => true,
        ];

        $this->postJson('/api/v1/public/companies/'.$this->company->slug.'/auth/register', $payload)
            ->assertCreated()
            ->assertJsonPath('data.user.company_id', $this->company->id);

        $this->postJson('/api/v1/public/companies/'.$other->slug.'/auth/register', $payload)
            ->assertCreated()
            ->assertJsonPath('data.user.company_id', $other->id);

        $this->assertSame(2, User::query()->withoutGlobalScope('company')->where('email', 'mesmo@email.test')->count());
        $this->assertNotNull($studentType);
    }

    public function test_company_login_rejects_user_from_another_company(): void
    {
        $other = Company::factory()->create();

        $this->postJson('/api/v1/public/companies/'.$other->slug.'/auth/login', [
            'email' => $this->student->email,
            'password' => 'password',
        ])->assertStatus(401);
    }

    public function test_password_reset_is_scoped_to_the_company(): void
    {
        Mail::fake();

        $other = Company::factory()->create();
        UserType::factory()->create(['company_id' => $other->id, 'code' => UserTypeCode::Student->value]);
        $otherStudent = User::factory()->create([
            'company_id' => $other->id,
            'email' => $this->student->email,
            'password' => 'original-password',
        ]);

        $this->postJson('/api/v1/public/companies/'.$this->company->slug.'/auth/forgot-password', [
            'email' => $this->student->email,
        ])->assertOk();

        $mail = Mail::sent(TransactionalMail::class)
            ->last(fn (TransactionalMail $mailable) => $mailable->hasTo($this->student->email));

        $this->assertNotNull($mail?->actionUrl);

        $url = (string) $mail->actionUrl;
        $query = (string) parse_url($url, PHP_URL_QUERY);

        if ($query === '' && str_contains($url, '?')) {
            $query = substr($url, strpos($url, '?') + 1);
        }

        parse_str($query, $params);
        $token = (string) ($params['token'] ?? '');

        $this->postJson('/api/v1/public/companies/'.$this->company->slug.'/auth/reset-password', [
            'token' => $token,
            'email' => $this->student->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $this->student->fresh()->password));
        $this->assertTrue(Hash::check('original-password', $otherStudent->fresh()->password));
    }

    public function test_public_product_exposes_package_courses_and_access_label(): void
    {
        $dataA = $this->buildCourse($this->company->id);
        $dataB = $this->buildCourse($this->company->id);
        $team = Team::factory()->create(['company_id' => $this->company->id]);

        $team->courses()->attach($dataA['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);
        $team->courses()->attach($dataB['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $dataA['course']->id,
            'slug' => 'pacote-completo',
            'access_duration_days' => 7,
        ]);

        $product->teams()->attach($team->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $this->getJson('/api/v1/public/companies/'.$this->company->slug.'/store/products/pacote-completo')
            ->assertOk()
            ->assertJsonPath('data.access_label', 'Acesso por 7 dias')
            ->assertJsonCount(2, 'data.included_courses');
    }

    public function test_public_product_hides_internal_teams_and_inactive_courses(): void
    {
        $active = $this->buildCourse($this->company->id);
        $draft = $this->buildCourse($this->company->id);
        $draft['course']->update(['status' => 'inactive']);

        $team = Team::factory()->create(['company_id' => $this->company->id, 'name' => 'Turma Interna']);
        $inactiveTeam = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'inactive']);

        $team->courses()->attach($active['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);
        $team->courses()->attach($draft['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);
        $inactiveTeam->courses()->attach($draft['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $active['course']->id,
            'slug' => 'sem-vazamento',
        ]);

        $product->teams()->attach($team->id, ['company_id' => $this->company->id, 'status' => 'active']);
        $product->teams()->attach($inactiveTeam->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $response = $this->getJson('/api/v1/public/companies/'.$this->company->slug.'/store/products/sem-vazamento')
            ->assertOk()
            ->assertJsonMissingPath('data.teams')
            ->assertJsonCount(1, 'data.included_courses')
            ->assertJsonPath('data.included_courses.0.id', $active['course']->id);

        $this->assertStringNotContainsString('Turma Interna', $response->getContent());

        $this->getJson('/api/v1/public/companies/'.$this->company->slug.'/store/products')
            ->assertOk()
            ->assertJsonMissingPath('data.0.teams');
    }
}
