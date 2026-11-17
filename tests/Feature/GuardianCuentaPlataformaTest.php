<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guardián de la cuenta de plataforma (super admin).
 *
 * SvcUsuario::crearCuentaDePlataforma es la ÚNICA vía para crear la cuenta
 * super admin, y solo la usa el comando plataforma:crear-super-admin. Además,
 * el rol super_admin no puede aparecer como rol asignable en ningún otro lugar
 * del código. Esta prueba recorre app/, routes/, database/ y config/ y falla si:
 *
 * 1) cualquier archivo que no sea el Service que lo define o el comando hace
 *    referencia a crearCuentaDePlataforma;
 * 2) aparece un uso de Rol::SUPER_ADMIN, self::SUPER_ADMIN o del texto
 *    'super_admin' fuera de la lista blanca de abajo (o más veces de las
 *    documentadas en un archivo de la lista);
 * 3) aparece un id de rol 3 escrito a mano ('id_rol' => 3) en app/ o routes/.
 *
 * Solo cuenta CÓDIGO: los comentarios y docblocks se ignoran (tokenizador de
 * PHP), para que documentar la regla no dispare el guardián.
 *
 * Si un cambio legítimo agrega un uso, se suma a la lista blanca CON SU MOTIVO
 * después de revisar que no convierte a super_admin en un rol asignable.
 *
 * ================= MUTACIÓN (resultado en el reporte de la tarea) =================
 * Se agregó una llamada a crearCuentaDePlataforma en UsuarioController: falló
 * test_solo_el_service_y_el_comando_mencionan_crear_cuenta_de_plataforma
 * nombrando ese archivo. Restaurado, pasa.
 */
class GuardianCuentaPlataformaTest extends TestCase
{
    const DIRECTORIOS = ['app', 'routes', 'database', 'config'];

    /** Únicos archivos que pueden mencionar crearCuentaDePlataforma. */
    const PERMITIDOS_CREAR_CUENTA = [
        'app/Service/SvcUsuario.php' => 'Define el método: es la única vía para crear la cuenta super admin.',
        'app/Console/Commands/CrearSuperAdmin.php' => 'El comando interactivo plataforma:crear-super-admin, su único llamador.',
    ];

    /**
     * Archivo => [cantidad exacta de apariciones en código, motivo].
     * Ninguna convierte a super_admin en un rol asignable al crear o editar
     * usuarios desde la aplicación.
     */
    const LISTA_BLANCA_SUPER_ADMIN = [
        'app/Models/Rol.php' => [2, 'Define la constante SUPER_ADMIN y la usa en esRolSuperAdmin(), la comprobación de rol que usan los middlewares SoloSuperAdmin y VerificarSesion.'],
        'app/Service/SvcUsuario.php' => [3, 'Busca el id del rol para esCuentaDePlataforma() y existeCuentaDePlataforma() (protegen la cuenta) y para crearCuentaDePlataforma() (la única vía de creación).'],
        'app/Console/Commands/CrearSuperAdmin.php' => [1, 'Precondición del comando: se niega si no existe el rol ("ejecuta primero el seeder de roles").'],
        'database/seeders/RolSeeder.php' => [1, 'Define el rol con su id fijo (3), el que asume la migración de blindaje.'],
        'database/seeders/UsuarioDemoSeeder.php' => [1, 'Cuenta de demostración, SOLO en APP_ENV=local: el seeder se niega en cualquier otro entorno.'],
        'database/migrations/2026_10_31_100000_blindar_cuenta_super_admin_unica.php' => [1, 'Lee el id del rol para la restricción de la base (un solo super admin activo y sin negocio).'],
    ];

    /** Rutas relativas (con "/") de todos los .php de los directorios revisados. */
    private function archivos(): array
    {
        $archivos = [];

        foreach (self::DIRECTORIOS as $dir) {
            foreach (File::allFiles(base_path($dir)) as $archivo) {
                if ($archivo->getExtension() === 'php') {
                    $archivos[] = $dir.'/'.str_replace('\\', '/', $archivo->getRelativePathname());
                }
            }
        }

        return $archivos;
    }

    /** El código del archivo SIN comentarios ni docblocks. */
    private function codigoSinComentarios(string $relativo): string
    {
        $codigo = '';

        foreach (token_get_all(file_get_contents(base_path($relativo))) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $codigo .= $token[1];
            } else {
                $codigo .= $token;
            }
        }

        return $codigo;
    }

    public function test_solo_el_service_y_el_comando_mencionan_crear_cuenta_de_plataforma(): void
    {
        $indebidos = [];

        foreach ($this->archivos() as $archivo) {
            if (array_key_exists($archivo, self::PERMITIDOS_CREAR_CUENTA)) {
                continue;
            }

            if (str_contains($this->codigoSinComentarios($archivo), 'crearCuentaDePlataforma')) {
                $indebidos[] = $archivo;
            }
        }

        $this->assertSame([], $indebidos, 'Solo el comando plataforma:crear-super-admin puede crear la cuenta de plataforma. La mencionan además: '.implode(', ', $indebidos));
    }

    public function test_el_service_y_el_comando_siguen_siendo_los_unicos_y_existen(): void
    {
        foreach (array_keys(self::PERMITIDOS_CREAR_CUENTA) as $archivo) {
            $this->assertStringContainsString('crearCuentaDePlataforma', $this->codigoSinComentarios($archivo), "$archivo debería seguir usando crearCuentaDePlataforma (si cambió, actualiza el guardián)");
        }
    }

    public function test_el_rol_super_admin_solo_aparece_en_los_usos_conocidos(): void
    {
        $encontrados = [];

        foreach ($this->archivos() as $archivo) {
            $codigo = $this->codigoSinComentarios($archivo);
            $n = preg_match_all('/\b(?:Rol|self|static)::SUPER_ADMIN\b|[\'"]super_admin[\'"]/', $codigo);

            if ($n > 0) {
                $encontrados[$archivo] = $n;
            }
        }

        $esperados = array_map(fn ($par) => $par[0], self::LISTA_BLANCA_SUPER_ADMIN);
        ksort($encontrados);
        ksort($esperados);

        $this->assertSame(
            $esperados,
            $encontrados,
            'Cambió el uso del rol super_admin en el código. Si es legítimo (no lo vuelve asignable al crear o editar usuarios), '
            .'actualiza LISTA_BLANCA_SUPER_ADMIN con su motivo.'
        );
    }

    public function test_ningun_id_de_rol_3_escrito_a_mano_en_la_aplicacion(): void
    {
        $indebidos = [];

        foreach ($this->archivos() as $archivo) {
            if (! str_starts_with($archivo, 'app/') && ! str_starts_with($archivo, 'routes/')) {
                continue;
            }

            if (preg_match('/[\'"]id_rol[\'"]\s*=>\s*3\b/', $this->codigoSinComentarios($archivo))) {
                $indebidos[] = $archivo;
            }
        }

        $this->assertSame([], $indebidos, 'El rol super_admin no se asigna con un id escrito a mano: '.implode(', ', $indebidos));
    }
}
