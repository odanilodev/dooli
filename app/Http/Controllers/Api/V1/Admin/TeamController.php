<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DTO\CreateTeamDTO;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\AddStudentRequest;
use App\Http\Requests\Admin\StoreTeamRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Http\Requests\Admin\UpdateTeamRequest;
use App\Http\Resources\CourseResource;
use App\Http\Resources\TeamResource;
use App\Http\Resources\TeamStudentResource;
use App\Models\Team;
use App\Services\TeamService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends ApiController
{
    public function __construct(private readonly TeamService $teams) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Team::class);

        $paginator = $this->teams->paginate(
            filters: $request->only(['search', 'status', 'sort', 'direction']),
            perPage: $this->perPage($request),
        );

        return ApiResponse::paginated($paginator, TeamResource::class);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $this->authorize('create', Team::class);

        $team = $this->teams->create(CreateTeamDTO::fromArray($request->validated()));

        foreach ($request->validated('course_ids', []) as $courseId) {
            $this->teams->attachCourse($team, (int) $courseId);
        }

        return ApiResponse::success(new TeamResource($team->load('courses')), 201);
    }

    public function show(Team $team): JsonResponse
    {
        $this->authorize('view', $team);

        return ApiResponse::success(new TeamResource($this->teams->find($team->id)));
    }

    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        $this->authorize('update', $team);

        return ApiResponse::success(new TeamResource($this->teams->update($team, $request->validated())));
    }

    public function destroy(Team $team): JsonResponse
    {
        $this->authorize('delete', $team);

        $this->teams->delete($team);

        return ApiResponse::noContent();
    }

    public function addStudent(AddStudentRequest $request, Team $team): JsonResponse
    {
        $this->authorize('update', $team);

        $this->teams->addStudent($team, (int) $request->validated('user_id'), $request->validated('expired_at'));

        return ApiResponse::success(['message' => 'Aluno adicionado à turma.']);
    }

    public function removeStudent(Team $team, int $user): JsonResponse
    {
        $this->authorize('update', $team);

        $this->teams->removeStudent($team, $user);

        return ApiResponse::success(['message' => 'Aluno removido da turma.']);
    }

    public function updateStudent(UpdateStudentRequest $request, Team $team, int $user): JsonResponse
    {
        $this->authorize('update', $team);

        $this->teams->updateStudent($team, $user, $request->validated());

        return ApiResponse::success(['message' => 'Vínculo do aluno atualizado.']);
    }

    public function students(Team $team): JsonResponse
    {
        $this->authorize('view', $team);

        return ApiResponse::collection(TeamStudentResource::collection($this->teams->students($team)));
    }

    public function courses(Team $team): JsonResponse
    {
        $this->authorize('view', $team);

        return ApiResponse::collection(CourseResource::collection($this->teams->courses($team)));
    }

    public function attachCourse(Request $request, Team $team): JsonResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
        ]);

        $this->teams->attachCourse($team, (int) $validated['course_id']);

        return ApiResponse::success(['message' => 'Curso associado à turma.']);
    }

    public function detachCourse(Team $team, int $course): JsonResponse
    {
        $this->authorize('update', $team);

        $this->teams->detachCourse($team, $course);

        return ApiResponse::success(['message' => 'Curso desassociado da turma.']);
    }
}
