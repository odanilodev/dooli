<?php

namespace App\Listeners;

use App\Events\CourseContentChanged;
use App\Services\CourseCompletionService;

class HandleCourseContentChange
{
    public function __construct(private readonly CourseCompletionService $completionService) {}

    public function handle(CourseContentChanged $event): void
    {
        $this->completionService->handleContentChange($event->courseIds);
    }
}
