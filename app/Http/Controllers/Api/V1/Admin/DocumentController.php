<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreDocumentRequest;
use App\Http\Requests\Admin\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Repositories\DocumentRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends ApiController
{
    public function __construct(private readonly DocumentRepository $documents) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        return ApiResponse::paginated(
            $this->documents->paginate($this->perPage($request), $request->only(['status'])),
            DocumentResource::class,
        );
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', Document::class);

        return ApiResponse::success(new DocumentResource($this->documents->create($request->validated())), 201);
    }

    public function show(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        return ApiResponse::success(new DocumentResource($document));
    }

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        return ApiResponse::success(new DocumentResource($this->documents->update($document, $request->validated())));
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $this->documents->delete($document);

        return ApiResponse::noContent();
    }
}
