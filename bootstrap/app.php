<?php

use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureCompanyContext;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetCompanyContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API-only app: never attempt to redirect guests to a web "login" route.
        // Unauthenticated requests must fall through to the JSON 401 handler.
        $middleware->redirectGuestsTo(null);

        $middleware->alias([
            'company' => SetCompanyContext::class,
            'company.required' => EnsureCompanyContext::class,
            'role' => EnsureUserHasRole::class,
            'admin.access' => EnsureAdminAccess::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // Resolve the tenant BEFORE route-model binding so the company global
        // scope also protects implicitly bound models (auth -> company -> bind).
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            SetCompanyContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Os dados enviados são inválidos.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não autenticado.',
                    'code' => 'unauthenticated',
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não possui permissão para realizar esta ação.',
                    'code' => 'forbidden',
                ], 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recurso não encontrado.',
                    'code' => 'resource_not_found',
                ], 404);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Muitas requisições. Tente novamente em instantes.',
                    'code' => 'too_many_requests',
                ], 429);
            }
        });

        // Translate database integrity errors (duplicate unique keys and
        // foreign-key violations) into clean 409 responses instead of 500s.
        $exceptions->render(function (QueryException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $message = $e->getMessage();
            $driverCode = $e->errorInfo[1] ?? null;

            if ($driverCode === 1062 || str_contains($message, 'UNIQUE constraint failed')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Já existe um registro com estes dados.',
                    'code' => 'duplicate_resource',
                ], 409);
            }

            if (in_array($driverCode, [1451, 1452], true) || str_contains($message, 'FOREIGN KEY constraint failed')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível concluir: o registro possui vínculos.',
                    'code' => 'resource_conflict',
                ], 409);
            }

            return null;
        });
    })->create();
