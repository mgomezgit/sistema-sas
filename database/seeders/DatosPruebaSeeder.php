<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de prueba realistas para los negocios 1 y 5.
 *
 * Este seeder SOLO inserta: nunca borra ni sobreescribe registros existentes,
 * porque estos negocios pueden tener datos reales. Todo lo que crea queda
 * marcado con usuario_registra = 'seeder', así se puede distinguir después.
 *
 * Se ejecuta a mano:  php artisan db:seed --class=DatosPruebaSeeder
 */
class DatosPruebaSeeder extends Seeder
{
    const NEGOCIOS = [1, 5];

    const MARCA = 'seeder';

    // El horario que queda configurado en ambos negocios.
    const DIAS_ATENCION = '1,2,3,4,5,6';

    const HORA_APERTURA = '07:00:00';

    const HORA_CIERRE = '17:00:00';

    public function run(): void
    {
        foreach (self::NEGOCIOS as $tenantId) {
            if (! DB::table('negocios')->where('id_negocio', $tenantId)->exists()) {
                $this->command->warn("Negocio {$tenantId} no existe, se omite.");

                continue;
            }

            $this->configurarHorario($tenantId);

            $clientes = $this->crearClientes($tenantId);
            $recursos = $this->crearRecursos($tenantId);
            $empleados = $this->crearEmpleados($tenantId);

            $reservas = $this->crearReservas($tenantId, $clientes, $recursos, $empleados);

            $this->command->info(
                "Negocio {$tenantId}: ".count($clientes).' clientes, '.count($recursos).' recursos, '
                .count($empleados).' empleados, '.$reservas.' reservas.'
            );
        }
    }

    private function configurarHorario($tenantId): void
    {
        DB::table('negocios')->where('id_negocio', $tenantId)->update([
            'dias_atencion' => self::DIAS_ATENCION,
            'hora_apertura' => self::HORA_APERTURA,
            'hora_cierre' => self::HORA_CIERRE,
        ]);
    }

    private function crearClientes($tenantId): array
    {
        $nombres = [
            'Valentina Restrepo', 'Santiago Cárdenas', 'Mariana Ospina', 'Andrés Villegas',
            'Camila Betancur', 'Juan Pablo Arango', 'Daniela Zapata', 'Sebastián Mejía',
            'Laura Quintero', 'Nicolás Herrera', 'Isabella London', 'Tomás Gallego',
            'Manuela Escobar', 'Felipe Cadavid', 'Sara Montoya',
        ];

        $ids = [];

        foreach ($nombres as $indice => $nombre) {
            $ids[] = DB::table('clientes')->insertGetId([
                'tenant_id' => $tenantId,
                'nombre' => $nombre,
                'telefono' => '3'.rand(0, 2).rand(0, 9).' '.rand(200, 899).' '.rand(1000, 9999),
                'email' => $this->correoDesdeNombre($nombre, $indice),
                'documento_identidad' => (string) rand(10000000, 1299999999),
                'fecha_nacimiento' => date('Y-m-d', strtotime('-'.rand(20, 55).' years -'.rand(0, 360).' days')),
                'notas' => null,
                'usuario_registra' => self::MARCA,
                'fecha_registro' => date('Y-m-d H:i:s'),
                'estado' => 1,
            ]);
        }

        return $ids;
    }

