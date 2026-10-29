<?php

namespace App\Services;

use App\Models\Seller;
use App\Models\User;
use App\Repositories\SellerRepository;

class SellerAttributionService
{
    public const REFERRAL_DAYS = 30;

    public function __construct(private readonly SellerRepository $sellers) {}

    /**
     * Resolve the seller for a checkout. An explicit valid code always wins
     * and refreshes the user's last attribution. Otherwise the user's stored
     * attribution is used while it is still valid.
     */
    public function resolveForUser(User $user, ?string $code): ?Seller
    {
        $seller = $this->resolveByCode($code);

        if ($seller) {
            $this->remember($user, $seller);

            return $seller;
        }

        $user->loadMissing('referredSeller');

        if (
            $user->referred_seller_id
            && $user->referral_expires_at?->isFuture()
            && $user->referredSeller?->isActive()
        ) {
            return $user->referredSeller;
        }

        return null;
    }

    public function rememberFromCode(User $user, ?string $code): void
    {
        $seller = $this->resolveByCode($code);

        if ($seller) {
            $this->remember($user, $seller);
        }
    }

    private function resolveByCode(?string $code): ?Seller
    {
        $code = Seller::normalizeCode($code);

        if ($code === null) {
            return null;
        }

        return $this->sellers->findActiveByCode($code);
    }

    private function remember(User $user, Seller $seller): void
    {
        $user->update([
            'referred_seller_id' => $seller->id,
            'referral_expires_at' => now()->addDays(self::REFERRAL_DAYS),
        ]);
    }
}
