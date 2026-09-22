<?php

namespace App\Providers;

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
use App\Repositories\MenuRepository;
use App\Repositories\ModuleRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\StoreProductRepository;
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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        MenuRepository::class,
        ModuleRepository::class,
        NotificationRepository::class,
        OrderRepository::class,
        PaymentRepository::class,
        CourseRepository::class,
        StoreProductRepository::class,
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

            return rtrim((string) config('app.frontend_url'), '/')
                ."/reset-password?token={$token}&email={$email}";
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
