<?php

namespace App\Support;

/**
 * Holds the current tenant (company) for the request lifecycle.
 *
 * Centralizing the tenant here allows global scopes and repositories to
 * isolate every query by company_id without repeating the filter.
 */
class CompanyContext
{
    protected ?int $companyId = null;

    public function set(?int $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function has(): bool
    {
        return $this->companyId !== null;
    }

    public function forget(): void
    {
        $this->companyId = null;
    }
}
