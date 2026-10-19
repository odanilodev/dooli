<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Enums\UserTypeCode;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_student_can_register_and_is_signed_in(): void
    {
        $response = $this->register([
            'name' => 'Novo Aluno',
            'email' => 'novo.aluno@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'novo.aluno@example.com')
            ->assertJsonPath('data.user.company_id', $this->company->id)
            ->assertJsonPath('data.user.user_type.code', UserTypeCode::Student->value)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user' => ['id', 'name', 'email']]]);

        $user = User::query()->where('email', 'novo.aluno@example.com')->firstOrFail();

        $this->assertSame(GeneralStatus::Active->value, $user->status);
        $this->assertSame('novoaluno', $user->username);
        $this->assertTrue(Hash::check('password123', $user->password));

        $this->withToken($response->json('data.token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_registration_requires_a_unique_email(): void
    {
        $this->register([
            'email' => $this->student->email,
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->register([
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_registration_requires_accepted_terms(): void
    {
        $this->register([
            'terms' => false,
        ])->assertStatus(422)->assertJsonValidationErrors('terms');
    }

    public function test_registration_requires_company_context(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())
            ->assertStatus(400)
            ->assertJsonPath('code', 'company_required');
    }

    public function test_registration_always_creates_a_student(): void
    {
        $this->register([
            'user_type_id' => $this->admin->user_type_id,
            'is_super_admin' => true,
        ])->assertCreated()
            ->assertJsonPath('data.user.user_type.code', UserTypeCode::Student->value)
            ->assertJsonPath('data.user.is_super_admin', false);

        $this->assertDatabaseHas('users', [
            'email' => 'novo.aluno@example.com',
            'user_type_id' => $this->student->user_type_id,
            'is_super_admin' => false,
        ]);
    }

    public function test_registration_generates_a_unique_username(): void
    {
        User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
            'username' => 'novoaluno',
        ]);

        $this->register([
            'email' => 'novo.aluno@example.com',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'novo.aluno@example.com',
            'username' => 'novoaluno-2',
        ]);
    }

    public function test_registration_fails_when_company_has_no_student_type(): void
    {
        UserType::query()
            ->where('company_id', $this->company->id)
            ->where('code', UserTypeCode::Student->value)
            ->update(['status' => GeneralStatus::Inactive->value]);

        $this->register()
            ->assertStatus(422)
            ->assertJsonPath('code', 'registration_unavailable');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function register(array $overrides = []): TestResponse
    {
        return $this->withHeader('X-Company', (string) $this->company->id)
            ->postJson('/api/v1/auth/register', array_merge($this->payload(), $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'Novo Aluno',
            'email' => 'novo.aluno@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ];
    }
}
