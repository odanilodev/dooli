<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['company_id', 'path', 'status'])]
class Attachment extends Model
{
    use BelongsToCompany, HasFactory;

    protected $attributes = ['status' => 'active'];

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_attachments', 'attachment_id', 'lesson_id')
            ->withPivot(['status', 'sort_order'])
            ->withTimestamps();
    }
}
