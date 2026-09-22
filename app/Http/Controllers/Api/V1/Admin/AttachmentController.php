<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreAttachmentRequest;
use App\Http\Requests\Admin\UpdateAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Repositories\AttachmentRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttachmentController extends ApiController
{
    public function __construct(private readonly AttachmentRepository $attachments) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Attachment::class);

        return ApiResponse::paginated(
            $this->attachments->paginate($this->perPage($request), $request->only(['status'])),
            AttachmentResource::class,
        );
    }

    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        $this->authorize('create', Attachment::class);

        return ApiResponse::success(new AttachmentResource($this->attachments->create($request->validated())), 201);
    }

    public function show(Attachment $attachment): JsonResponse
    {
        $this->authorize('view', $attachment);

        return ApiResponse::success(new AttachmentResource($attachment));
    }

    public function update(UpdateAttachmentRequest $request, Attachment $attachment): JsonResponse
    {
        $this->authorize('update', $attachment);

        return ApiResponse::success(new AttachmentResource($this->attachments->update($attachment, $request->validated())));
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        $this->attachments->delete($attachment);

        return ApiResponse::noContent();
    }
}
