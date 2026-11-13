<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guardián: el proyecto NO usa Vite ni npm (todo el frontend va por CDN, sin
 * build tools; ver CLAUDE.md). Los restos del esqueleto de Laravel
 * (package.json, vite.config.js, .npmrc, welcome.blade.php con @vite) se
 * eliminaron; si alguno vuelve a aparecer, esta prueba falla.
 *
 * ================= MUTACIÓN =================
 * Se agregó @vite(['resources/js/app.js']) a resources/views/login.blade.php:
 * falló test_ninguna_vista_carga_assets_con_vite nombrando ese archivo.
 * Restaurado, pasa. (Resultado exacto en el reporte de la tarea.)
 */
class SinViteNiNpmTest extends TestCase
{
    const ARCHIVOS_PROHIBIDOS = [
        'package.json',
        'package-lock.json',
        'vite.config.js',
        '.npmrc',
    ];

    public function test_no_existen_archivos_de_vite_ni_npm(): void
    {
        foreach (self::ARCHIVOS_PROHIBIDOS as $archivo) {
            $this->assertFileDoesNotExist(
                base_path($archivo),
                "$archivo no debe existir: el proyecto no usa Vite ni npm (todo va por CDN)."
            );
        }
    }

    public function test_ninguna_vista_carga_assets_con_vite(): void
    {
        $conVite = [];

        foreach (File::allFiles(resource_path('views')) as $archivo) {
            if (stripos($archivo->getContents(), '@vite') !== false) {
                $conVite[] = str_replace('\\', '/', $archivo->getRelativePathname());
            }
        }

        $this->assertSame([], $conVite, 'Estas vistas usan @vite, pero el proyecto no tiene Vite: '.implode(', ', $conVite));
    }

    public function test_los_scripts_de_composer_no_llaman_a_npm(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        foreach ($composer['scripts'] ?? [] as $nombre => $pasos) {
            foreach ((array) $pasos as $paso) {
                $this->assertDoesNotMatchRegularExpression('/\b(npm|npx|yarn|pnpm|bun)\b/i', (string) $paso, "El script de composer '$nombre' no debe llamar a npm.");
                // 'php artisan dev' lanza concurrently con npx: depende de Node.
                $this->assertStringNotContainsString('artisan dev', (string) $paso, "El script de composer '$nombre' no debe usar 'artisan dev' (requiere Node).");
            }
        }
    }
}
