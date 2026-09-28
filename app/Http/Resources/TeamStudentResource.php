<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * A student enrolled in a team (turma). Exposes the pivot (team_users) so the
 * admin can see and manage the enrollment status and expiration per team,
 * without touching the user's own account.
 */
class TeamStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'image' => $this->image,
            'status' => $this->whenPivotLoaded('team_users', fn () => $this->pivot->status),
            'expired_at' => $this->whenPivotLoaded('team_users', fn () => $this->pivot->expired_at
                ? Carbon::parse($this->pivot->expired_at)->toIso8601String()
                : null),
            'joined_at' => $this->whenPivotLoaded('team_users', fn () => $this->pivot->created_at
                ? Carbon::parse($this->pivot->created_at)->toIso8601String()
                : null),
        ];
    }
}
