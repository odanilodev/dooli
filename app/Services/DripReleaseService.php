<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Models\AccessGrant;
use App\Models\Module;
use App\Models\StoreProduct;
use App\Models\StoreProductModuleDrip;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Liberação gradual de módulos por produto, contada a partir da compra do aluno.
 *
 * O primeiro módulo libera na hora; cada módulo seguinte libera N dias depois do
 * anterior (padrão 1). Só vale para acessos criados com `drip_product_id`.
 */
class DripReleaseService
{
    public const DEFAULT_INTERVAL_DAYS = 1;

    /**
     * Módulos do curso do produto com o intervalo configurado e o dia de liberação.
     *
     * @return array<int, array{module_id: int, name: string, interval_days: int, release_day: int}>
     */
    public function config(StoreProduct $product): array
    {
        $intervals = StoreProductModuleDrip::query()
            ->where('store_product_id', $product->id)
            ->pluck('interval_days', 'module_id');

        $day = 0;

        return $this->courseModules($product->course_id)
            ->values()
            ->map(function (Module $module, int $index) use ($intervals, &$day) {
                $interval = $index === 0
                    ? 0
                    : (int) ($intervals[$module->id] ?? self::DEFAULT_INTERVAL_DAYS);
                $day += $interval;

                return [
                    'module_id' => (int) $module->id,
                    'name' => $module->name,
                    'interval_days' => $interval,
                    'release_day' => $day,
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array{module_id: int, interval_days: int}>  $items
     */
    public function syncIntervals(StoreProduct $product, array $items): void
    {
        $allowed = $this->courseModules($product->course_id)->pluck('id')->all();

        DB::transaction(function () use ($product, $items, $allowed) {
            foreach ($items as $item) {
                $moduleId = (int) $item['module_id'];

                if (! in_array($moduleId, $allowed, true)) {
                    continue;
                }

                StoreProductModuleDrip::query()->updateOrCreate(
                    ['store_product_id' => $product->id, 'module_id' => $moduleId],
                    ['company_id' => $product->company_id, 'interval_days' => (int) $item['interval_days']],
                );
            }
        });
    }

    /**
     * Datas de liberação dos módulos do curso para o aluno na turma, por id do módulo.
     * Vazio = sem restrição (acesso total).
     *
     * @return array<int, Carbon>
     */
    public function moduleReleaseDates(User $user, int $teamId, int $courseId): array
    {
        $grants = AccessGrant::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->where('status', GeneralStatus::Active->value)
            ->get()
            ->filter(fn (AccessGrant $grant) => $grant->isActive());

        // Qualquer acesso sem liberação gradual (manual, assinatura, compra antiga) libera tudo.
        if ($grants->isEmpty() || $grants->contains(fn (AccessGrant $grant) => $grant->drip_product_id === null)) {
            return [];
        }

        $modules = $this->courseModules($courseId)->values();
        $dates = [];

        foreach ($grants as $grant) {
            $intervals = StoreProductModuleDrip::query()
                ->where('store_product_id', $grant->drip_product_id)
                ->pluck('interval_days', 'module_id');

            $offset = 0;

            foreach ($modules as $index => $module) {
                if ($index > 0) {
                    $offset += (int) ($intervals[$module->id] ?? self::DEFAULT_INTERVAL_DAYS);
                }

                $releaseAt = $grant->starts_at->copy()->addDays($offset);

                if (! isset($dates[$module->id]) || $releaseAt->lessThan($dates[$module->id])) {
                    $dates[$module->id] = $releaseAt;
                }
            }
        }

        return $dates;
    }

    /**
     * @return Collection<int, Module>
     */
    private function courseModules(?int $courseId): Collection
    {
        if (! $courseId) {
            return collect();
        }

        return Module::query()
            ->join('course_modules', 'course_modules.module_id', '=', 'modules.id')
            ->where('course_modules.course_id', $courseId)
            ->orderBy('course_modules.sort_order')
            ->orderBy('modules.id')
            ->select('modules.*')
            ->get();
    }
}
