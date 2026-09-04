<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(UsuarioDemoSeeder::class);

        // Datos de prueba realistas para los negocios 1 y 5. Se deja fuera de la
        // ejecución automática a propósito: solo inserta (nunca borra), así que
        // llamarlo repetidamente duplicaría registros. Ejecutar a mano cuando se
        // necesite:  php artisan db:seed --class=DatosPruebaSeeder
        // $this->call(DatosPruebaSeeder::class);
    }
}
