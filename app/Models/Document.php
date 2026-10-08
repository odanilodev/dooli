<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasImageUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'path', 'url', 'image', 'status'])]
class Document extends Model
{
    use BelongsToCompany, HasFactory, HasImageUrl;

    protected $attributes = ['status' => 'active'];

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
