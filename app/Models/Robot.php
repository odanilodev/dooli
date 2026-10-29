<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'slug', 'name', 'description', 'market', 'icon', 'status', 'sort_order'])]
class Robot extends Model
{
    use BelongsToCompany;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
