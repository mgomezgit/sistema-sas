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

    /** Pedidos de código de recuperación de clave por minuto desde una IP. */
    const SOLICITUDES_RECUPERACION_POR_MINUTO = 5;

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

        // Mismo patrón para pedir códigos de recuperación de clave: frena el
        // envío masivo de correos a terceros desde una misma conexión.
        RateLimiter::for('recuperacion-solicitar', function (Request $request) {
            return Limit::perMinute(self::SOLICITUDES_RECUPERACION_POR_MINUTO)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 1,
                        'mensaje' => 'Se pidieron demasiados códigos desde esta conexión. Espera un minuto e inténtalo de nuevo.',
                        'data' => [],
                    ]);
                });
        });

        /*
         * Mensajes al chatbot de la página pública, por IP: un límite por
         * minuto y otro por hora (config/chatbot.php). Cada mensaje es una
         * llamada de pago al proveedor de IA, así que este límite protege
         * el saldo además del límite mensual del negocio.
         *
         * A diferencia de los de arriba, responde con un 429 real, pero en el
         * formato de siempre: el widget del chatbot lo distingue por el
         * código y muestra el mensaje.
         */
        RateLimiter::for('chatbot-mensaje', function (Request $request) {
            $respuesta = function () {
                return response()->json([
                    'error' => 1,
                    'mensaje' => 'Enviaste muchos mensajes seguidos. Espera un momento e inténtalo de nuevo.',
                    'data' => [],
                ], 429);
            };

            return [
                Limit::perMinute((int) config('chatbot.mensajes_por_minuto_ip'))
                    ->by('minuto:'.$request->ip())
                    ->response($respuesta),
                Limit::perHour((int) config('chatbot.mensajes_por_hora_ip'))
                    ->by('hora:'.$request->ip())
                    ->response($respuesta),
            ];
        });

        /*
         * "Terminar conversación" del chatbot, por IP. No cuesta una llamada
         * de pago, pero es público y escribe en la caché. Tiene su propia
         * clave ('terminar:') para no gastar el cupo de mensajes de la misma
         * IP. Mismo formato de error 429 que el limitador de mensajes.
         */
        RateLimiter::for('chatbot-terminar', function (Request $request) {
            return Limit::perMinute((int) config('chatbot.terminar_por_minuto_ip'))
                ->by('terminar:'.$request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 1,
                        'mensaje' => 'Hiciste muchas peticiones seguidas. Espera un momento e inténtalo de nuevo.',
                        'data' => [],
                    ], 429);
                });
        });
    }
}
