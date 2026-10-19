<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Student\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Gateway;
use App\Models\StoreProduct;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends ApiController
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = Subscription::query()
            ->where('user_id', $request->user()->id)
            ->with(['storeProduct', 'gateway'])
            ->latest('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, SubscriptionResource::class);
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $product = StoreProduct::query()->findOrFail((int) $request->validated('store_product_id'));
        $gateway = Gateway::query()->findOrFail((int) $request->validated('gateway_id'));

        $result = $this->subscriptions->start($request->user(), $product, $gateway);

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($result['subscription']),
            'checkout_url' => $result['checkout_url'],
        ], 201);
    }

    public function show(Subscription $subscription): JsonResponse
    {
        $this->authorize('view', $subscription);

        return ApiResponse::success(
            new SubscriptionResource($subscription->load(['storeProduct', 'gateway'])),
        );
    }

    public function cancel(Subscription $subscription): JsonResponse
    {
        $this->authorize('cancel', $subscription);

        return ApiResponse::success(
            new SubscriptionResource($this->subscriptions->cancel($subscription)->load(['storeProduct', 'gateway'])),
        );
    }
}
