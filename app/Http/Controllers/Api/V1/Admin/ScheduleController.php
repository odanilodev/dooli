<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ScheduleLevel;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\UpsertScheduleRequest;
use App\Models\Team;
use App\Services\ScheduleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ScheduleController extends ApiController
{
    public function __construct(private readonly ScheduleService $schedules) {}

    public function index(Team $team): JsonResponse
    {
        $this->authorize('view', $team);

        return ApiResponse::success($this->schedules->tree($team));
    }

    public function upsert(UpsertScheduleRequest $request, Team $team): JsonResponse
    {
        $this->authorize('update', $team);

        $this->schedules->set(
            team: $team,
            level: ScheduleLevel::from($request->validated('schedulable_type')),
            id: (int) $request->validated('schedulable_id'),
            availableAt: $request->validated('available_at'),
        );

        return ApiResponse::success(['message' => 'Agendamento salvo.']);
    }
}
