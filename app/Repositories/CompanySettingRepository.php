<?php

namespace App\Repositories;

use App\Models\CompanySetting;

class CompanySettingRepository extends BaseRepository
{
    protected array $filterable = ['name', 'company_id'];

    public function __construct()
    {
        parent::__construct(new CompanySetting);
    }

    public function get(int $companyId, string $name, mixed $default = null): mixed
    {
        $setting = $this->query()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->first();

        return $setting?->value ?? $default;
    }

    public function put(int $companyId, string $name, ?string $value): CompanySetting
    {
        return $this->query()->updateOrCreate(
            ['company_id' => $companyId, 'name' => $name],
            ['value' => $value],
        );
    }
}
