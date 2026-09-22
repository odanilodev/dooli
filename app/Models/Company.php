<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'status'])]
class Company extends Model
{
    use HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function userTypes(): HasMany
    {
        return $this->hasMany(UserType::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    public function gateways(): HasMany
    {
        return $this->hasMany(Gateway::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(CompanySetting::class);
    }
}
