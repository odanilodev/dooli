<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\DTO\CreateOrderDTO;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Store\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->orders->paginate(
            filters: ['user_id' => $request->user()->id, ...$request->only(['status', 'sort', 'direction'])],
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, OrderResource::class);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $order = $this->orders->create(
            CreateOrderDTO::fromArray($request->validated(), $request->user()->id),
        );

        return ApiResponse::success(new OrderResource($order), 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return ApiResponse::success(new OrderResource($this->orders->find($order->id)));
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        if ((int) $order->user_id !== (int) $request->user()->id) {
            $this->authorize('view', $order);
        }

        return ApiResponse::success(new OrderResource($this->orders->cancel($order)));
    }
}
