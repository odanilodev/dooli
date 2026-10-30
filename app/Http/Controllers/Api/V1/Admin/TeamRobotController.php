<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\RobotResource;
use App\Models\Robot;
use App\Models\Team;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Liberação das ferramentas (robôs) por turma. Quem está na turma com acesso ativo
 * passa a ver a ferramenta; o vínculo do aluno com a turma vem da compra ou do admin.
 */
class TeamRobotController extends ApiController
{
    /** Catálogo de ferramentas ativas da empresa, para o admin escolher. */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Team::class);

        return ApiResponse::collection(RobotResource::collection(
            Robot::query()->where('status', 'active')->orderBy('sort_order')->orderBy('id')->get(),
        ));
    }

    public function robots(Team $team): JsonResponse
    {
        $this->authorize('view', $team);

        return ApiResponse::collection(RobotResource::collection($team->robots()->orderBy('robots.sort_order')->get()));
    }

    public function attach(Request $request, Team $team): JsonResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate(['robot_id' => ['required', 'integer']]);

        $robot = Robot::query()->findOrFail($validated['robot_id']);

        $team->robots()->syncWithoutDetaching([$robot->id => ['company_id' => $team->company_id]]);

        return ApiResponse::success(['message' => 'Ferramenta liberada para a turma.']);
    }

    public function detach(Team $team, int $robot): JsonResponse
    {
        $this->authorize('update', $team);

        $team->robots()->detach($robot);

        return ApiResponse::success(['message' => 'Ferramenta removida da turma.']);
    }
}
