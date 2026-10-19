<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\DTO\CreateOrderDTO;
use App\DTO\CreatePaymentDTO;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Store\StoreOrderRequest;
use App\Http\Requests\Student\PayOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Gateway;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly PaymentService $payments,
    ) {}

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

    public function pay(PayOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        if ($order->status !== OrderStatus::Pending->value) {
            throw new BusinessRuleException('Somente pedidos pendentes podem ser pagos.', 'order_not_payable');
        }

        $gateway = Gateway::query()->findOrFail((int) $request->validated('gateway_id'));

        if ($request->validated('method') === 'credit_card') {
            $result = $this->payments->createCardPreference($order, $gateway);

            return ApiResponse::success([
                'payment' => new PaymentResource($result['payment']),
                'checkout_url' => $result['checkout_url'],
            ], 201);
        }

        $payment = $this->payments->create(new CreatePaymentDTO(
            orderId: (int) $order->id,
            gatewayId: (int) $gateway->id,
            method: (string) $request->validated('method'),
            amount: (float) $order->total,
            metadata: [
                'payment_method_id' => $request->validated('method'),
            ],
        ));

        return ApiResponse::success(new PaymentResource($payment), 201);
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
