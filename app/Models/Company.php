<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'domain', 'status'])]
class Company extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Company $company) {
            if (filled($company->slug)) {
                $company->slug = Str::slug((string) $company->slug);

                return;
            }

            $base = Str::slug((string) $company->name) ?: 'empresa';
            $slug = $base;
            $suffix = 2;

            while (Company::query()->where('slug', $slug)->whereKeyNot($company->getKey())->exists()) {
                $slug = $base.'-'.$suffix;
                $suffix++;
            }

            $company->slug = $slug;
        });
    }

    public function frontendUrl(): string
    {
        $domain = self::normalizeDomain($this->domain);

        if ($domain) {
            return rtrim((string) config('app.frontend_scheme', 'https'), '/')
                .'://'.$domain;
        }

        return rtrim((string) config('app.frontend_url'), '/');
    }

    /**
     * Resolve a frontend path using the company domain when available, or the
     * legacy slug-based hash route as a fallback.
     */
    public function frontendPath(string $path = ''): string
    {
        $path = ltrim($path, '/');

        if (filled($this->domain)) {
            return $this->frontendUrl().($path === '' ? '/' : '/'.$path);
        }

        return $this->frontendUrl()
            .'/#/e/'.rawurlencode((string) $this->slug)
            .($path === '' ? '' : '/'.$path);
    }

    public function publicStoreUrl(): string
    {
        if (filled($this->domain)) {
            return $this->frontendUrl().'/';
        }

        return $this->frontendPath('loja');
    }

    public function publicOrderUrl(int $orderId): string
    {
        return $this->frontendPath('pedido/'.$orderId);
    }

    public function publicSubscriptionsUrl(): string
    {
        if (filled($this->domain)) {
            return $this->frontendUrl().'/minhas-assinaturas';
        }

        return $this->frontendPath('assinaturas');
    }

    public static function normalizeDomain(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $domain = (string) preg_replace('#^https?://#i', '', trim($value));
        $domain = (string) preg_replace('#[/?].*$#', '', $domain);
        $domain = (string) preg_replace('#:\d+$#', '', $domain);
        $domain = strtolower(trim($domain));
        $domain = (string) preg_replace('#^www\.#i', '', $domain);
        $domain = rtrim($domain, '.');

        return $domain === '' ? null : $domain;
    }

    protected function domain(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => self::normalizeDomain($value),
        );
    }

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
