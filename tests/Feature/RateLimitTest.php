<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $this->admin->email,
                'password' => 'wrongpass',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $this->admin->email,
            'password' => 'wrongpass',
        ])->assertStatus(429);
    }
}
