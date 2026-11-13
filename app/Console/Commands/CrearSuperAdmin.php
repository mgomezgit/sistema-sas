<?php

namespace App\Console\Commands;

use App\Models\Rol;
use App\Service\SvcUsuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crea la cuenta super admin de un servidor nuevo, de forma INTERACTIVA.
 *
 * La clave se pide con entrada oculta y se confirma dos veces; NUNCA llega por
 * argumento, opción ni variable de entorno, para que no quede en el historial
 * de la terminal ni en la lista de procesos. No se imprime ni se registra la
 * clave ni su hash, y no se envía ningún correo.
 *
 * Solo puede existir UNA cuenta super admin: el comando se niega si ya hay
 * una (activa o inactiva) o si faltan los roles. La regla vive en
 * SvcUsuario::crearCuentaDePlataforma y la base la respalda.
 */
class CrearSuperAdmin extends Command
{
    /** Sin argumentos ni opciones: todo se pregunta. */
    protected $signature = 'plataforma:crear-super-admin';

    protected $description = 'Crea, de forma interactiva, la unica cuenta super admin del servidor';

    public function handle(SvcUsuario $svcUsuario): int
    {
        if (Rol::idPorNombre(Rol::SUPER_ADMIN) === null) {
            $this->error('No existe el rol super_admin: ejecuta primero el seeder de roles (php artisan db:seed).');

            return self::FAILURE;
        }

        if ($svcUsuario->existeCuentaDePlataforma()) {
            $this->error('Ya existe una cuenta super admin en esta plataforma (activa o inactiva). Solo puede haber una; no se creó nada.');

            return self::FAILURE;
        }

        $nombre = trim((string) $this->ask('Nombre completo'));
        $usuario = trim((string) $this->ask('Usuario'));
        $correo = mb_strtolower(trim((string) $this->ask('Correo')));

        $validador = Validator::make(
            ['nombre' => $nombre, 'usuario' => $usuario, 'correo' => $correo],
            [
                'nombre' => 'required|max:100',
                'usuario' => 'required|max:50',
                'correo' => 'required|email|max:150',
            ],
            [
                'nombre.required' => 'El nombre es obligatorio.',
                'usuario.required' => 'El usuario es obligatorio.',
                'correo.required' => 'El correo es obligatorio.',
                'correo.email' => 'El correo no tiene un formato válido.',
            ]
        );

        if ($validador->fails()) {
            $this->error(implode(' ', $validador->errors()->all()).' No se creó nada.');

            return self::FAILURE;
        }

        if ($svcUsuario->credencialActivaOcupada('email', $correo)) {
            $this->error('Ese correo ya lo usa una cuenta activa. No se creó nada.');

            return self::FAILURE;
        }

        if ($svcUsuario->credencialActivaOcupada('usuario', $usuario)) {
            $this->error('Ese usuario ya lo usa una cuenta activa. No se creó nada.');

            return self::FAILURE;
        }

        $clave = (string) $this->secret('Clave (mínimo '.SvcUsuario::LARGO_MINIMO_CLAVE_PLATAFORMA.' caracteres, no se muestra)');
        $confirmacion = (string) $this->secret('Repite la clave');

        if (mb_strlen($clave) < SvcUsuario::LARGO_MINIMO_CLAVE_PLATAFORMA) {
            $this->error('La clave debe tener al menos '.SvcUsuario::LARGO_MINIMO_CLAVE_PLATAFORMA.' caracteres. No se creó nada.');

            return self::FAILURE;
        }

        if (! hash_equals($clave, $confirmacion)) {
            $this->error('Las dos claves no coinciden. No se creó nada.');

            return self::FAILURE;
        }

        $idUsuario = $svcUsuario->crearCuentaDePlataforma([
            'nombre' => $nombre,
            'usuario' => $usuario,
            'email' => $correo,
            'clave' => $clave,
        ]);

        // La clave ya no hace falta: se suelta antes de seguir.
        unset($clave, $confirmacion);

        if ($idUsuario === false) {
            $this->error('No se pudo crear la cuenta. Revisa el log del canal database (no contiene la clave).');

            return self::FAILURE;
        }

        $this->info('Cuenta super admin creada.');
        $this->line('Correo: '.$correo);
        $this->line('Usuario: '.$usuario);
        $this->line('Si alguna vez compartiste esta clave, cámbiala desde "Recuperar clave" en la pantalla de inicio de sesión.');

        return self::SUCCESS;
    }
}
