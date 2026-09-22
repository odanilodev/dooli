<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'company_id', 'status'])]
class Action extends Model
{
    use BelongsToCompany, HasFactory;

    public function timeline(): HasMany
    {
        return $this->hasMany(Timeline::class);
    }
}
