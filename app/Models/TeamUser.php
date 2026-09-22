<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\GeneralStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'team_id', 'user_id', 'status', 'expired_at'])]
class TeamUser extends Model
{
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'expired_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === GeneralStatus::Active->value
            && ($this->expired_at === null || $this->expired_at->isFuture());
    }
}
