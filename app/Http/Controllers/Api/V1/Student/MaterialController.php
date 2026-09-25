<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\MaterialService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaterialController extends ApiController
{
    public function __construct(private readonly MaterialService $materials) {}

    public function document(Request $request, int $course, int $document): Response
    {
        return $this->materials->downloadDocument($request->user(), $course, $document);
    }

    public function attachment(Request $request, int $course, int $attachment): Response
    {
        return $this->materials->downloadAttachment($request->user(), $course, $attachment);
    }
}
