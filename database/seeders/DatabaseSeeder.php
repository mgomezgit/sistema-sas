<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Siembra lo que necesita CUALQUIER instalación, incluida la de producción:
     * solo los roles. No crea usuarios ni datos de demostración.
     *
     * La cuenta super admin del servidor se crea aparte, de forma interactiva:
     *   php artisan plataforma:crear-super-admin
     *
     * Datos de demostración (SOLO en entorno local; el seeder se niega fuera):
     *   php artisan db:seed --class=UsuarioDemoSeeder
     * Datos de prueba realistas (a mano, solo inserta y nunca borra):
     *   php artisan db:seed --class=DatosPruebaSeeder
     */
    public function run(): void
    {
        $this->call(RolSeeder::class);
    }
}
