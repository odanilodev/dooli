<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\AdminOrderDetailResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends ApiController
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $paginator = $this->orders->paginate(
            filters: $request->only([
                'search', 'status', 'source', 'user_id', 'seller_id',
                'date_from', 'date_to', 'sort', 'direction',
            ]),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, OrderResource::class);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        return ApiResponse::success($this->orders->summary(
            $request->only(['search', 'seller_id', 'source', 'date_from', 'date_to']),
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Order::class);

        $rows = $this->orders->exportRows($request->only([
            'search', 'status', 'source', 'seller_id', 'date_from', 'date_to',
            'sort', 'direction',
        ]));

        return response()->streamDownload(function () use ($rows) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $headers = [
                'Pedido', 'Data', 'Cliente', 'E-mail', 'Produtos', 'Tipo',
                'Origem', 'Vendedor', 'Cupom', 'Subtotal', 'Desconto',
                'Total', 'Status', 'Pagamento', 'Método',
            ];
            $sheet->fromArray($headers, null, 'A1');

            $rowIndex = 2;

            foreach ($rows as $order) {
                $products = $order->items
                    ->map(fn ($item) => trim(sprintf(
                        '%s (%d)',
                        $item->product_title ?: $item->storeProduct?->title,
                        (int) $item->quantity,
                    )))
                    ->implode('; ');

                $values = [
                    $order->id,
                    $order->created_at?->format('d/m/Y H:i'),
                    $order->user?->name,
                    $order->user?->email,
                    $products,
                    $order->subscription_id ? 'Assinatura' : 'Compra única',
                    $this->sourceLabel($order->source),
                    $order->seller?->name,
                    $order->coupon?->code,
                    (float) $order->subtotal,
                    (float) $order->discount,
                    (float) $order->total,
                    $this->statusLabel($order->status),
                    $this->statusLabel($order->latestPayment?->status),
                    $this->paymentMethodLabel($order->latestPayment?->method),
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
        }, 'vendas-'.now()->format('Y-m-d-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return ApiResponse::success(new AdminOrderDetailResource($this->orders->find($order->id)));
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            OrderStatus::Pending->value => 'Pendente',
            OrderStatus::Paid->value => 'Pago',
            OrderStatus::Canceled->value => 'Cancelado',
            OrderStatus::Refunded->value => 'Estornado',
            'failed' => 'Recusado',
            default => $status ?? '—',
        };
    }

    private function sourceLabel(?string $source): string
    {
        return match ($source) {
            OrderSource::Store->value => 'Loja',
            OrderSource::Manual->value => 'Manual',
            OrderSource::Unknown->value => 'Desconhecida',
            default => $source ?? '—',
        };
    }

    private function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'credit_card' => 'Cartão',
            'pix' => 'PIX',
            default => $method ?? '—',
        };
    }
}
