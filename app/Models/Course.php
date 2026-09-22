<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'description', 'image', 'status', 'sort_order'])]
class Course extends Model
{
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'course_modules')
            ->withPivot(['status', 'sort_order'])
            ->withTimestamps();
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function storeProducts(): HasMany
    {
        return $this->hasMany(StoreProduct::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_courses')
            ->withPivot(['status'])
            ->withTimestamps();
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(Timeline::class);
    }
}
