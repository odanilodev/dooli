<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Enums\GeneralStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Gateway;
use App\Services\WebhookService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends ApiController
{
    public function __construct(private readonly WebhookService $webhooks) {}

    public function handle(Request $request): JsonResponse
    {
        $gateway = (string) $request->route('gateway');

        // Tenant is mandatory: gateway codes are only unique per company.
        $model = Gateway::query()
            ->where('code', $gateway)
            ->where('status', GeneralStatus::Active->value)
            ->firstOrFail();

        return ApiResponse::success($this->webhooks->handle($model, $request), 202);
    }
}
