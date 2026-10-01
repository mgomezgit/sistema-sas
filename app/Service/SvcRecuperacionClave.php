<?php

namespace App\Service;

use App\Models\CodigoRecuperacionClave;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Recuperación de clave con un código de 6 dígitos enviado al correo.
 *
 * Tabla de plataforma: un código pertenece a un usuario (id_usuario), no a un
 * negocio. Lo que impide usar el código de otra cuenta es que siempre se busca
 * el código DEL usuario dueño del correo que se escribió.
 */
class SvcRecuperacionClave
{
    const MINUTOS_VIGENCIA = 60;

    /* Resultados de confirmar(). */
    const CLAVE_CAMBIADA = 'clave_cambiada';

    const CODIGO_INVALIDO = 'codigo_invalido';

    const ERROR = 'error';

    /**
     * Guarda un código nuevo para el usuario (hasheado) y borra los anteriores
     * sin usar: solo el último pedido sirve.
     *
     * @return bool
     */
    public function solicitar(int $idUsuario, string $codigo): bool
    {
        try {
            DB::transaction(function () use ($idUsuario, $codigo) {
                CodigoRecuperacionClave::where('id_usuario', $idUsuario)->whereNull('usado_en')->delete();

                CodigoRecuperacionClave::create([
                    'id_usuario' => $idUsuario,
                    'codigo_hash' => Hash::make($codigo),
                    'fecha_expiracion' => now()->addMinutes(self::MINUTOS_VIGENCIA),
                    'fecha_registro' => now(),
                ]);
            });

            return true;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Si el código es el vigente del usuario dueño de ese correo, cambia la
     * clave, marca el código como usado, borra los demás sin usar e
     * incrementa version_sesion (lo que cierra todas sus sesiones abiertas:
     * ver VerificarSesion). Todo en una transacción.
     *
     * Un correo que no existe y un código equivocado dan el MISMO resultado:
     * desde fuera no se distingue cuál de los dos falló.
     */
    public function confirmar(string $correo, string $codigo, string $claveNueva): string
    {
        try {
            return DB::transaction(function () use ($correo, $codigo, $claveNueva) {
                $idUsuario = Usuario::where('email', $correo)->where('estado', 1)->value('id_usuario');

                if ($idUsuario === null) {
                    // Un bcrypt de relleno para que un correo inexistente tarde
                    // lo mismo que uno real con el código equivocado: si no, el
                    // tiempo de respuesta revelaría qué correos existen.
                    Hash::make($codigo);

                    return self::CODIGO_INVALIDO;
                }

                // El vigente más reciente DE ESTE usuario, bloqueado: dos envíos
                // simultáneos del mismo código no pueden usarlo dos veces.
                $vigente = CodigoRecuperacionClave::where('id_usuario', $idUsuario)
                    ->whereNull('usado_en')
                    ->where('fecha_expiracion', '>', now())
                    ->orderByDesc('id_codigo')
                    ->lockForUpdate()
                    ->first();

                if ($vigente === null) {
                    Hash::make($codigo);

                    return self::CODIGO_INVALIDO;
                }

                if (! Hash::check($codigo, $vigente->codigo_hash)) {
                    return self::CODIGO_INVALIDO;
                }

                Usuario::where('id_usuario', $idUsuario)->update(['clave' => Hash::make($claveNueva)]);
                Usuario::where('id_usuario', $idUsuario)->increment('version_sesion');

                CodigoRecuperacionClave::where('id_codigo', $vigente->id_codigo)->update(['usado_en' => now()]);
                CodigoRecuperacionClave::where('id_usuario', $idUsuario)->whereNull('usado_en')->delete();

                return self::CLAVE_CAMBIADA;
            });
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return self::ERROR;
        }
    }

    /**
     * PROCESO DE SISTEMA (comando auth:limpiar-pendientes-vencidos): borra los
     * códigos vencidos que nunca se usaron. Sin tenant: es tabla de plataforma.
     * Los usados se conservan como registro.
     *
     * @return int|false Filas borradas.
     */
    public function borrarVencidos()
    {
        try {
            return CodigoRecuperacionClave::where('fecha_expiracion', '<', now())->whereNull('usado_en')->delete();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }
}
