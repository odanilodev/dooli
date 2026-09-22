<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever course content changes in a way that may impact
 * existing course completions (section 27).
 */
class CourseContentChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, int>  $courseIds
     */
    public function __construct(
        public array $courseIds,
        public string $reason = 'content_changed',
    ) {}
}
