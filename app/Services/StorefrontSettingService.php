<?php

namespace App\Services;

use App\Enums\BannerPlacement;
use App\Enums\GeneralStatus;
use App\Models\Banner;
use App\Repositories\CompanySettingRepository;
use Illuminate\Support\Collection;

class StorefrontSettingService
{
    private const SETTINGS_KEY = 'storefront';

    public function __construct(
        private readonly CompanySettingRepository $settings,
    ) {}

    /**
     * Storefront copy configured by the company, merged with safe defaults.
     *
     * @return array<string, mixed>
     */
    public function forCompany(int $companyId): array
    {
        $stored = json_decode(
            (string) $this->settings->get($companyId, self::SETTINGS_KEY, '{}'),
            true,
        );

        return $this->merge(is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $companyId, array $data): array
    {
        $current = $this->forCompany($companyId);

        foreach (['catalog', 'internal', 'footer'] as $section) {
            if (isset($data[$section]) && is_array($data[$section])) {
                $current[$section] = array_replace($current[$section] ?? [], $data[$section]);
            }
        }

        if (array_key_exists('benefits', $data)) {
            $current['benefits'] = $data['benefits'];
        }

        $settings = $this->merge($current);

        $this->settings->put(
            $companyId,
            self::SETTINGS_KEY,
            json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        );

        return $settings;
    }

    /**
     * Active banners used by both the public and the student storefronts.
     *
     * @return Collection<int, Banner>
     */
    public function banners(int $companyId): Collection
    {
        return Banner::query()
            ->where('company_id', $companyId)
            ->where('placement', BannerPlacement::Store->value)
            ->where('status', GeneralStatus::Active->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function merge(array $data): array
    {
        $defaults = $this->defaults();

        return [
            'catalog' => $this->section($data, 'catalog', $defaults['catalog'], ['eyebrow', 'title', 'description']),
            'benefits' => $this->benefits($data['benefits'] ?? $defaults['benefits']),
            'internal' => $this->section($data, 'internal', $defaults['internal'], ['eyebrow', 'title', 'description']),
            'footer' => $this->section($data, 'footer', $defaults['footer'], ['note']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>  $defaults
     * @param  list<string>  $fields
     * @return array<string, string|null>
     */
    private function section(array $data, string $key, array $defaults, array $fields): array
    {
        $values = is_array($data[$key] ?? null) ? $data[$key] : [];
        $section = [];

        foreach ($fields as $field) {
            $section[$field] = $this->text(
                $this->sectionValue($values, $field, $defaults[$field] ?? null),
            );
        }

        return $section;
    }

    /**
     * @param  mixed  $benefits
     * @return list<array{title: string|null, description: string|null}>
     */
    private function benefits(mixed $benefits): array
    {
        if (! is_array($benefits)) {
            return [];
        }

        return collect($benefits)
            ->take(3)
            ->map(fn ($item) => [
                'title' => $this->text(is_array($item) ? ($item['title'] ?? null) : null),
                'description' => $this->text(is_array($item) ? ($item['description'] ?? null) : null),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function sectionValue(array $values, string $key, ?string $default): mixed
    {
        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'catalog' => [
                'eyebrow' => 'Catálogo',
                'title' => 'Todos os cursos',
                'description' => 'Encontre o conteúdo ideal para o seu próximo passo.',
            ],
            'benefits' => [
                [
                    'title' => 'Conteúdo atualizado',
                    'description' => 'Aulas e materiais sempre relevantes.',
                ],
                [
                    'title' => 'Compra segura',
                    'description' => 'Pagamento processado com proteção.',
                ],
                [
                    'title' => 'Acesso em todos os dispositivos',
                    'description' => 'Estude onde e quando quiser.',
                ],
            ],
            'internal' => [
                'eyebrow' => 'Loja',
                'title' => 'Conteúdos extras',
                'description' => 'Conteúdos extras para acelerar seus estudos.',
            ],
            'footer' => [
                'note' => 'Pagamento processado com segurança.',
            ],
        ];
    }
}
