<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_forgot_password_sends_reset_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->student->email])
            ->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($this->student, ResetPassword::class);
    }

    public function test_forgot_password_does_not_reveal_unknown_email(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])
            ->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertNothingSent();
    }

    public function test_password_reset_link_uses_the_company_domain(): void
    {
        Notification::fake();

        config()->set('app.frontend_scheme', 'https');
        $this->company->update(['domain' => 'ourovelho.test']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->student->email])
            ->assertOk();

        Notification::assertSentTo(
            $this->student,
            ResetPassword::class,
            function (ResetPassword $notification) {
                $url = $notification->toMail($this->student)->actionUrl;

                return str_starts_with($url, 'https://ourovelho.test/reset-password?')
                    && ! str_contains($url, 'company=');
            },
        );
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->student->email]);

        $token = null;

        Notification::assertSentTo(
            $this->student,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token) {
                $token = $notification->token;

                return true;
            },
        );

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $this->student->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('new-password-123', $this->student->fresh()->password));
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $this->student->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_reset_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'whatever',
            'email' => $this->student->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }
}
