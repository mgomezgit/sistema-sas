<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'sesion.activa' => \App\Http\Middleware\VerificarSesion::class,
            'restringir.empleado' => \App\Http\Middleware\RestringirEmpleado::class,
        ]);

        /*
         * La solicitud de cita de la página pública queda fuera de la
         * verificación CSRF.
         *
         * No es una relajación de seguridad: el token CSRF protege a alguien
         * que YA tiene sesión de que su navegador ejecute una acción a su
         * nombre sin que se entere. Aquí no hay sesión ni identidad a la que
         * suplantar — el endpoint hace exactamente lo mismo para cualquiera,
         * y un bot que quisiera saltárselo solo tendría que pedir la página
         * primero y leer el token, así que tampoco frena nada.
         *
         * Lo que sí protege este endpoint es el throttle del grupo y el campo
         * trampa del formulario. Y dejarlo sin token permite además que la
         * página pública se pueda cachear sin que un token caducado rompa el
         * formulario de quien la abra.
         *
         * Va acotado a esta ruta: el resto de la aplicación, backoffice
         * incluido, sigue con CSRF como siempre.
         */
        $middleware->validateCsrfTokens(except: [
            'publico/*/agendar',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
