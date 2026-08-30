<?php

namespace Database\Seeders;

use App\Models\Negocio;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioDemoSeeder extends Seeder
{
    /**
     * Seed la información demo del módulo de usuarios.
     */
    public function run(): void
    {
        $negocio = Negocio::create([
            'nombre_negocio' => 'Negocio Demo',
            'rubro' => 'spa',
            'estado' => 1,
        ]);

        $rolAdmin = Rol::create([
            'nombre_rol' => 'admin',
            'estado' => 1,
        ]);

        Rol::create([
            'nombre_rol' => 'empleado',
            'estado' => 1,
        ]);

        $rolSuperAdmin = Rol::create([
            'nombre_rol' => 'super_admin',
            'estado' => 1,
        ]);

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
