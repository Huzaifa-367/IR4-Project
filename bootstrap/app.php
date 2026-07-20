<?php

use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            require __DIR__.'/../routes/storage.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'auth.device' => AuthenticateDevice::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'VALIDATION_FAILED',
                $e->getMessage(),
                $e->errors(),
                422,
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error('UNAUTHENTICATED', $e->getMessage() ?: 'Unauthenticated.', status: 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error('FORBIDDEN', $e->getMessage() ?: 'Forbidden.', status: 403);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error('NOT_FOUND', 'Resource not found.', status: 404);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();
            $message = $e->getMessage() ?: 'Request failed.';

            if ($request->is('api/*') || $request->expectsJson()) {
                $code = match ($status) {
                    409 => 'CONFLICT',
                    429 => 'RATE_LIMITED',
                    403 => 'FORBIDDEN',
                    401 => 'UNAUTHENTICATED',
                    404 => 'NOT_FOUND',
                    default => 'HTTP_ERROR',
                };

                return ApiResponse::error($code, $message, status: $status);
            }

            // Domain guards (409/422) on operator UI: toast + stay on page.
            // Leave 401/403/404/5xx to Laravel's normal error pages.
            if (in_array($status, [409, 422], true) && $request->hasSession()) {
                Inertia::flash('toast', [
                    'type' => $status === 409 ? 'warning' : 'error',
                    'message' => $message,
                ]);

                return Inertia::back();
            }

            return null;
        });
    })->create();
