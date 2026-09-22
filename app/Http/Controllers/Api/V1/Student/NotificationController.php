<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->notifications->listForUser($request->user(), $this->perPage($request));

        return ApiResponse::paginated($paginator, NotificationResource::class);
    }

    public function markAsRead(Request $request, int $notification): JsonResponse
    {
        $this->notifications->markAsRead($request->user(), $notification);

        return ApiResponse::success(['message' => 'Notificação marcada como lida.']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $this->notifications->markAllAsRead($request->user());

        return ApiResponse::success(['updated' => $updated]);
    }
}
