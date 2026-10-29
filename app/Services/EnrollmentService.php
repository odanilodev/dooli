<?php

namespace App\Services;

use App\Enums\AccessGrantSource;
use App\Enums\GeneralStatus;
use App\Models\AccessGrant;
use App\Models\Order;
use App\Models\StoreProduct;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Grants course access after a confirmed payment.
 *
 * Flow: StoreProduct -> associated Teams -> team_courses + team_users.
 */
class EnrollmentService
{
    public function __construct(
        private readonly DatalogService $audit,
        private readonly AccessGrantService $accessGrants,
    ) {}

    public function grantAccessForOrder(Order $order, ?Carbon $grantedAt = null): void
    {
        $order->loadMissing('items.storeProduct');

        DB::transaction(function () use ($order, $grantedAt) {
            foreach ($order->items as $item) {
                if ($item->storeProduct) {
                    $this->grantAccess(
                        $order->user_id,
                        $item->storeProduct,
                        $grantedAt,
                        AccessGrantSource::Order->value,
                        (int) $order->id,
                    );
                }
            }
        });
    }

    public function grantAccessForSubscription(
        Subscription $subscription,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): void {
        DB::transaction(function () use ($subscription, $periodStart, $periodEnd) {
            $this->accessGrants->grantForSubscription($subscription, $periodStart, $periodEnd);
        });
    }

    public function grantAccess(
        int $userId,
        StoreProduct $storeProduct,
        ?Carbon $grantedAt = null,
        string $sourceType = AccessGrantSource::Manual->value,
        int $sourceId = 0,
    ): void {
        $storeProduct->loadMissing('teams');

        $grantedAt ??= now();

        foreach ($storeProduct->teams as $team) {
            if ($team->pivot->status !== GeneralStatus::Active->value) {
                continue;
            }

            if ($team->status !== GeneralStatus::Active->value) {
                continue;
            }

            if (! $storeProduct->course_id) {
                continue; // produto só de ferramenta: não há curso para ligar à turma
            }

            $team->courses()->syncWithoutDetaching([
                $storeProduct->course_id => [
                    'company_id' => $team->company_id,
                    'status' => GeneralStatus::Active->value,
                ],
            ]);
        }

        $this->accessGrants->grantForProduct(
            companyId: (int) $storeProduct->company_id,
            userId: $userId,
            product: $storeProduct,
            sourceType: $sourceType,
            sourceId: $sourceId,
            startsAt: $grantedAt,
        );

        $this->audit->log(
            'enrollment',
            'grant_access',
            'store_product',
            "Acesso liberado para user {$userId} no produto {$storeProduct->id}",
            $userId,
        );
    }

    public function revokeAccess(
        int $userId,
        StoreProduct $storeProduct,
        string $sourceType = AccessGrantSource::Manual->value,
        int $sourceId = 0,
    ): void {
        $storeProduct->loadMissing('teams');

        foreach ($storeProduct->teams as $team) {
            $grant = AccessGrant::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $storeProduct->company_id)
                ->where('user_id', $userId)
                ->where('team_id', $team->id)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->first();

            if ($grant) {
                $grant->update(['status' => GeneralStatus::Inactive->value]);
            }

            $this->accessGrants->syncTeamMembership(
                (int) $storeProduct->company_id,
                (int) $team->id,
                $userId,
            );
        }

        $this->audit->log(
            'enrollment',
            'revoke_access',
            'store_product',
            "Acesso revogado para user {$userId} no produto {$storeProduct->id}",
            $userId,
        );
    }
}
