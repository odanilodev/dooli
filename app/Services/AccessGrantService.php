<?php

namespace App\Services;

use App\Enums\AccessGrantSource;
use App\Enums\GeneralStatus;
use App\Models\AccessGrant;
use App\Models\Order;
use App\Models\StoreProduct;
use App\Models\Subscription;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccessGrantService
{
    public function grantForOrder(Order $order, ?Carbon $grantedAt = null): void
    {
        $order->loadMissing('items.storeProduct.teams');

        $grantedAt ??= now();

        foreach ($order->items as $item) {
            $product = $item->storeProduct;

            if (! $product) {
                continue;
            }

            foreach ($this->activeTeams($product) as $team) {
                $this->grant(
                    companyId: (int) $order->company_id,
                    userId: (int) $order->user_id,
                    teamId: (int) $team->id,
                    sourceType: AccessGrantSource::Order->value,
                    sourceId: (int) $order->id,
                    startsAt: $grantedAt,
                    expiresAt: $product->accessExpiresAt($grantedAt),
                );
            }
        }
    }

    public function grantForSubscription(
        Subscription $subscription,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): void {
        $subscription->loadMissing('storeProduct.teams');

        $product = $subscription->storeProduct;

        if (! $product) {
            return;
        }

        foreach ($this->activeTeams($product) as $team) {
            $this->grant(
                companyId: (int) $subscription->company_id,
                userId: (int) $subscription->user_id,
                teamId: (int) $team->id,
                sourceType: AccessGrantSource::Subscription->value,
                sourceId: (int) $subscription->id,
                startsAt: $periodStart,
                expiresAt: $periodEnd,
            );
        }
    }

    public function grantForProduct(
        int $companyId,
        int $userId,
        StoreProduct $product,
        string $sourceType,
        int $sourceId,
        Carbon $startsAt,
        ?Carbon $expiresAt = null,
    ): void {
        $product->loadMissing('teams');

        foreach ($this->activeTeams($product) as $team) {
            $this->grant(
                companyId: $companyId,
                userId: $userId,
                teamId: (int) $team->id,
                sourceType: $sourceType,
                sourceId: $sourceId,
                startsAt: $startsAt,
                expiresAt: $expiresAt ?? $product->accessExpiresAt($startsAt),
                dripProductId: $sourceType === AccessGrantSource::Order->value && $product->drip_enabled
                    ? (int) $product->id
                    : null,
            );
        }
    }

    public function grantManual(
        int $companyId,
        int $userId,
        int $teamId,
        ?Carbon $expiresAt = null,
    ): void {
        $this->grant(
            companyId: $companyId,
            userId: $userId,
            teamId: $teamId,
            sourceType: AccessGrantSource::Manual->value,
            sourceId: 0,
            startsAt: now(),
            expiresAt: $expiresAt,
        );
    }

    public function extendSource(string $sourceType, int $sourceId, Carbon $expiresAt): void
    {
        $grants = AccessGrant::query()
            ->withoutGlobalScope('company')
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->get();

        foreach ($grants as $grant) {
            $grant->update([
                'expires_at' => $expiresAt,
                'status' => GeneralStatus::Active->value,
            ]);

            $this->syncTeamMembership(
                (int) $grant->company_id,
                (int) $grant->team_id,
                (int) $grant->user_id,
            );
        }
    }

    public function revokeSource(string $sourceType, int $sourceId): void
    {
        $grants = AccessGrant::query()
            ->withoutGlobalScope('company')
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->get();

        foreach ($grants as $grant) {
            $grant->update(['status' => GeneralStatus::Inactive->value]);
            $this->syncTeamMembership(
                (int) $grant->company_id,
                (int) $grant->team_id,
                (int) $grant->user_id,
            );
        }
    }

    public function syncTeamMembership(int $companyId, int $teamId, int $userId): void
    {
        $grants = AccessGrant::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->where('status', GeneralStatus::Active->value)
            ->get()
            ->filter(fn (AccessGrant $grant) => $grant->isActive());

        $membership = DB::table('team_users')
            ->where('company_id', $companyId)
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->first();

        if ($grants->isEmpty()) {
            if ($membership) {
                DB::table('team_users')
                    ->where('id', $membership->id)
                    ->update([
                        'status' => GeneralStatus::Inactive->value,
                        'expired_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            return;
        }

        $expiresAt = $grants->contains(fn (AccessGrant $grant) => $grant->expires_at === null)
            ? null
            : $grants->max('expires_at');

        $values = [
            'company_id' => $companyId,
            'team_id' => $teamId,
            'user_id' => $userId,
            'status' => GeneralStatus::Active->value,
            'expired_at' => $expiresAt,
            'updated_at' => now(),
        ];

        if ($membership) {
            DB::table('team_users')->where('id', $membership->id)->update($values);
        } else {
            $values['created_at'] = now();
            DB::table('team_users')->insert($values);
        }
    }

    private function grant(
        int $companyId,
        int $userId,
        int $teamId,
        string $sourceType,
        int $sourceId,
        Carbon $startsAt,
        ?Carbon $expiresAt,
        ?int $dripProductId = null,
    ): void {
        AccessGrant::query()->withoutGlobalScope('company')->updateOrCreate([
            'company_id' => $companyId,
            'user_id' => $userId,
            'team_id' => $teamId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ], [
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'status' => GeneralStatus::Active->value,
            'drip_product_id' => $dripProductId,
        ]);

        $this->syncTeamMembership($companyId, $teamId, $userId);
    }

    /**
     * @return iterable<int, Team>
     */
    private function activeTeams(StoreProduct $product): iterable
    {
        return $product->teams
            ->filter(fn ($team) => $team->pivot->status === GeneralStatus::Active->value
                && $team->status === GeneralStatus::Active->value);
    }
}
