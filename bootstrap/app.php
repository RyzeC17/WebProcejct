<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        $middleware->alias([
            'staff' => \App\Http\Middleware\StaffMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return \App\Services\ApiResponse::json(
                    'Controlla i dati inviati.',
                    false,
                    [],
                    $exception->errors(),
                    400,
                );
            }

            if ($exception instanceof AuthenticationException) {
                return \App\Services\ApiResponse::json('Autenticazione richiesta.', false, [], [], 401);
            }

            if ($exception instanceof AuthorizationException) {
                return \App\Services\ApiResponse::json($exception->getMessage() ?: 'Permessi insufficienti.', false, [], [], 403);
            }

            if ($exception instanceof TokenMismatchException) {
                return \App\Services\ApiResponse::json('Token CSRF non valido o sessione scaduta.', false, [], [], 419);
            }

            if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
                return \App\Services\ApiResponse::json('Risorsa non trovata.', false, [], [], 404);
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $message = match ($status) {
                    401 => 'Autenticazione richiesta.',
                    403 => 'Permessi insufficienti.',
                    404 => 'Risorsa non trovata.',
                    419 => 'Token CSRF non valido o sessione scaduta.',
                    default => $status >= 500 ? 'Si e verificato un errore interno.' : ($exception->getMessage() ?: 'Richiesta non valida.'),
                };

                return \App\Services\ApiResponse::json($message, false, [], [], $status);
            }

            return \App\Services\ApiResponse::json('Si e verificato un errore interno.', false, [], [], 500);
        });
    })->create();
