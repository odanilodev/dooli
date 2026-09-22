<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'status', 'sort_order'])]
class Team extends Model
{
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'team_courses')
            ->withPivot(['status'])
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_users')
            ->withPivot(['status', 'expired_at'])
            ->withTimestamps();
    }

    public function teamUsers(): HasMany
    {
        return $this->hasMany(TeamUser::class);
    }

    public function storeProducts(): BelongsToMany
    {
        return $this->belongsToMany(StoreProduct::class, 'store_product_teams')
            ->withPivot(['status'])
            ->withTimestamps();
    }

    public function certificateUsers(): HasMany
    {
        return $this->hasMany(CertificateUser::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
