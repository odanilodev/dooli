<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\RevokeCertificateRequest;
use App\Http\Requests\Admin\StoreCertificateRequest;
use App\Http\Requests\Admin\UpdateCertificateRequest;
use App\Http\Resources\CertificateResource;
use App\Http\Resources\CertificateUserResource;
use App\Models\Certificate;
use App\Models\CertificateUser;
use App\Repositories\CertificateRepository;
use App\Repositories\CertificateUserRepository;
use App\Services\CertificateService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateController extends ApiController
{
    public function __construct(
        private readonly CertificateRepository $certificates,
        private readonly CertificateUserRepository $issued,
        private readonly CertificateService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Certificate::class);

        return ApiResponse::paginated(
            $this->certificates->paginate($this->perPage($request), $request->only(['search', 'course_id']), ['course']),
            CertificateResource::class,
        );
    }

    public function store(StoreCertificateRequest $request): JsonResponse
    {
        $this->authorize('create', Certificate::class);

        $data = $request->validated();
        $removeBackground = $request->boolean('remove_background');
        $removeSignature = $request->boolean('remove_signature');
        $removeModulesBackground = $request->boolean('remove_modules_background');
        unset(
            $data['background'],
            $data['signature'],
            $data['modules_background'],
            $data['remove_background'],
            $data['remove_signature'],
            $data['remove_modules_background'],
        );

        $certificate = $this->service->createTemplate(
            $data,
            $request->file('background'),
            $request->file('signature'),
            $request->file('modules_background'),
            $removeModulesBackground,
            $removeBackground,
            $removeSignature,
        );

        $this->service->issuePendingForCourse((int) $certificate->course_id, (int) $certificate->company_id);

        return ApiResponse::success(new CertificateResource($certificate->load('course')), 201);
    }

    public function show(Certificate $certificate): JsonResponse
    {
        $this->authorize('view', $certificate);

        return ApiResponse::success(new CertificateResource($certificate->load('course')));
    }

    public function update(UpdateCertificateRequest $request, Certificate $certificate): JsonResponse
    {
        $this->authorize('update', $certificate);

        $data = $request->validated();
        $removeBackground = $request->boolean('remove_background');
        $removeSignature = $request->boolean('remove_signature');
        $removeModulesBackground = $request->boolean('remove_modules_background');
        unset(
            $data['background'],
            $data['signature'],
            $data['modules_background'],
            $data['remove_background'],
            $data['remove_signature'],
            $data['remove_modules_background'],
        );

        $certificate = $this->service->updateTemplate(
            $certificate,
            $data,
            $request->file('background'),
            $request->file('signature'),
            $request->file('modules_background'),
            $removeModulesBackground,
            $removeBackground,
            $removeSignature,
        );

        $this->service->issuePendingForCourse((int) $certificate->course_id, (int) $certificate->company_id);

        return ApiResponse::success(new CertificateResource($certificate->load('course')));
    }

    public function destroy(Certificate $certificate): JsonResponse
    {
        $this->authorize('delete', $certificate);

        $this->service->deleteTemplate($certificate);

        return ApiResponse::noContent();
    }

    /**
     * Lists issued certificates (certificate_users).
     */
    public function issued(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CertificateUser::class);

        $paginator = $this->issued->paginate(
            perPage: $this->perPage($request),
            filters: $request->only(['user_id', 'team_id', 'certificate_id', 'course_id']),
            with: ['certificate.course', 'user', 'team'],
        );

        return ApiResponse::paginated($paginator, CertificateUserResource::class);
    }

    /**
     * Invalidates an issued certificate.
     */
    public function revoke(RevokeCertificateRequest $request, CertificateUser $certificateUser): JsonResponse
    {
        $this->authorize('update', $certificateUser);

        $revoked = $this->service->revoke($certificateUser, $request->validated('reason'));

        return ApiResponse::success(new CertificateUserResource($revoked));
    }
}