    private function crearRecursos($tenantId): array
    {
        // [categoría, nombre, duración en minutos, precio]
        $servicios = [
            ['Masajes', 'Masaje relajante de cuerpo completo', 60, 95000],
            ['Masajes', 'Masaje descontracturante de espalda', 45, 75000],
            ['Masajes', 'Masaje con piedras calientes', 90, 165000],
            ['Masajes', 'Drenaje linfático', 60, 110000],
            ['Faciales', 'Limpieza facial profunda', 60, 90000],
            ['Faciales', 'Facial hidratante con ácido hialurónico', 45, 120000],
            ['Faciales', 'Peeling químico suave', 45, 140000],
            ['Depilación', 'Depilación de piernas completas', 45, 70000],
            ['Depilación', 'Depilación de axilas', 30, 40000],
            ['Depilación', 'Depilación facial con cera', 30, 45000],
            ['Manos y pies', 'Manicure semipermanente', 60, 65000],
            ['Manos y pies', 'Pedicure spa', 60, 80000],
            ['Cejas y pestañas', 'Diseño y perfilado de cejas', 30, 50000],
            ['Cejas y pestañas', 'Lifting de pestañas', 60, 130000],
            ['Corporales', 'Exfoliación corporal con sales', 75, 155000],
        ];

        $ids = [];

        foreach ($servicios as $servicio) {
            [$categoria, $nombre, $duracion, $precio] = $servicio;

            $ids[] = DB::table('recursos_reservables')->insertGetId([
                'tenant_id' => $tenantId,
                'categoria' => $categoria,
                'nombre' => $nombre,
                'descripcion' => 'Servicio de '.mb_strtolower($categoria).' con productos profesionales.',
                'duracion_minutos' => $duracion,
                'precio' => $precio,
                'capacidad' => 1,
                'usuario_registra' => self::MARCA,
                'fecha_registro' => date('Y-m-d H:i:s'),
                'estado' => 1,
            ]);
        }

        return $ids;
    }

    private function crearEmpleados($tenantId): array
    {
        // [nombre, cargo]
        $personas = [
            ['Aura Posso', 'Masajista'],
            ['Carolina Jaramillo', 'Esteticista'],
            ['Diana Ortiz', 'Masajista'],
            ['Luisa Fernanda Rúa', 'Cosmetóloga'],
            ['Paula Andrea Gil', 'Manicurista'],
            ['Natalia Bedoya', 'Recepcionista'],
            ['Jorge Iván Salazar', 'Masajista'],
            ['Catalina Uribe', 'Esteticista'],
            ['Marcela Duque', 'Depiladora profesional'],
            ['Andrea Ramírez', 'Cosmetóloga'],
            ['Sandra Milena Ríos', 'Manicurista'],
            ['Verónica Agudelo', 'Especialista en cejas'],
            ['Julián Castaño', 'Masajista'],
            ['Alejandra Vélez', 'Esteticista'],
            ['Yuliana Marín', 'Recepcionista'],
        ];

        $ids = [];

        foreach ($personas as $indice => $persona) {
            [$nombre, $cargo] = $persona;

            $ids[] = DB::table('empleados')->insertGetId([
                'tenant_id' => $tenantId,
                'nombre' => $nombre,
                'telefono' => '3'.rand(0, 2).rand(0, 9).' '.rand(200, 899).' '.rand(1000, 9999),
                'email' => $this->correoDesdeNombre($nombre, $indice),
                'cargo' => $cargo,
                'porcentaje_comision' => rand(10, 20),
                // Se siembran sin acceso al sistema; el acceso se crea a mano.
                'id_usuario' => null,
                'usuario_registra' => self::MARCA,
                'fecha_registro' => date('Y-m-d H:i:s'),
                'estado' => 1,
            ]);
        }

        return $ids;
    }

