<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'slug', 'name'])]
class TypeNotification extends Model
{
    use BelongsToCompany, HasFactory;

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
