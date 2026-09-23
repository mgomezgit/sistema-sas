<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use App\Service\SvcBannerPromocional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Banners promocionales: administración del negocio y vigencia por fechas.
 *
 * El disco se finge en todas las pruebas (Storage::fake), así que los archivos
 * que se suban aquí no tocan storage/app/public de verdad.
 */
class BannerPromocionalTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    private SvcBannerPromocional $svcBanner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->svcBanner = new SvcBannerPromocional;

        $this->negocioA = $this->crearNegocio('Spa Fashion', 'spa-fashion');
        $this->negocioB = $this->crearNegocio('Casa Canela', 'casa-canela');
    }

    private function crearNegocio(string $nombre, string $slug): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'slug' => $slug,
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function sesionAdmin(int $tenantId): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 1,
            'usuario' => 'admin.test',
            'nombre_usuario' => 'Admin Test',
            'tenant_id' => $tenantId,
            'id_rol' => 1,
        ];
    }

    private function imagenFalsa(string $nombre = 'promo.jpg', int $kilobytes = 120): UploadedFile
    {
        return UploadedFile::fake()->image($nombre, 1200, 500)->size($kilobytes);
    }

    /**
     * Alta directa por el Service, para las pruebas que no están mirando el
     * endpoint sino la regla de negocio.
     */
    private function crearBanner(int $tenantId, array $info = []): int
    {
        return $this->svcBanner->crear($tenantId, array_merge([
            'titulo' => 'Promo',
            'orden' => 0,
            'usuario_registra' => 'test',
        ], $info), $this->imagenFalsa());
    }

    private function rutaDe(int $idBanner): string
    {
        return DB::table('banners_promocionales')->where('id_banner', $idBanner)->value('imagen_path');
    }

    /* ================= 1) ALTA Y ARCHIVO EN DISCO ================= */

    public function test_crear_un_banner_guarda_la_imagen_y_la_fila(): void
    {
        $id = $this->crearBanner($this->negocioA, ['titulo' => 'Promo de apertura', 'texto' => '20% en masajes']);

        $this->assertIsInt($id);

        $fila = DB::table('banners_promocionales')->where('id_banner', $id)->first();

        $this->assertSame($this->negocioA, (int) $fila->tenant_id);
        $this->assertSame('Promo de apertura', $fila->titulo);
        $this->assertSame(1, (int) $fila->estado);

        // El archivo cayó en la carpeta del negocio, y la fila guarda la ruta
        // relativa, no una URL completa.
        Storage::disk('public')->assertExists($fila->imagen_path);
        $this->assertStringStartsWith('banners/'.$this->negocioA.'/', $fila->imagen_path);
        $this->assertStringNotContainsString('http', $fila->imagen_path);
    }

    /**
     * Dos subidas del MISMO nombre de archivo no pueden pisarse entre sí.
     */
    public function test_dos_imagenes_con_el_mismo_nombre_no_se_pisan(): void
    {
        $primero = $this->svcBanner->crear($this->negocioA, ['orden' => 0], $this->imagenFalsa('promo.jpg'));
        $segundo = $this->svcBanner->crear($this->negocioA, ['orden' => 1], $this->imagenFalsa('promo.jpg'));

        $rutaPrimero = $this->rutaDe($primero);
        $rutaSegundo = $this->rutaDe($segundo);

        $this->assertNotSame($rutaPrimero, $rutaSegundo);
        Storage::disk('public')->assertExists($rutaPrimero);
        Storage::disk('public')->assertExists($rutaSegundo);
    }

    /**
     * Cada negocio escribe en su propia carpeta.
     */
    public function test_cada_negocio_guarda_sus_imagenes_en_su_propia_carpeta(): void
    {
        $delA = $this->crearBanner($this->negocioA);
        $delB = $this->crearBanner($this->negocioB);

        $this->assertStringStartsWith('banners/'.$this->negocioA.'/', $this->rutaDe($delA));
        $this->assertStringStartsWith('banners/'.$this->negocioB.'/', $this->rutaDe($delB));
    }

    /* ================= 2) VALIDACIÓN DEL ARCHIVO ================= */

    public function test_el_service_rechaza_un_tipo_de_archivo_no_permitido(): void
    {
        $resultado = $this->svcBanner->crear(
            $this->negocioA,
            ['orden' => 0],
            UploadedFile::fake()->create('lista.pdf', 100, 'application/pdf')
        );

        $this->assertFalse($resultado);
        $this->assertSame(0, DB::table('banners_promocionales')->count());
        // Y no dejó basura en el disco.
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_el_service_rechaza_una_imagen_que_excede_el_peso(): void
    {
        $resultado = $this->svcBanner->crear(
            $this->negocioA,
            ['orden' => 0],
            $this->imagenFalsa('enorme.jpg', SvcBannerPromocional::PESO_MAXIMO_KB + 1)
        );

        $this->assertFalse($resultado);
        $this->assertSame(0, DB::table('banners_promocionales')->count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_el_endpoint_rechaza_un_tipo_de_archivo_no_permitido(): void
    {
        $this->withSession($this->sesionAdmin($this->negocioA))
            ->post('request/banner/crear', [
                'imagen' => UploadedFile::fake()->create('lista.pdf', 100, 'application/pdf'),
                'orden' => 0,
            ])
            ->assertJsonPath('error', 1);

        $this->assertSame(0, DB::table('banners_promocionales')->count());
    }

    public function test_el_endpoint_rechaza_una_imagen_que_excede_el_peso(): void
    {
        $this->withSession($this->sesionAdmin($this->negocioA))
            ->post('request/banner/crear', [
                'imagen' => $this->imagenFalsa('enorme.jpg', SvcBannerPromocional::PESO_MAXIMO_KB + 1),
                'orden' => 0,
            ])
            ->assertJsonPath('error', 1);

        $this->assertSame(0, DB::table('banners_promocionales')->count());
    }

    public function test_el_endpoint_exige_una_imagen_al_crear(): void
    {
        $this->withSession($this->sesionAdmin($this->negocioA))
            ->post('request/banner/crear', ['titulo' => 'Sin imagen'])
            ->assertJsonPath('error', 1);

        $this->assertSame(0, DB::table('banners_promocionales')->count());
    }

    /* ================= 3) EDICIÓN Y ARCHIVOS HUÉRFANOS ================= */

    public function test_editar_con_imagen_nueva_borra_la_anterior_del_disco(): void
    {
        $id = $this->crearBanner($this->negocioA);
        $rutaVieja = $this->rutaDe($id);

        Storage::disk('public')->assertExists($rutaVieja);

        $this->svcBanner->editar($id, $this->negocioA, ['titulo' => 'Renovado', 'orden' => 0, 'estado' => 1], $this->imagenFalsa('nueva.png'));

        $rutaNueva = $this->rutaDe($id);

        $this->assertNotSame($rutaVieja, $rutaNueva);
        Storage::disk('public')->assertExists($rutaNueva);
        Storage::disk('public')->assertMissing($rutaVieja);

        // Y no se acumulan huérfanos: queda exactamente un archivo.
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_editar_sin_imagen_conserva_la_que_ya_tenia(): void
    {
        $id = $this->crearBanner($this->negocioA, ['titulo' => 'Original']);
        $ruta = $this->rutaDe($id);

        $this->svcBanner->editar($id, $this->negocioA, ['titulo' => 'Solo cambia el texto', 'orden' => 3, 'estado' => 1]);

        $this->assertSame($ruta, $this->rutaDe($id));
        Storage::disk('public')->assertExists($ruta);

        $fila = DB::table('banners_promocionales')->where('id_banner', $id)->first();
        $this->assertSame('Solo cambia el texto', $fila->titulo);
        $this->assertSame(3, (int) $fila->orden);
    }

    /**
     * La baja es lógica: el archivo tiene que seguir ahí para que reactivar
     * devuelva un banner con imagen y no un hueco.
     */
    public function test_eliminar_no_borra_el_archivo_del_disco(): void
    {
        $id = $this->crearBanner($this->negocioA);
        $ruta = $this->rutaDe($id);

        $this->assertTrue($this->svcBanner->eliminar($id, $this->negocioA));

        $this->assertSame(0, (int) DB::table('banners_promocionales')->where('id_banner', $id)->value('estado'));
        Storage::disk('public')->assertExists($ruta);
    }

    /* ================= 4) AISLAMIENTO ENTRE NEGOCIOS ================= */

    public function test_el_listado_nunca_trae_banners_de_otro_negocio(): void
    {
        $this->crearBanner($this->negocioA, ['titulo' => 'Del A']);
        $this->crearBanner($this->negocioB, ['titulo' => 'Del B']);

        $banners = $this->svcBanner->listar($this->negocioA);

        $this->assertCount(1, $banners);
        $this->assertSame('Del A', $banners[0]['titulo']);
    }

    public function test_no_se_puede_editar_el_banner_de_otro_negocio(): void
    {
        $idDelB = $this->crearBanner($this->negocioB, ['titulo' => 'Del B']);

        $resultado = $this->svcBanner->editar($idDelB, $this->negocioA, ['titulo' => 'Secuestrado', 'orden' => 0, 'estado' => 1]);

        $this->assertFalse($resultado);
        $this->assertSame('Del B', DB::table('banners_promocionales')->where('id_banner', $idDelB)->value('titulo'));
    }

    public function test_no_se_puede_desactivar_el_banner_de_otro_negocio(): void
    {
        $idDelB = $this->crearBanner($this->negocioB);

        $this->withSession($this->sesionAdmin($this->negocioA))
            ->postJson('request/banner/eliminar', ['id_banner' => $idDelB])
            ->assertJsonPath('error', 1);

        $this->assertSame(1, (int) DB::table('banners_promocionales')->where('id_banner', $idDelB)->value('estado'));
    }

    public function test_el_endpoint_de_listar_solo_trae_los_del_negocio_de_la_sesion(): void
    {
        $this->crearBanner($this->negocioA, ['titulo' => 'Del A']);
        $this->crearBanner($this->negocioB, ['titulo' => 'Del B']);

        $banners = $this->withSession($this->sesionAdmin($this->negocioA))
            ->getJson('request/banner/listar')
            ->json('data.banners');

        $this->assertCount(1, $banners);
        $this->assertSame('Del A', $banners[0]['titulo']);
    }

    /* ================= 5) REACTIVACIÓN Y FILTRO DE INACTIVOS ================= */

    public function test_listar_no_muestra_inactivos_por_defecto_pero_si_al_pedirlos(): void
    {
        $activo = $this->crearBanner($this->negocioA, ['titulo' => 'Vigente']);
        $inactivo = $this->crearBanner($this->negocioA, ['titulo' => 'Retirado']);
        $this->svcBanner->eliminar($inactivo, $this->negocioA);

        $porDefecto = $this->svcBanner->listar($this->negocioA);
        $this->assertCount(1, $porDefecto);
        $this->assertSame($activo, $porDefecto[0]['id_banner']);

        $conInactivos = $this->svcBanner->listar($this->negocioA, true);
        $this->assertCount(2, $conInactivos);
    }

    public function test_el_endpoint_de_listar_respeta_incluir_inactivos(): void
    {
        $inactivo = $this->crearBanner($this->negocioA, ['titulo' => 'Retirado']);
        $this->svcBanner->eliminar($inactivo, $this->negocioA);

        $sesion = $this->sesionAdmin($this->negocioA);

        $this->assertCount(0, $this->withSession($sesion)->getJson('request/banner/listar')->json('data.banners'));
        $this->assertCount(1, $this->withSession($sesion)->getJson('request/banner/listar?incluir_inactivos=1')->json('data.banners'));
    }

    public function test_un_banner_se_puede_desactivar_y_volver_a_activar(): void
    {
        $id = $this->crearBanner($this->negocioA);
        $sesion = $this->sesionAdmin($this->negocioA);

        $this->withSession($sesion)->postJson('request/banner/eliminar', ['id_banner' => $id])->assertJsonPath('error', 0);
        $this->assertSame(0, (int) DB::table('banners_promocionales')->where('id_banner', $id)->value('estado'));

        // Reactivar es guardar con estado = 1, sin mandar imagen nueva.
        $this->withSession($sesion)->post('request/banner/editar', [
            'id_banner' => $id,
            'titulo' => 'De vuelta',
            'orden' => 0,
            'estado' => 1,
        ])->assertJsonPath('error', 0);

        $this->assertSame(1, (int) DB::table('banners_promocionales')->where('id_banner', $id)->value('estado'));
    }

    public function test_editar_sin_estado_es_rechazado(): void
    {
        $id = $this->crearBanner($this->negocioA);

        $this->withSession($this->sesionAdmin($this->negocioA))
            ->post('request/banner/editar', ['id_banner' => $id, 'titulo' => 'Sin estado', 'orden' => 0])
            ->assertJsonPath('error', 1);
    }

    /* ================= 6) VIGENCIA POR FECHAS ================= */

    /**
     * Las cuatro combinaciones de vigencia, sobre el mismo día de referencia.
     */
    public function test_listar_vigentes_respeta_las_cuatro_combinaciones_de_fechas(): void
    {
        $ayer = now()->subDay()->toDateString();
        $hoy = now()->toDateString();
        $manana = now()->addDay()->toDateString();
        $semanaPasada = now()->subWeek()->toDateString();
        $semanaQueViene = now()->addWeek()->toDateString();

        $sinFechas = $this->crearBanner($this->negocioA, ['titulo' => 'Sin fechas']);
        $soloInicioYaEmpezo = $this->crearBanner($this->negocioA, ['titulo' => 'Empezo ayer', 'fecha_inicio' => $ayer]);
        $soloInicioNoEmpezo = $this->crearBanner($this->negocioA, ['titulo' => 'Empieza manana', 'fecha_inicio' => $manana]);
        $soloFinVigente = $this->crearBanner($this->negocioA, ['titulo' => 'Termina manana', 'fecha_fin' => $manana]);
        $soloFinCaducado = $this->crearBanner($this->negocioA, ['titulo' => 'Termino ayer', 'fecha_fin' => $ayer]);
        $rangoIncluyeHoy = $this->crearBanner($this->negocioA, ['titulo' => 'Rango activo', 'fecha_inicio' => $ayer, 'fecha_fin' => $manana]);
        $rangoPasado = $this->crearBanner($this->negocioA, ['titulo' => 'Rango pasado', 'fecha_inicio' => $semanaPasada, 'fecha_fin' => $ayer]);
        $rangoFuturo = $this->crearBanner($this->negocioA, ['titulo' => 'Rango futuro', 'fecha_inicio' => $manana, 'fecha_fin' => $semanaQueViene]);
        // Los extremos cuentan como vigentes: empieza y termina hoy mismo.
        $soloHoy = $this->crearBanner($this->negocioA, ['titulo' => 'Solo hoy', 'fecha_inicio' => $hoy, 'fecha_fin' => $hoy]);

        $titulos = array_column($this->svcBanner->listarVigentes($this->negocioA), 'titulo');

        $this->assertContains('Sin fechas', $titulos, 'Sin fechas = siempre visible');
        $this->assertContains('Empezo ayer', $titulos);
        $this->assertContains('Termina manana', $titulos);
        $this->assertContains('Rango activo', $titulos);
        $this->assertContains('Solo hoy', $titulos, 'El día del extremo cuenta como vigente');

        $this->assertNotContains('Empieza manana', $titulos);
        $this->assertNotContains('Termino ayer', $titulos);
        $this->assertNotContains('Rango pasado', $titulos);
        $this->assertNotContains('Rango futuro', $titulos);

        $this->assertCount(5, $titulos);

        // Se usan para que PHPStan/lint no marquen las variables como no usadas
        // y, sobre todo, para dejar claro qué id es cada caso si algo falla.
        $this->assertNotEmpty([$sinFechas, $soloInicioYaEmpezo, $soloInicioNoEmpezo, $soloFinVigente,
            $soloFinCaducado, $rangoIncluyeHoy, $rangoPasado, $rangoFuturo, $soloHoy]);
    }

    public function test_un_banner_inactivo_nunca_esta_vigente_aunque_su_fecha_lo_permita(): void
    {
        $id = $this->crearBanner($this->negocioA, ['titulo' => 'Desactivado pero en fecha']);
        $this->svcBanner->eliminar($id, $this->negocioA);

        $this->assertCount(0, $this->svcBanner->listarVigentes($this->negocioA));
    }

    public function test_listar_vigentes_ordena_por_el_campo_orden(): void
    {
        $this->crearBanner($this->negocioA, ['titulo' => 'Tercero', 'orden' => 20]);
        $this->crearBanner($this->negocioA, ['titulo' => 'Primero', 'orden' => 1]);
        $this->crearBanner($this->negocioA, ['titulo' => 'Segundo', 'orden' => 10]);

        $titulos = array_column($this->svcBanner->listarVigentes($this->negocioA), 'titulo');

        $this->assertSame(['Primero', 'Segundo', 'Tercero'], $titulos);
    }

    public function test_listar_vigentes_nunca_trae_los_de_otro_negocio(): void
    {
        $this->crearBanner($this->negocioA, ['titulo' => 'Del A']);
        $this->crearBanner($this->negocioB, ['titulo' => 'Del B']);

        $titulos = array_column($this->svcBanner->listarVigentes($this->negocioA), 'titulo');

        $this->assertSame(['Del A'], $titulos);
    }
}
