<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\ReportProgressRequest;
use App\Models\Lesson;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends ApiController
{
    public function __construct(private readonly ReportService $reports) {}

    public function progress(ReportProgressRequest $request): JsonResponse
    {
        $this->authorize('viewReports');

        $filters = $request->filters();
        $filters['per_page'] = $this->perPage($request);

        $result = $this->reports->progress($filters);

        return ApiResponse::success($result['data'], 200, $result['meta']);
    }

    public function student(ReportProgressRequest $request, User $user): JsonResponse
    {
        $this->authorize('viewReports');

        return ApiResponse::success($this->reports->student($user->id, $request->filters()));
    }

    public function lesson(ReportProgressRequest $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('viewReports');

        return ApiResponse::success($this->reports->lesson($lesson->id, $request->filters()));
    }

    public function activity(ReportProgressRequest $request): JsonResponse
    {
        $this->authorize('viewReports');

        return ApiResponse::success($this->reports->activity($request->filters()));
    }

    public function export(ReportProgressRequest $request): StreamedResponse
    {
        $this->authorize('viewReports');

        $filters = $request->filters();
        $filters['page'] = (int) $request->integer('page', 1);

        $export = $this->reports->export($filters);

        return response()->streamDownload(function () use ($export) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $export['headers']);

            foreach ($export['rows'] as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, 'relatorio-progresso-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
