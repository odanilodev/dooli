<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreDocumentRequest;
use App\Http\Requests\Admin\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Repositories\DocumentRepository;
use App\Services\MaterialService;
use App\Support\ApiResponse;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentController extends ApiController
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly MaterialService $materials,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        return ApiResponse::paginated(
            $this->documents->paginate($this->perPage($request), $request->only(['search', 'status'])),
            DocumentResource::class,
        );
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', Document::class);

        $data = $request->validated();
        unset($data['file']);

        if ($file = $request->file('file')) {
            $data['path'] = $this->materials->storeDocumentFile($file, (int) app(CompanyContext::class)->id());
            $data['url'] = null;
        }

        return ApiResponse::success(new DocumentResource($this->documents->create($data)), 201);
    }

    public function show(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        return ApiResponse::success(new DocumentResource($document));
    }

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        $data = $request->validated();
        unset($data['file']);

        if ($file = $request->file('file')) {
            $data['path'] = $this->materials->storeDocumentFile($file, $document->company_id);
            $data['url'] = null;
        } else {
            $path = array_key_exists('path', $data) ? $data['path'] : $document->path;
            $url = array_key_exists('url', $data) ? $data['url'] : $document->url;

            if (empty($path) && empty($url)) {
                throw ValidationException::withMessages(['path' => 'Informe um arquivo ou um link.']);
            }
        }

        return ApiResponse::success(new DocumentResource($this->documents->update($document, $data)));
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $this->documents->delete($document);

        return ApiResponse::noContent();
    }
}
