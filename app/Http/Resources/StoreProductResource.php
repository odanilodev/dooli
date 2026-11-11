<?php

namespace App\Http\Resources;

use App\Enums\BillingType;
use App\Enums\GeneralStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends JsonResource
{
    /**
     * Quando verdadeiro, included_courses lista só o que o aluno de fato recebe
     * (turma, vínculo e curso ativos), espelhando o EnrollmentService::grantAccess.
     */
    protected bool $activeOnly = false;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
            'image_url' => $this->imageUrl(),
            'slug' => $this->slug,
            'tags' => $this->tags,
            'price' => $this->price,
            'sale' => $this->sale,
            'status' => $this->status,
            'release_at' => $this->release_at?->toIso8601String(),
            'billing_type' => $this->billing_type,
            'billing_interval' => $this->billing_interval,
            'access_duration_days' => $this->access_duration_days,
            'grace_days' => $this->grace_days,
            'drip_enabled' => (bool) $this->drip_enabled,
            'drip_modules' => $this->drip_enabled
                ? app(\App\Services\DripReleaseService::class)->config($this->resource)
                : [],
            'is_subscription' => $this->billing_type === BillingType::Subscription->value,
            'access_label' => $this->accessLabel(),
            'course' => new CourseResource($this->whenLoaded('course')),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'included_courses' => $this->includedCourses(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function includedCourses(): mixed
    {
        if (! $this->relationLoaded('teams')) {
            return null;
        }

        $teams = $this->activeOnly
            ? $this->teams->filter(fn ($team) => $team->status === GeneralStatus::Active->value
                && $team->pivot?->status === GeneralStatus::Active->value)
            : $this->teams;

        $courses = $teams
            ->flatMap(function ($team) {
                if (! $team->relationLoaded('courses')) {
                    return collect();
                }

                return $this->activeOnly
                    ? $team->courses->filter(fn ($course) => $course->status === GeneralStatus::Active->value
                        && $course->pivot?->status === GeneralStatus::Active->value)
                    : $team->courses;
            })
            ->unique('id')
            ->values();

        return CourseResource::collection($courses);
    }

    private function accessLabel(): string
    {
        if ($this->billing_type === BillingType::Subscription->value) {
            return 'Assinatura mensal';
        }

        if ($this->access_duration_days === null) {
            return 'Acesso permanente';
        }

        return $this->access_duration_days === 1
            ? 'Acesso por 1 dia'
            : "Acesso por {$this->access_duration_days} dias";
    }
}
