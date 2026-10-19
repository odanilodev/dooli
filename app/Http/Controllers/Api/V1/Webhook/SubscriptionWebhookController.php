<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Enums\GeneralStatus;
use App\Exceptions\ApiException;
use App\Exceptions\ResourceNotFoundException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Gateway;
use App\Models\Subscription;
use App\Services\Payments\GatewayManager;
use App\Services\SubscriptionService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionWebhookController extends ApiController
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $gateway = (string) $request->route('gateway');

        $model = Gateway::query()
            ->where('code', $gateway)
            ->where('status', GeneralStatus::Active->value)
            ->firstOrFail();

        $adapter = $this->gateways->for($model);

        if (! $adapter->verifyWebhook($request, $model)) {
            throw new ApiException('Assinatura do webhook inválida.', 401, 'invalid_webhook_signature');
        }

        $payload = $request->all();

        if (method_exists($adapter, 'resolveSubscriptionWebhook')) {
            $payload = $adapter->resolveSubscriptionWebhook($payload, $model);
        }

        $providerId = $payload['provider_subscription_id']
            ?? $payload['data']['id']
            ?? $payload['id']
            ?? null;

        $externalReference = $payload['external_reference']
            ?? $payload['data']['external_reference']
            ?? null;

        if (! $providerId && ! $externalReference) {
            throw new ResourceNotFoundException('Assinatura não encontrada para o webhook.');
        }

        $subscription = Subscription::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $model->company_id)
            ->when($providerId, fn ($query) => $query->where('provider_subscription_id', $providerId))
            ->when(! $providerId && $externalReference, fn ($query) => $query->whereKey((int) $externalReference))
            ->first();

        if (! $subscription) {
            throw new ResourceNotFoundException('Assinatura não encontrada para o webhook.');
        }

        app(CompanyContext::class)->set((int) $subscription->company_id);

        $status = $payload['status'] ?? $payload['data']['status'] ?? null;

        $this->subscriptions->handleWebhook($subscription, [
            'status' => $status,
            'transaction_id' => $payload['transaction_id'] ?? $payload['data']['id'] ?? null,
            'paid_at' => $payload['paid_at'] ?? $payload['date_approved'] ?? null,
        ]);

        return ApiResponse::success(['status' => 'queued'], 202);
    }
}
