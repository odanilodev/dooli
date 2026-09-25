<?php

use App\Http\Controllers\Api\V1\Admin\AttachmentController as AdminAttachmentController;
use App\Http\Controllers\Api\V1\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Api\V1\Admin\CertificateController as AdminCertificateController;
use App\Http\Controllers\Api\V1\Admin\CommentController as AdminCommentController;
use App\Http\Controllers\Api\V1\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\V1\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\V1\Admin\DatalogController as AdminDatalogController;
use App\Http\Controllers\Api\V1\Admin\DocumentController as AdminDocumentController;
use App\Http\Controllers\Api\V1\Admin\GatewayController as AdminGatewayController;
use App\Http\Controllers\Api\V1\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Api\V1\Admin\ModuleController as AdminModuleController;
use App\Http\Controllers\Api\V1\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\RatingController as AdminRatingController;
use App\Http\Controllers\Api\V1\Admin\StoreProductController as AdminStoreProductController;
use App\Http\Controllers\Api\V1\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Api\V1\Admin\TopicController as AdminTopicController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Admin\UserTypeController as AdminUserTypeController;
use App\Http\Controllers\Api\V1\Admin\VideoController as AdminVideoController;
use App\Http\Controllers\Api\V1\Admin\VideoPlatformController as AdminVideoPlatformController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\Public\CertificateVerifyController;
use App\Http\Controllers\Api\V1\Public\StoreController;
use App\Http\Controllers\Api\V1\Student\CertificateController as StudentCertificateController;
use App\Http\Controllers\Api\V1\Student\CommentController;
use App\Http\Controllers\Api\V1\Student\MaterialController;
use App\Http\Controllers\Api\V1\Student\MyCourseController;
use App\Http\Controllers\Api\V1\Student\NotificationController as StudentNotificationController;
use App\Http\Controllers\Api\V1\Student\OrderController as StudentOrderController;
use App\Http\Controllers\Api\V1\Student\ProgressController;
use App\Http\Controllers\Api\V1\Student\RatingController;
use App\Http\Controllers\Api\V1\Student\VideoController as StudentVideoController;
use App\Http\Controllers\Api\V1\Webhook\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes (tenant via X-Company header)
|--------------------------------------------------------------------------
*/
Route::middleware(['company', 'company.required', 'throttle:public'])->group(function () {
    Route::get('store/products', [StoreController::class, 'index']);
    Route::get('store/products/{slug}', [StoreController::class, 'show']);
    Route::get('certificates/{code}/verify', [CertificateVerifyController::class, 'verify']);
});

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::post('auth/login', [AuthController::class, 'login'])->middleware(['company', 'throttle:auth']);

Route::post('auth/forgot-password', [PasswordResetController::class, 'sendLink'])
    ->middleware(['company', 'throttle:password']);

