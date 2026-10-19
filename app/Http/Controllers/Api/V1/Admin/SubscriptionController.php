<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\SubscriptionResource;
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
        $this->authorize('viewAny', Subscription::class);

        $paginator = Subscription::query()
            ->with(['storeProduct', 'gateway', 'user'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.(string) $request->string('search').'%';

                $query->whereHas('user', fn ($user) => $user
                    ->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search))
                    ->orWhereHas('storeProduct', fn ($product) => $product->where('title', 'like', $search));
            })
            ->latest('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, SubscriptionResource::class);
    }

    public function show(Subscription $subscription): JsonResponse
    {
        $this->authorize('view', $subscription);

        return ApiResponse::success(
            new SubscriptionResource($subscription->load(['storeProduct', 'gateway', 'user'])),
        );
    }

    public function cancel(Subscription $subscription): JsonResponse
    {
        $this->authorize('cancel', $subscription);

        return ApiResponse::success(
            new SubscriptionResource($this->subscriptions->cancel($subscription)->load(['storeProduct', 'gateway', 'user'])),
        );
    }
}
