<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\ReportProgressRequest;
use App\Models\Lesson;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

        $export = $this->reports->export($request->filters());

        return response()->streamDownload(function () use ($export) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $data = array_merge([$export['headers']], $export['rows']);
            $widths = array_fill(0, count($export['headers']), 10);

            foreach ($data as $rowIndex => $row) {
                foreach ($row as $columnIndex => $value) {
                    $coordinate = Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 1);
                    $numeric = is_int($value) || is_float($value);
                    $widths[$columnIndex] = max($widths[$columnIndex], mb_strwidth((string) $value));

                    $sheet->setCellValueExplicit(
                        $coordinate,
                        $numeric ? $value : (string) $value,
                        $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING,
                    );
                }
            }

            foreach ($widths as $columnIndex => $width) {
                $column = Coordinate::stringFromColumnIndex($columnIndex + 1);
                $sheet->getColumnDimension($column)->setWidth(min(60, $width + 2));
            }

            $lastColumn = Coordinate::stringFromColumnIndex(count($export['headers']));

            $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$lastColumn}1");

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');

            $spreadsheet->disconnectWorksheets();
        }, 'relatorio-progresso-'.now()->format('Y-m-d-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
