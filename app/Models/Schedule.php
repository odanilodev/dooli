<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A scheduled release date for a team course/module/lesson. The content is
 * released only when every applicable date (course, module and lesson) has
 * passed — the most restrictive one wins.
 */
#[Fillable(['company_id', 'team_id', 'schedulable_type', 'schedulable_id', 'available_at'])]
class Schedule extends Model
{
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }
}
