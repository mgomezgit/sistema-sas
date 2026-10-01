<?php

namespace App\Service;

use App\Models\RegistroPendiente;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Registro público en dos pasos: nada se crea en negocios ni en usuarios
 * hasta que la persona confirma su correo abriendo el link que le llega.
 *
 * Paso 1 (solicitar): guarda los datos con la clave ya hasheada y un token
 * de un solo uso, del que solo se guarda el hash.
 * Paso 2 (confirmar): con el token del link, crea el negocio y su admin.
 *
 * No hay tenant_id que filtrar: mientras el registro está pendiente todavía
 * no existe ningún negocio. Lo que separa un registro de otro es el token,
 * que solo conoce quien recibió el correo.
 */
class SvcRegistroPendiente
{
    const HORAS_VIGENCIA = 24;

    /* Resultados de confirmar(). */
    const CONFIRMADO = 'confirmado';

    const TOKEN_INVALIDO = 'token_invalido';

    const YA_CONFIRMADO = 'ya_confirmado';

    const CORREO_OCUPADO = 'correo_ocupado';

    const ERROR = 'error';

    public function __construct(
        private SvcNegocio $svcNegocio = new SvcNegocio,
        private SvcUsuario $svcUsuario = new SvcUsuario,
    ) {}

    /**
     * sha256 y no bcrypt: el link solo trae el token, así que hay que poder
     * BUSCAR por su hash. Con 64 caracteres aleatorios no hace falta que el
     * hash sea lento: no hay forma práctica de adivinarlo por fuerza bruta.
     */
    private function hashDeToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function normalizarCorreo(string $correo): string
    {
        return Str::lower(trim($correo));
    }

    /**
     * Paso 1. Guarda el registro pendiente y devuelve el token EN CLARO, solo
     * para ponerlo en el link del correo; en la base queda únicamente su hash.
     *
     * Si ya había un pendiente sin confirmar con el mismo correo, se borra:
     * no se acumulan pendientes, y el link viejo deja de servir. (Los ya
     * confirmados no se tocan: quedan como historial.)
     *
     * @param  array  $datos  nombre_negocio, rubro, telefono_contacto, nombre_admin, correo.
     * @param  string  $claveHash  La clave ya hasheada por quien llama.
     * @return string|false
     */
    public function solicitar(array $datos, string $claveHash)
    {
        try {
            $correo = $this->normalizarCorreo($datos['correo']);
            $token = Str::random(64);

            DB::transaction(function () use ($datos, $claveHash, $correo, $token) {
                RegistroPendiente::where('correo', $correo)->whereNull('confirmado_en')->delete();

                RegistroPendiente::create([
                    'nombre_negocio' => $datos['nombre_negocio'],
                    'rubro' => $datos['rubro'],
                    'telefono_contacto' => $datos['telefono_contacto'],
                    'nombre_admin' => $datos['nombre_admin'],
                    'correo' => $correo,
                    'clave_hash' => $claveHash,
                    'token_hash' => $this->hashDeToken($token),
                    'fecha_expiracion' => now()->addHours(self::HORAS_VIGENCIA),
                    'fecha_registro' => now(),
                ]);
            });

            return $token;
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * PROCESO DE SISTEMA (comando auth:limpiar-pendientes-vencidos): borra los
     * registros vencidos que nunca se confirmaron, que son los únicos que
     * guardan una clave hasheada sin cuenta detrás. Los confirmados se
     * conservan como historial. Sin tenant: es tabla de plataforma.
     *
     * @return int|false Filas borradas.
     */
    public function borrarVencidos()
    {
        try {
            return RegistroPendiente::where('fecha_expiracion', '<', now())->whereNull('confirmado_en')->delete();
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return false;
        }
    }

    /**
     * Paso 2. Si el token es válido, crea el negocio y su usuario admin y
     * marca el registro como confirmado. Todo en una transacción, con la fila
     * bloqueada: dos clics seguidos en el mismo link no pueden crear dos
     * negocios.
     *
     * @return array{resultado: string, id_usuario: int|null}
     */
    public function confirmar(string $token): array
    {
        try {
            return DB::transaction(function () use ($token) {
                $pendiente = RegistroPendiente::where('token_hash', $this->hashDeToken($token))
                    ->lockForUpdate()
                    ->first();

                if ($pendiente === null) {
                    return ['resultado' => self::TOKEN_INVALIDO, 'id_usuario' => null];
                }

                if ($pendiente->confirmado_en !== null) {
                    return ['resultado' => self::YA_CONFIRMADO, 'id_usuario' => null];
                }

                if (now()->greaterThan($pendiente->fecha_expiracion)) {
                    return ['resultado' => self::TOKEN_INVALIDO, 'id_usuario' => null];
                }

                // Entre el paso 1 y el 2 alguien pudo crear una cuenta activa
                // con este correo (el correo es único en toda la plataforma).
                if (Usuario::where('email', $pendiente->correo)->where('estado', 1)->exists()) {
                    return ['resultado' => self::CORREO_OCUPADO, 'id_usuario' => null];
                }

                $idNegocio = $this->svcNegocio->crear([
                    'nombre_negocio' => $pendiente->nombre_negocio,
                    'slug' => $this->svcNegocio->generarSlug($pendiente->nombre_negocio),
                    'rubro' => $pendiente->rubro,
                    'telefono_contacto' => $pendiente->telefono_contacto,
                    'usuario_registra' => 'Registro Publico',
                    'fecha_registro' => date('Y-m-d H:i:s'),
                    'estado' => 1,
                ]);

                if ($idNegocio === false) {
                    throw new \RuntimeException('REG-CONF-NEGOCIO');
                }

                $idRolAdmin = Rol::where('nombre_rol', 'admin')->value('id_rol');

                if (! $idRolAdmin) {
                    throw new \RuntimeException('REG-CONF-SIN-ROL-ADMIN');
                }

                $idUsuario = $this->svcUsuario->crearConClaveHasheada([
                    'tenant_id' => $idNegocio,
                    'id_rol' => $idRolAdmin,
                    'usuario' => $pendiente->correo,
                    'nombre' => $pendiente->nombre_admin,
                    'email' => $pendiente->correo,
                    'clave' => $pendiente->clave_hash,
                    'usuario_registra' => 'Registro Publico',
                    'fecha_registro' => date('Y-m-d H:i:s'),
                    'estado' => 1,
                ]);

                if ($idUsuario === false) {
                    throw new \RuntimeException('REG-CONF-USUARIO');
                }

                RegistroPendiente::where('id_registro_pendiente', $pendiente->id_registro_pendiente)
                    ->update(['confirmado_en' => now()]);

                return ['resultado' => self::CONFIRMADO, 'id_usuario' => (int) $idUsuario];
            });
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            return ['resultado' => self::ERROR, 'id_usuario' => null];
        }
    }
}
