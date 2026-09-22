<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Reset auth guards whenever a token is applied.
     *
     * Multiple requests with different users share the same application
     * instance in a test, and the auth guard caches the resolved user.
     */
    public function withToken(string $token, string $type = 'Bearer'): static
    {
        $this->app['auth']->forgetGuards();

        return parent::withToken($token, $type);
    }
}
