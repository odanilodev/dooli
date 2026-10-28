<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DTO\CreatePaymentDTO;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\RefundPaymentRequest;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Http\Resources\AdminPaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends ApiController
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $paginator = $this->payments->paginate(
            filters: $request->only([
                'search', 'status', 'order_id', 'gateway_id', 'method',
                'date_from', 'date_to', 'sort', 'direction',
            ]),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, AdminPaymentResource::class);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        return ApiResponse::success($this->payments->summary(
            $request->only(['gateway_id', 'method', 'date_from', 'date_to']),
        ));
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $payment = $this->payments->create(CreatePaymentDTO::fromArray($request->validated()));

        return ApiResponse::success(new AdminPaymentResource($payment), 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        return ApiResponse::success(new AdminPaymentResource($this->payments->find($payment->id)));
    }

    public function refund(RefundPaymentRequest $request, Payment $payment): JsonResponse
    {
        $this->authorize('update', $payment);

        $refunded = $this->payments->refund(
            $payment,
            $request->validated('amount') ? (float) $request->validated('amount') : null,
            $request->validated('reason'),
        );

        return ApiResponse::success(new AdminPaymentResource($refunded->load('refunds')));
    }
}
