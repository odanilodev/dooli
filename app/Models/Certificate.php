<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'grade', 'title', 'course_id', 'version', 'time'])]
class Certificate extends Model
{
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'grade' => 'decimal:2',
            'time' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function certificateUsers(): HasMany
    {
        return $this->hasMany(CertificateUser::class);
    }
}
