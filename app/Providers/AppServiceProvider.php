<?php

namespace App\Providers;

use App\Models\User;
use App\Repositories\ActionRepository;
use App\Repositories\AttachmentRepository;
use App\Repositories\BannerRepository;
use App\Repositories\CertificateRepository;
use App\Repositories\CertificateUserRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\CompanySettingRepository;
use App\Repositories\CouponRepository;
use App\Repositories\CourseRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\GatewayRepository;
use App\Repositories\LessonRepository;
use App\Repositories\LiveClassRepository;
use App\Repositories\MenuRepository;
use App\Repositories\ModuleRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\SellerCommissionRepository;
use App\Repositories\SellerRepository;
use App\Repositories\StoreProductRepository;
use App\Repositories\StudentLessonProgressRepository;
use App\Repositories\TeamRepository;
use App\Repositories\TimelineRepository;
use App\Repositories\TopicRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserTypeRepository;
use App\Repositories\VideoRepository;
use App\Support\CompanyContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Repositories that should be resolved as singletons per request.
     *
     * @var array<int, class-string>
     */
    private array $repositories = [
        ActionRepository::class,
        AttachmentRepository::class,
        BannerRepository::class,
        CertificateRepository::class,
        CertificateUserRepository::class,
        CompanyRepository::class,
        CompanySettingRepository::class,
        CouponRepository::class,
        DocumentRepository::class,
        GatewayRepository::class,
        LessonRepository::class,
        LiveClassRepository::class,
        MenuRepository::class,
        ModuleRepository::class,
        NotificationRepository::class,
        OrderRepository::class,
        PaymentRepository::class,
        SellerCommissionRepository::class,
        SellerRepository::class,
        CourseRepository::class,
        StoreProductRepository::class,
        StudentLessonProgressRepository::class,
        TeamRepository::class,
        TimelineRepository::class,
        TopicRepository::class,
        UserRepository::class,
        UserTypeRepository::class,
        VideoRepository::class,
    ];

    public function register(): void
    {
        $this->app->singleton(CompanyContext::class);
    }

    public function boot(): void
    {
        foreach ($this->repositories as $repository) {
            $this->app->bind($repository, $repository);
        }

        Gate::define('viewReports', fn (User $user) => $user->isAdmin() || $user->isSuperAdmin());
        Gate::define('manageCompanyBranding', fn (User $user) => $user->isSuperAdmin());
        Gate::define('manageStorefront', fn (User $user) => $user->isAdmin() || $user->isSuperAdmin());

        $this->configurePasswordResetUrl();
        $this->configureRateLimiting();
    }

    /**
     * The password reset link is consumed by the frontend, not by the API,
     * so the default "password.reset" web route must not be required.
     */
    private function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $email = urlencode($notifiable->getEmailForPasswordReset());
            $company = $notifiable->company;

            if ($company?->domain && ! $notifiable->isSuperAdmin()) {
                return $company->frontendUrl()."/reset-password?token={$token}&email={$email}";
            }

            $slug = urlencode((string) ($company?->slug ?? ''));

            return rtrim((string) config('app.frontend_url'), '/')
                ."/#/reset-password?token={$token}&email={$email}&company={$slug}";
        });
    }

    /**
     * Rate limiters are intentionally documented here so limits are explicit:
     * - auth:     5/min per login+IP (brute force protection)
     * - password: 3/min per IP (password recovery)
     * - public:   60/min per IP (unauthenticated store endpoints)
     * - api:      120/min per user (authenticated API)
     * - admin:    300/min per user (administrative operations)
     * - webhooks: 600/min per gateway+IP (gateways may burst)
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            $login = $request->input('email') ?? $request->input('username');
            $key = is_string($login) && $login !== '' ? $login : (string) $request->ip();

            return Limit::perMinute(5)->by($key.'|'.$request->ip());
        });

        RateLimiter::for('password', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));

        RateLimiter::for('verification', function (Request $request) {
            $email = $request->input('email');
            $key = is_string($email) && $email !== '' ? Str::lower($email) : (string) $request->ip();

            return Limit::perMinute(5)->by($key.'|'.$request->ip());
        });

        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(300)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(600)->by($request->route('gateway').'|'.$request->ip());
        });
    }
}