    /**
     * 15 reservas repartidas entre esta semana y la próxima, dentro del horario
     * y solo de lunes a sábado. Se lleva un registro de las franjas ya ocupadas
     * por cada empleado para no generar dos citas encimadas.
     */
    private function crearReservas($tenantId, array $clientes, array $recursos, array $empleados): int
    {
        $duraciones = DB::table('recursos_reservables')
            ->whereIn('id_recurso', $recursos)
            ->pluck('duracion_minutos', 'id_recurso')
            ->toArray();

        $fechas = $this->fechasHabiles();
        $estados = ['pendiente', 'confirmada', 'completada', 'cancelada'];

        // [id_empleado][fecha] => [[minutoInicio, minutoFin], ...]
        $ocupacion = [];
        $creadas = 0;

        for ($i = 0; $i < 15; $i++) {
            $idRecurso = $recursos[array_rand($recursos)];
            $duracion = (int) $duraciones[$idRecurso];

            // Una de cada cinco queda sin empleado asignado.
            $idEmpleado = ($i % 5 === 0) ? null : $empleados[array_rand($empleados)];

            $asignada = false;

            // Se prueban las fechas en orden aleatorio hasta encontrar un hueco.
            $fechasBarajadas = $fechas;
            shuffle($fechasBarajadas);

            foreach ($fechasBarajadas as $fecha) {
                $minutoInicio = $this->buscarHueco($ocupacion, $idEmpleado, $fecha, $duracion);

                if ($minutoInicio === null) {
                    continue;
                }

                DB::table('reservas')->insert([
                    'tenant_id' => $tenantId,
                    'id_cliente' => $clientes[array_rand($clientes)],
                    'id_recurso' => $idRecurso,
                    'id_empleado' => $idEmpleado,
                    'fecha_reserva' => $fecha,
                    'hora_inicio' => $this->aHora($minutoInicio),
                    'hora_fin' => $this->aHora($minutoInicio + $duracion),
                    'estado_reserva' => $estados[$i % 4],
                    'notas' => null,
                    'usuario_registra' => self::MARCA,
                    'fecha_registro' => date('Y-m-d H:i:s'),
                    'estado' => 1,
                ]);

                if ($idEmpleado !== null) {
                    $ocupacion[$idEmpleado][$fecha][] = [$minutoInicio, $minutoInicio + $duracion];
                }

                $creadas++;
                $asignada = true;
                break;
            }

            if (! $asignada) {
                $this->command->warn('No se encontró hueco libre para una reserva; se omite.');
            }
        }

        return $creadas;
    }

    /**
     * Primer minuto del día (en la rejilla de 30 min, dentro del horario) donde
     * cabe una cita de $duracion sin pisar a las que ya tiene ese empleado.
     * Devuelve null si ese día ya no da.
     */
    private function buscarHueco(array $ocupacion, $idEmpleado, string $fecha, int $duracion): ?int
    {
        $apertura = (int) (substr(self::HORA_APERTURA, 0, 2) * 60);
        $cierre = (int) (substr(self::HORA_CIERRE, 0, 2) * 60);

        $ocupadas = ($idEmpleado === null) ? [] : ($ocupacion[$idEmpleado][$fecha] ?? []);

        $candidatos = [];
        for ($minuto = $apertura; $minuto + $duracion <= $cierre; $minuto += 30) {
            $candidatos[] = $minuto;
        }

        shuffle($candidatos);

        foreach ($candidatos as $inicio) {
            $fin = $inicio + $duracion;
            $chocaConOtra = false;

            foreach ($ocupadas as $franja) {
                // Se solapan salvo que una termine antes de que empiece la otra.
                if ($inicio < $franja[1] && $franja[0] < $fin) {
                    $chocaConOtra = true;
                    break;
                }
            }

            if (! $chocaConOtra) {
                return $inicio;
            }
        }

        return null;
    }

    /**
     * Días de atención (lunes a sábado) de esta semana y la próxima.
     */
    private function fechasHabiles(): array
    {
        $dias = array_map('intval', explode(',', self::DIAS_ATENCION));
        $lunesDeEstaSemana = strtotime('monday this week');

        $fechas = [];

        for ($i = 0; $i < 14; $i++) {
            $dia = strtotime("+{$i} days", $lunesDeEstaSemana);

            if (in_array((int) date('N', $dia), $dias, true)) {
                $fechas[] = date('Y-m-d', $dia);
            }
        }

        return $fechas;
    }

    private function aHora(int $minutos): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutos, 60), $minutos % 60);
    }

    private function correoDesdeNombre(string $nombre, int $indice): string
    {
        $limpio = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', ' '],
            ['a', 'e', 'i', 'o', 'u', 'n', '.'],
            mb_strtolower($nombre)
        );

        return $limpio.$indice.'@correo.test';
    }
}
