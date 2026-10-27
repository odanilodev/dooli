<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Order;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DashboardController extends ApiController
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['7', '30', '90', 'all'])],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'required_with:from', 'after_or_equal:from'],
        ]);

        if (! empty($validated['from']) && ! empty($validated['to'])) {
            $from = Carbon::parse($validated['from'])->startOfDay();
            $to = Carbon::parse($validated['to'])->startOfDay();

            if ($to->greaterThan($from->copy()->addMonthsNoOverflow(3))) {
                throw ValidationException::withMessages([
                    'to' => 'O período personalizado não pode exceder 3 meses.',
                ]);
            }
        }

        return ApiResponse::success($this->dashboard->overview(
            period: $validated['period'] ?? '30',
            from: $validated['from'] ?? null,
            to: $validated['to'] ?? null,
        ));
    }
}
