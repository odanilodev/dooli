<?php

namespace Tests\Feature;

use App\Enums\UserTypeCode;
use App\Mail\TransactionalMail;
use App\Models\Company;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
        Mail::fake();
    }

    public function test_valid_code_confirms_email_and_returns_a_token(): void
    {
        $user = $this->register();
        $code = $this->latestCode($user);

        $response = $this->verify($user->email, $code);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user' => ['id', 'email']]]);

        $user->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', [
            'user_id' => $user->id,
        ]);

        $this->withToken($response->json('data.token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_invalid_code_increments_attempts(): void
    {
        $user = $this->register();

        $this->verify($user->email, '000000')
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_verification_code');

        $record = $this->record($user);

        $this->assertSame(1, $record->attempts);
        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = $this->register();
        $code = $this->latestCode($user);

        $this->record($user)->update(['expires_at' => now()->subMinute()]);

        $this->verify($user->email, $code)
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_verification_code');
    }

    public function test_code_is_rejected_after_max_attempts(): void
    {
        $user = $this->register();
        $code = $this->latestCode($user);

        $this->record($user)->update(['attempts' => 5]);

        $this->verify($user->email, $code)
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_verification_code');
    }

    public function test_resend_rotates_the_code_after_cooldown(): void
    {
        $user = $this->register();
        $firstCode = $this->latestCode($user);

        $this->travel(61)->seconds();

        $this->postJson($this->endpoint('resend-verification'), [
            'email' => $user->email,
        ])->assertOk();

        $secondCode = $this->latestCode($user);

        Mail::assertSentTimes(TransactionalMail::class, 2);
        $this->assertNotSame($firstCode, $secondCode);

        $this->verify($user->email, $firstCode)
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_verification_code');
    }

    public function test_resend_is_ignored_during_cooldown(): void
    {
        $user = $this->register();

        $this->postJson($this->endpoint('resend-verification'), [
            'email' => $user->email,
        ])->assertOk();

        Mail::assertSentTimes(TransactionalMail::class, 1);
    }

    public function test_resend_returns_generic_response_for_unknown_email(): void
    {
        $this->postJson($this->endpoint('resend-verification'), [
            'email' => 'nao-existe@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_code_is_scoped_to_the_company(): void
    {
        $user = $this->register();
        $code = $this->latestCode($user);

        $otherCompany = Company::factory()->create();
        $otherStudentType = UserType::factory()->create([
            'company_id' => $otherCompany->id,
            'code' => UserTypeCode::Student->value,
        ]);
        User::factory()->create([
            'company_id' => $otherCompany->id,
            'user_type_id' => $otherStudentType->id,
            'email' => $user->email,
            'email_verified_at' => null,
        ]);

        $this->postJson(
            '/api/v1/public/companies/'.$otherCompany->slug.'/auth/verify-email',
            ['email' => $user->email, 'code' => $code],
        )
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_verification_code');
    }

    private function register(): User
    {
        $this->postJson('/api/v1/public/companies/'.$this->company->slug.'/auth/register', [
            'name' => 'Novo Aluno',
            'email' => 'novo.aluno@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ])->assertCreated();

        return User::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $this->company->id)
            ->where('email', 'novo.aluno@example.com')
            ->firstOrFail();
    }

    private function verify(string $email, string $code)
    {
        return $this->postJson($this->endpoint('verify-email'), [
            'email' => $email,
            'code' => $code,
        ]);
    }

    private function latestCode(User $user): string
    {
        $mail = Mail::sent(TransactionalMail::class)
            ->filter(fn (TransactionalMail $mailable) => $mailable->hasTo($user->email))
            ->last();

        $this->assertNotNull($mail?->code);

        return (string) $mail->code;
    }

    private function record(User $user): EmailVerificationCode
    {
        return EmailVerificationCode::query()
            ->withoutGlobalScope('company')
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    private function endpoint(string $action): string
    {
        return '/api/v1/public/companies/'.$this->company->slug.'/auth/'.$action;
    }
}
