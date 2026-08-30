<?php

namespace Database\Seeders;

use App\Models\Empleado;
use App\Models\Negocio;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Empleada demo con acceso al sistema, para poder probar el rol "empleado"
 * (solo ve sus propias citas). No se ejecuta con DatabaseSeeder por defecto:
 *
 *   php artisan db:seed --class=EmpleadoDemoSeeder
 *
 * Credenciales: laura@demo.test / laura123
 */
class EmpleadoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $idNegocio = Negocio::value('id_negocio');
        $idRolEmpleado = Rol::where('nombre_rol', 'empleado')->value('id_rol');

        if (! $idNegocio || ! $idRolEmpleado) {
            $this->command->warn('Falta el negocio o el rol "empleado". Ejecuta primero: php artisan db:seed --class=UsuarioDemoSeeder');

            return;
        }

        if (Usuario::where('email', 'laura@demo.test')->exists()) {
            $this->command->warn('La empleada demo ya existe, no se creó de nuevo.');

            return;
        }

        $empleado = Empleado::create([
            'tenant_id' => $idNegocio,
            'nombre' => 'Laura Gomez',
            'telefono' => '3011234567',
            'cargo' => 'Masajista',
            'porcentaje_comision' => 15,
            'id_usuario' => null,
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $usuario = Usuario::create([
            'usuario' => 'laura.gomez',
            'nombre' => $empleado->nombre,
            'email' => 'laura@demo.test',
            'clave' => Hash::make('laura123'),
            'tenant_id' => $idNegocio,
            'id_rol' => $idRolEmpleado,
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);

        $empleado->update(['id_usuario' => $usuario->id_usuario]);

        $this->command->info('Empleada demo creada: laura@demo.test / laura123');
    }
}
