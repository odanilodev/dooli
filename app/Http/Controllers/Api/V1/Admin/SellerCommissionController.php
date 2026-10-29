<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SellerCommissionStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\BulkMarkSellerCommissionsPaidRequest;
use App\Http\Requests\Admin\MarkSellerCommissionPaidRequest;
use App\Http\Resources\SellerCommissionResource;
use App\Models\SellerCommission;
use App\Repositories\SellerCommissionRepository;
use App\Services\SellerCommissionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerCommissionController extends ApiController
{
    public function __construct(
        private readonly SellerCommissionRepository $commissions,
        private readonly SellerCommissionService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SellerCommission::class);

        $paginator = $this->commissions->paginateDetailed(
            filters: $request->only([
                'search', 'status', 'seller_id', 'product_id', 'order_id',
                'date_from', 'date_to', 'sort', 'direction',
            ]),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, SellerCommissionResource::class);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SellerCommission::class);

        return ApiResponse::success($this->commissions->summary(
            $request->only([
                'search', 'status', 'seller_id', 'product_id', 'order_id',
                'date_from', 'date_to',
            ]),
        ));
    }

    public function markPaid(MarkSellerCommissionPaidRequest $request, SellerCommission $commission): JsonResponse
    {
        $this->authorize('update', $commission);

        return ApiResponse::success(
            new SellerCommissionResource(
                $this->service->markPaid($commission, $request->validated()),
            ),
        );
    }

    public function bulkMarkPaid(BulkMarkSellerCommissionsPaidRequest $request): JsonResponse
    {
        $this->authorize('updateAny', SellerCommission::class);

        $count = $this->service->markManyPaid(
            $request->validated('ids'),
            $request->safe()->only(['paid_at', 'payment_reference', 'payment_notes']),
        );

        return ApiResponse::success([
            'updated' => $count,
            'message' => $count === 1
                ? '1 comissão marcada como paga.'
                : "{$count} comissões marcadas como pagas.",
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', SellerCommission::class);

        $rows = $this->commissions->filtered($request->only([
            'search', 'status', 'seller_id', 'product_id', 'order_id',
            'date_from', 'date_to',
        ]));

        return response()->streamDownload(function () use ($rows) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $headers = [
                'Vendedor', 'Código', 'Pedido', 'Aluno', 'Produto', 'Qtd.',
                'Base', 'Percentual', 'Comissão', 'Estornado', 'Líquido',
                'Status', 'Pago em', 'Referência', 'Criado em',
            ];
            $sheet->fromArray($headers, null, 'A1');

            $rowIndex = 2;

            foreach ($rows as $commission) {
                $values = [
                    $commission->seller?->name,
                    $commission->seller?->code,
                    $commission->order_id,
                    $commission->order?->user?->name,
                    $commission->orderItem?->product_title,
                    (int) ($commission->orderItem?->quantity ?? 0),
                    (float) $commission->base_amount,
                    (float) $commission->commission_percent,
                    (float) $commission->amount,
                    (float) $commission->refunded_amount,
                    $commission->netAmount(),
                    $this->statusLabel($commission->status),
                    $commission->paid_at?->format('d/m/Y H:i'),
                    $commission->payment_reference,
                    $commission->created_at?->format('d/m/Y H:i'),
                ];

                foreach ($values as $columnIndex => $value) {
                    $coordinate = Coordinate::stringFromColumnIndex($columnIndex + 1).$rowIndex;
                    $numeric = is_int($value) || is_float($value);
                    $sheet->setCellValueExplicit(
                        $coordinate,
                        $numeric ? $value : (string) ($value ?? ''),
                        $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING,
                    );
                }

                $rowIndex++;
            }

            $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$lastColumn}1");

            foreach (range(1, count($headers)) as $columnIndex) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setWidth(18);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');

            $spreadsheet->disconnectWorksheets();
        }, 'comissoes-'.now()->format('Y-m-d-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            SellerCommissionStatus::Approved->value => 'A pagar',
            SellerCommissionStatus::Paid->value => 'Paga',
            SellerCommissionStatus::Reversed->value => 'Estornada',
            default => $status,
        };
    }
}
