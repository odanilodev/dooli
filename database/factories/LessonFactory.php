<?php

namespace Database\Factories;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'topic_id' => Topic::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'status' => GeneralStatus::Active->value,
            'sort_order' => 0,
        ];
    }

    /**
     * A lesson attached directly to a module, without a topic.
     */
    public function withoutTopic(): static
    {
        return $this->state(fn () => [
            'topic_id' => null,
            'module_id' => Module::factory(),
        ]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Lesson $lesson) {
            if ($lesson->module_id === null && $lesson->topic_id !== null) {
                $lesson->module_id = Topic::query()->whereKey($lesson->topic_id)->value('module_id');
            }
        });
    }
}
