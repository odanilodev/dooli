<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasImageUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'company_id', 'image', 'description', 'status'])]
class Module extends Model
{
    use BelongsToCompany, HasFactory, HasImageUrl;

    protected $attributes = ['status' => 'active'];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_modules')
            ->withPivot(['status', 'sort_order'])
            ->withTimestamps();
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('sort_order');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    public function looseLessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->whereNull('topic_id')->orderBy('sort_order');
    }

    public function schedules(): MorphMany
    {
        return $this->morphMany(Schedule::class, 'schedulable');
    }

    public function studentProgress(): HasMany
    {
        return $this->hasMany(StudentLessonProgress::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Module $module) {
            $module->schedules()->delete();
            $module->studentProgress()->delete();
        });
    }
}
