<?php

namespace App\Services;

use App\Enums\GeneralStatus;
use App\Models\Order;
use App\Models\StoreProduct;
use Illuminate\Support\Facades\DB;

/**
 * Grants course access after a confirmed payment.
 *
 * Flow: StoreProduct -> associated Teams -> team_courses + team_users.
 */
class EnrollmentService
{
    public function __construct(private readonly DatalogService $audit) {}

    public function grantAccessForOrder(Order $order): void
    {
        $order->loadMissing('items.storeProduct');

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $this->grantAccess($order->user_id, $item->storeProduct);
            }
        });
    }

    public function grantAccess(int $userId, StoreProduct $storeProduct): void
    {
        $storeProduct->loadMissing('teams');

        foreach ($storeProduct->teams as $team) {
            if ($team->pivot->status !== GeneralStatus::Active->value) {
                continue;
            }

            $team->courses()->syncWithoutDetaching([
                $storeProduct->course_id => [
                    'company_id' => $team->company_id,
                    'status' => GeneralStatus::Active->value,
                ],
            ]);

            $team->users()->syncWithoutDetaching([
                $userId => [
                    'company_id' => $team->company_id,
                    'status' => GeneralStatus::Active->value,
                    'expired_at' => null,
                ],
            ]);
        }

        $this->audit->log(
            'enrollment',
            'grant_access',
            'store_product',
            "Acesso liberado para user {$userId} no produto {$storeProduct->id}",
            $userId,
        );
    }

    public function revokeAccess(int $userId, StoreProduct $storeProduct): void
    {
        $storeProduct->loadMissing('teams');

        foreach ($storeProduct->teams as $team) {
            $team->users()->updateExistingPivot($userId, ['status' => GeneralStatus::Inactive->value]);
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
