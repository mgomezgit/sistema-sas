<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Altas del registro público permitidas por minuto desde una misma IP.
     * Cuenta TODA petición (buena, con error de validación o caída en la
     * trampa), igual que el throttle de ruta de publico/*.
     */
    const REGISTROS_POR_MINUTO = 5;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Throttle de ruta del registro público, mismo patrón que el de
         * publico/*, pero con nombre para poder responder en el formato de
         * siempre: el throttle genérico devuelve un 429 en inglés, y
         * axiosSipleInterno solo alcanzaría a mostrar "problema técnico
         * HTTP-429". Se responde 200 con error = 1, igual que VerificarSesion
         * cuando rechaza, para que el formulario muestre el motivo real.
         */
        RateLimiter::for('registro-publico', function (Request $request) {
            return Limit::perMinute(self::REGISTROS_POR_MINUTO)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 1,
                        'mensaje' => 'Se hicieron demasiados intentos de registro desde esta conexión. Espera un minuto e inténtalo de nuevo.',
                        'data' => [],
                    ]);
                });
        });
    }
}
