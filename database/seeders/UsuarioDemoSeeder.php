<?php

namespace Database\Seeders;

use App\Models\Negocio;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de DEMOSTRACIÓN (negocio demo, admin y super admin con claves
 * conocidas). SOLO para el entorno local: fuera de él se niega sin escribir
 * nada, porque crearía cuentas con claves públicas en un servidor real.
 *
 * Los roles los crea RolSeeder (el seeder de producción), no este.
 */
class UsuarioDemoSeeder extends Seeder
{
    const MENSAJE_SOLO_LOCAL = 'UsuarioDemoSeeder solo se ejecuta en el entorno local (APP_ENV=local): crea cuentas de demostración con claves conocidas. No se escribió nada. En un servidor usa "php artisan db:seed" (solo roles) y "php artisan plataforma:crear-super-admin".';

    /**
     * Seed la información demo del módulo de usuarios.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error(self::MENSAJE_SOLO_LOCAL);

            return;
        }

        $this->call(RolSeeder::class);

        $negocio = Negocio::create([
            'nombre_negocio' => 'Negocio Demo',
            'rubro' => 'spa',
            'estado' => 1,
        ]);

        $rolAdmin = Rol::where('nombre_rol', 'admin')->firstOrFail();
        $rolSuperAdmin = Rol::where('nombre_rol', Rol::SUPER_ADMIN)->firstOrFail();

        Usuario::create([
            'usuario' => 'admin',
            'nombre' => 'Administrador Demo',
            'email' => 'admin@demo.test',
            'clave' => Hash::make('admin123'),
            'tenant_id' => $negocio->id_negocio,
            'id_rol' => $rolAdmin->id_rol,
            'estado' => 1,
        ]);

        Usuario::create([
            'usuario' => 'superadmin',
            'nombre' => 'Super Administrador',
            'email' => 'superadmin@demo.test',
            'clave' => Hash::make('superadmin123'),
            'tenant_id' => null,
            'id_rol' => $rolSuperAdmin->id_rol,
            'estado' => 1,
        ]);
    }
}
