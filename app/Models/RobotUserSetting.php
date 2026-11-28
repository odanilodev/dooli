<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'robot_id', 'user_id', 'settings'])]
class RobotUserSetting extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
