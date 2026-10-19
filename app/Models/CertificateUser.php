<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Support\CertificatePublicCode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'code', 'public_code', 'company_id', 'user_id', 'team_id', 'lessons_count', 'modules',
    'certificate_id', 'invalidated_at', 'invalidation_reason',
])]
class CertificateUser extends Model
{
    use BelongsToCompany, HasFactory;

    public const UPDATED_AT = null;

    public const STATUS_VALID = 'valid';

    public const STATUS_REPLACED = 'replaced';

    public const STATUS_REVOKED = 'revoked';

    public const INVALIDATION_REPLACED = 'Substituído automaticamente por uma nova conclusão do curso.';

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
        static::creating(function (self $model) {
            if ($model->public_code) {
                return;
            }

            do {
                $code = CertificatePublicCode::generate();
            } while (
                static::query()
                    ->withoutGlobalScope('company')
                    ->where('public_code', $code)
                    ->exists()
            );

            $model->public_code = $code;
        });

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

    public function isReplaced(): bool
    {
        return $this->invalidation_reason === self::INVALIDATION_REPLACED;
    }

    public function status(): string
    {
        if (! $this->isInvalidated()) {
            return self::STATUS_VALID;
        }

        return $this->isReplaced() ? self::STATUS_REPLACED : self::STATUS_REVOKED;
    }
}
