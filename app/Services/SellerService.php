<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Seller;
use App\Repositories\SellerRepository;
use Illuminate\Support\Str;

class SellerService
{
    public function __construct(
        private readonly SellerRepository $sellers,
        private readonly DatalogService $audit,
    ) {}

    public function create(array $data): Seller
    {
        $data['code'] = $this->uniqueCode(
            $data['code'] ?? null,
            $data['name'] ?? null,
        );
        $data['share_token'] = $this->uniqueToken();

        $seller = $this->sellers->create($data);

        $this->audit->log('seller', 'create', 'seller', "Vendedor criado: {$seller->name}");

        return $seller->load(['user', 'company']);
    }

    public function update(Seller $seller, array $data): Seller
    {
        if (array_key_exists('code', $data)) {
            $code = Seller::normalizeCode($data['code']);

            if ($code === null) {
                unset($data['code']);
            } else {
                $data['code'] = $this->uniqueCode($code, null, $seller->id);
            }
        }

        $seller = $this->sellers->update($seller, $data);

        $this->audit->log('seller', 'update', 'seller', "Vendedor atualizado: {$seller->name}");

        return $seller->load(['user', 'company']);
    }

    public function delete(Seller $seller): void
    {
        if (
            $seller->orders()->exists()
            || $seller->subscriptions()->exists()
            || $seller->commissions()->exists()
        ) {
            throw new BusinessRuleException(
                'Este vendedor possui histórico de vendas ou comissões. Inative-o em vez de excluir.',
                'seller_has_history',
            );
        }

        $name = $seller->name;
        $this->sellers->delete($seller);

        $this->audit->log('seller', 'delete', 'seller', "Vendedor excluído: {$name}");
    }

    public function rotateKitToken(Seller $seller): Seller
    {
        $seller = $this->sellers->update($seller, [
            'share_token' => $this->uniqueToken(),
        ]);

        $this->audit->log('seller', 'rotate_kit', 'seller', "Link do kit renovado: {$seller->name}");

        return $seller->load(['user', 'company']);
    }

    public function kitUrl(Seller $seller): string
    {
        $seller->loadMissing('company');

        return $seller->kitUrl();
    }

    private function uniqueCode(?string $requested, ?string $name, ?int $ignoreId = null): string
    {
        $base = Seller::normalizeCode($requested)
            ?? (Str::slug((string) $name) ?: 'vendedor');

        $base = Str::limit($base, 24, '');
        $code = $base;
        $suffix = 2;

        while ($this->sellers->codeExists($code, $ignoreId)) {
            $code = Str::limit($base, 26, '').'-'.$suffix;
            $suffix++;
        }

        return $code;
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(Seller::KIT_TOKEN_LENGTH);
        } while ($this->sellers->tokenExists($token));

        return $token;
    }
}