Route::post('auth/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware(['company', 'throttle:password']);

/*
|--------------------------------------------------------------------------
| Webhooks (tenant via X-Company header, high volume)
|--------------------------------------------------------------------------
*/
Route::post('webhooks/payments/{gateway}', [PaymentWebhookController::class, 'handle'])
    ->middleware(['company', 'company.required', 'throttle:webhooks']);

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active', 'company', 'throttle:api'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);

    /*
    | Own profile
    */
    Route::get('me/profile', [ProfileController::class, 'show']);
    Route::put('me/profile', [ProfileController::class, 'update']);
    Route::put('me/password', [ProfileController::class, 'updatePassword']);

    /*
    | Student area
    */
    Route::get('me/courses', [MyCourseController::class, 'index']);
    Route::get('me/courses/{course}', [MyCourseController::class, 'show']);
    Route::get('me/courses/{course}/progress', [MyCourseController::class, 'progress']);
    Route::get('me/courses/{course}/timeline', [MyCourseController::class, 'timeline']);

    Route::get('me/continue-watching', [MyCourseController::class, 'continueWatching']);

    Route::get('me/courses/{course}/documents/{document}', [MaterialController::class, 'document']);
    Route::get('me/courses/{course}/attachments/{attachment}', [MaterialController::class, 'attachment']);

    Route::get('me/courses/{course}/lessons/{lesson}/video', [StudentVideoController::class, 'playback']);

    Route::post('me/progress/complete', [ProgressController::class, 'completeLesson']);
    Route::post('me/progress/video-position', [ProgressController::class, 'saveVideoPosition']);

    Route::get('me/certificates', [StudentCertificateController::class, 'index']);
    Route::post('me/certificates/generate', [StudentCertificateController::class, 'generate']);

    Route::get('me/notifications', [StudentNotificationController::class, 'index']);
    Route::post('me/notifications/read-all', [StudentNotificationController::class, 'markAllAsRead']);
    Route::post('me/notifications/{notification}/read', [StudentNotificationController::class, 'markAsRead']);

    Route::get('me/orders', [StudentOrderController::class, 'index']);
    Route::post('me/orders', [StudentOrderController::class, 'store']);
    Route::get('me/orders/{order}', [StudentOrderController::class, 'show']);
    Route::post('me/orders/{order}/cancel', [StudentOrderController::class, 'cancel']);

    Route::get('comments', [CommentController::class, 'index']);
    Route::post('comments', [CommentController::class, 'store']);
    Route::get('ratings', [RatingController::class, 'index']);
    Route::post('ratings', [RatingController::class, 'store']);

    /*
    | Administrative area
    */
    Route::middleware(['role:admin', 'throttle:admin'])->prefix('admin')->group(function () {
        Route::apiResource('courses', AdminCourseController::class);
        Route::post('courses/{course}/publish', [AdminCourseController::class, 'publish']);
        Route::post('courses/{course}/unpublish', [AdminCourseController::class, 'unpublish']);
        Route::post('courses/{course}/modules', [AdminCourseController::class, 'attachModule']);
        Route::delete('courses/{course}/modules/{module}', [AdminCourseController::class, 'detachModule'])->whereNumber('module');
        Route::put('courses/{course}/modules/reorder', [AdminCourseController::class, 'reorderModules']);

        Route::apiResource('modules', AdminModuleController::class);
        Route::put('modules/{module}/topics/reorder', [AdminTopicController::class, 'reorder']);

        Route::apiResource('topics', AdminTopicController::class);
        Route::put('topics/{topic}/lessons/reorder', [AdminLessonController::class, 'reorder']);

        Route::apiResource('lessons', AdminLessonController::class);
        Route::post('lessons/{lesson}/publish', [AdminLessonController::class, 'publish']);
        Route::post('lessons/{lesson}/unpublish', [AdminLessonController::class, 'unpublish']);

        Route::apiResource('teams', AdminTeamController::class);
        Route::get('teams/{team}/students', [AdminTeamController::class, 'students']);
        Route::post('teams/{team}/students', [AdminTeamController::class, 'addStudent']);
        Route::delete('teams/{team}/students/{user}', [AdminTeamController::class, 'removeStudent'])->whereNumber('user');
        Route::get('teams/{team}/courses', [AdminTeamController::class, 'courses']);
        Route::post('teams/{team}/courses', [AdminTeamController::class, 'attachCourse']);
        Route::delete('teams/{team}/courses/{course}', [AdminTeamController::class, 'detachCourse'])->whereNumber('course');

        Route::apiResource('users', AdminUserController::class);
        Route::apiResource('user-types', AdminUserTypeController::class);

        Route::apiResource('coupons', AdminCouponController::class);
        Route::apiResource('store-products', AdminStoreProductController::class);

        Route::apiResource('payments', AdminPaymentController::class)->only(['index', 'store', 'show']);
        Route::post('payments/{payment}/refund', [AdminPaymentController::class, 'refund']);

        Route::apiResource('orders', AdminOrderController::class)->only(['index', 'show']);

        Route::apiResource('gateways', AdminGatewayController::class);
        Route::apiResource('video-platforms', AdminVideoPlatformController::class)->parameters(['video-platforms' => 'videoPlatform']);
        Route::apiResource('videos', AdminVideoController::class);
        Route::get('videos/{video}/playback', [AdminVideoController::class, 'playback']);
        Route::apiResource('documents', AdminDocumentController::class);
        Route::apiResource('attachments', AdminAttachmentController::class);
        Route::apiResource('banners', AdminBannerController::class);
        Route::apiResource('certificates', AdminCertificateController::class);
        Route::get('certificate-users', [AdminCertificateController::class, 'issued']);
        Route::post('certificate-users/{certificateUser}/revoke', [AdminCertificateController::class, 'revoke']);

        Route::apiResource('comments', AdminCommentController::class)->only(['index', 'destroy']);
        Route::apiResource('ratings', AdminRatingController::class)->only(['index', 'destroy']);
        Route::get('datalogs', [AdminDatalogController::class, 'index']);
        Route::apiResource('notifications', AdminNotificationController::class)->only(['index', 'show']);
    });
});
