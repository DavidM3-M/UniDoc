<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    // Configura la aplicación Laravel, estableciendo la ruta base del proyecto.

    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/publico.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->alias([

            'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
            // Registra el middleware de autenticación con el alias `auth`.
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            // Registra el middleware de roles de Spatie con el alias `role`.
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 401);
                // Retorna una respuesta JSON con un mensaje de error y un código de estado 401.
            }
        });
    })->create();
// Crea y retorna la instancia de la aplicación configurada.
