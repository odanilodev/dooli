<?php

namespace App\Enums;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;

enum ScheduleLevel: string
{
    case Course = 'course';
    case Module = 'module';
    case Lesson = 'lesson';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function modelClass(): string
    {
        return match ($this) {
            self::Course => Course::class,
            self::Module => Module::class,
            self::Lesson => Lesson::class,
        };
    }
}
