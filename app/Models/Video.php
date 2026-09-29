<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'title', 'image', 'time', 'status', 'player', 'link', 'code', 'provider_status'])]
class Video extends Model
{
    use BelongsToCompany, HasFactory;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'time' => 'integer',
        ];
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
