<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'name', 'code', 'status', 'credentials', 'settings'])]
#[Hidden(['credentials', 'settings'])]
class VideoPlatform extends Model
{
    use BelongsToCompany, HasFactory;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
        ];
    }

    /**
     * Returns a credential value without ever exposing the whole bag.
     */
    public function credential(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }
}
