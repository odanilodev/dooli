<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasImageUrl;
use App\Enums\UserTypeCode;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'username', 'email', 'email_verified_at', 'password', 'status', 'registration_source',
    'user_type_id', 'company_id', 'image', 'phone', 'address', 'city',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToCompany, HasApiTokens, HasFactory, HasImageUrl, Notifiable;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            $user->teamLinks()->delete();
            $user->timeline()->delete();
            $user->videoProgresses()->delete();
            $user->studentProgress()->delete();
        });
    }

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_users')
            ->withPivot(['status', 'expired_at'])
            ->withTimestamps();
    }

    public function teamLinks(): HasMany
    {
        return $this->hasMany(TeamUser::class);
    }

    public function emailVerificationCode(): HasOne
    {
        return $this->hasOne(EmailVerificationCode::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function certificateUsers(): HasMany
    {
        return $this->hasMany(CertificateUser::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(Timeline::class);
    }

    public function videoProgresses(): HasMany
    {
        return $this->hasMany(VideoProgress::class);
    }

    public function studentProgress(): HasMany
    {
        return $this->hasMany(StudentLessonProgress::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function notifications(): BelongsToMany
    {
        return $this->belongsToMany(Notification::class, 'notification_users')
            ->withPivot(['read_at'])
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->userType?->code === UserTypeCode::Admin->value;
    }

    /**
     * Platform-level administrator. Kept out of the fillable list on purpose so
     * the flag can only be granted by hardened flows (seeder / company bootstrap),
     * never through the regular user CRUD mass-assignment.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->userType?->code, $roles, true);
    }
}
