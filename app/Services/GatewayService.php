<?php

namespace App\Services;

use App\Models\Gateway;
use App\Repositories\GatewayRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GatewayService
{
    public function __construct(
        private readonly GatewayRepository $gateways,
        private readonly DatalogService $audit,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->gateways->paginate(
            perPage: $perPage,
            filters: $filters,
            sort: $filters['sort'] ?? null,
            direction: $filters['direction'] ?? 'desc',
        );
    }

    public function find(int $id): Gateway
    {
        return $this->gateways->findOrFail($id);
    }

    public function create(array $data): Gateway
    {
        $gateway = $this->gateways->create($data);

        $this->audit->log('gateway', 'create', 'gateway', "Gateway criado: {$gateway->name}");

        return $gateway;
    }

    public function update(Gateway $gateway, array $data): Gateway
    {
        // Never overwrite credentials with an empty value.
        if (array_key_exists('credentials', $data) && empty($data['credentials'])) {
            unset($data['credentials']);
        }

        // Mescla com as credenciais já salvas: enviar só um campo (ex.: public_key)
        // não pode apagar o access_token e o webhook_secret existentes.
        if (isset($data['credentials']) && is_array($data['credentials'])) {
            $data['credentials'] = array_merge($gateway->credentials ?? [], $data['credentials']);
        }

        return $this->gateways->update($gateway, $data);
    }

    public function delete(Gateway $gateway): void
    {
        $gateway->delete();

        $this->audit->log('gateway', 'delete', 'gateway', "Gateway excluído: {$gateway->name}");
    }
}
