<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasImageUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['company_id', 'name', 'description', 'link', 'starts_at', 'duration', 'image', 'status'])]
class LiveClass extends Model
{
    use BelongsToCompany, HasFactory, HasImageUrl;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'duration' => 'integer',
        ];
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_live_classes')
            ->withPivot(['status'])
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::deleting(fn (LiveClass $liveClass) => $liveClass->teams()->detach());
    }
}
