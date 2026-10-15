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

    /**
     * All settings of a company as a name => value map.
     *
     * @return array<string, string|null>
     */
    public function all(int $companyId): array
    {
        return $this->query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->get()
            ->mapWithKeys(fn (CompanySetting $setting) => [$setting->name => $setting->value])
            ->all();
    }

    public function get(int $companyId, string $name, mixed $default = null): mixed
    {
        $setting = $this->query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->first();

        return $setting?->value ?? $default;
    }

    public function put(int $companyId, string $name, ?string $value): CompanySetting
    {
        return $this->query()
            ->withoutGlobalScope('company')
            ->updateOrCreate(
                ['company_id' => $companyId, 'name' => $name],
                ['value' => $value],
            );
    }
}
