<?php

namespace App\Http\Resources;

use App\Enums\BillingType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends JsonResource
{
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

        $courses = $this->teams
            ->flatMap(fn ($team) => $team->relationLoaded('courses') ? $team->courses : collect())
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
