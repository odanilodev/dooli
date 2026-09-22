<?php

namespace App\Concerns;

use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applies multi-tenant isolation.
 *
 * - Global scope filtering by company_id whenever a CompanyContext is set.
 * - Auto-fills company_id on create.
 * - Provides a company() relation and a forCompany() scope for explicit use.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $context = app(CompanyContext::class);

            if ($context->has()) {
                $builder->where(
                    $builder->getModel()->getTable().'.company_id',
                    $context->id(),
                );
            }
        });

        static::creating(function ($model) {
            $context = app(CompanyContext::class);

            if (empty($model->company_id) && $context->has()) {
                $model->company_id = $context->id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->getTable().'.company_id', $companyId);
    }
}
