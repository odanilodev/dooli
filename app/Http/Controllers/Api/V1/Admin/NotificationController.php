<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Repositories\NotificationRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function __construct(private readonly NotificationRepository $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Notification::class);

        $paginator = $this->notifications->paginate(
            perPage: $this->perPage($request),
            filters: $request->only(['status', 'team_id', 'type_notification_id']),
            with: ['type', 'team'],
        );

        return ApiResponse::paginated($paginator, NotificationResource::class);
    }

    public function show(Notification $notification): JsonResponse
    {
        $this->authorize('view', $notification);

        return ApiResponse::success(new NotificationResource($notification->load(['type', 'team'])));
    }
}
