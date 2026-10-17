<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'code', 'company_id', 'user_id', 'team_id', 'lessons_count', 'modules',
    'certificate_id', 'invalidated_at', 'invalidation_reason',
])]
class CertificateUser extends Model
{
    use BelongsToCompany, HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'lessons_count' => 'integer',
            'modules' => 'array',
            'created_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // Keep the partial unique guarantee: only valid certificates carry
            // active_flag = 1, so invalidated history is never blocked.
            $model->active_flag = $model->invalidated_at === null ? 1 : null;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function isInvalidated(): bool
    {
        return $this->invalidated_at !== null;
    }
}
