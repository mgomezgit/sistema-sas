<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Roles del sistema: el ÚNICO seeder apto para un servidor real.
 *
 * Crea los tres roles con ids EXPLÍCITOS (admin 1, empleado 2, super_admin 3),
 * sin usuarios ni datos de demostración. El id 3 de super_admin no es casual:
 * la migración 2026_10_31_100000_blindar_cuenta_super_admin_unica lo usa como
 * respaldo cuando la tabla roles está vacía al migrar, así que ambos deben
 * coincidir (SeederRolesTest lo vigila).
 *
 * Idempotente: si un rol ya existe con el mismo id y nombre, no cambia nada. Si
 * un id existe con OTRO nombre, o un nombre existe con OTRO id, se detiene con
 * un mensaje claro ANTES de escribir nada: nunca sobrescribe un rol.
 */
class RolSeeder extends Seeder
{
    /** id_rol => nombre_rol. No cambiar sin revisar la migración de blindaje. */
    const ROLES = [
        1 => 'admin',
        2 => 'empleado',
        3 => 'super_admin',
    ];

    public function run(): void
    {
        // 1) Comprobar TODO antes de escribir nada.
        foreach (self::ROLES as $id => $nombre) {
            $nombreActual = Rol::where('id_rol', $id)->value('nombre_rol');

            if ($nombreActual !== null && $nombreActual !== $nombre) {
                throw new \RuntimeException(
                    "RolSeeder detenido: el rol con id $id ya existe con el nombre '$nombreActual' y debería llamarse '$nombre'. "
                    .'No se sobrescribió nada. Revisa la tabla roles a mano antes de volver a sembrar.'
                );
            }

            $idActual = Rol::where('nombre_rol', $nombre)->value('id_rol');

            if ($idActual !== null && (int) $idActual !== $id) {
                throw new \RuntimeException(
                    "RolSeeder detenido: el rol '$nombre' ya existe con el id $idActual y debería tener el id $id. "
                    .'No se sobrescribió nada. Revisa la tabla roles a mano antes de volver a sembrar.'
                );
            }
        }

        // 2) Crear solo los que faltan, con su id explícito.
        DB::transaction(function () {
            foreach (self::ROLES as $id => $nombre) {
                if (Rol::where('id_rol', $id)->exists()) {
                    continue;
                }

                Rol::create([
                    'id_rol' => $id,
                    'nombre_rol' => $nombre,
                    'usuario_registra' => 'RolSeeder',
                    'fecha_registro' => now(),
                    'estado' => 1,
                ]);
            }
        });
    }
}
