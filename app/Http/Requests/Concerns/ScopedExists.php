<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Eloquent global scopes are not applied to validation rules, so a raw
 * `Rule::exists` would accept ids from other tenants. This helper constrains
 * the existence check to the authenticated user's company.
 */
trait ScopedExists
{
    protected function companyExists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)
            ->where('company_id', (int) $this->user()?->company_id);
    }
}
