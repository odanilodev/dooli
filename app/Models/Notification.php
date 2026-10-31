<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'company_id', 'team_id', 'release_at', 'type_notification_id', 'status',
    'title', 'message', 'action_url', 'category', 'severity', 'dedupe_key', 'metadata',
])]
class Notification extends Model
{
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'release_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeNotification::class, 'type_notification_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notification_users')
            ->withPivot(['read_at'])
            ->withTimestamps();
    }
}
