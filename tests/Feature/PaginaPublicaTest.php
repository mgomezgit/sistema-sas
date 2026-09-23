<?php

namespace Tests\Feature;

use App\Service\SvcBannerPromocional;
use App\Service\SvcNegocio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Página pública de autogestión: lo que cualquier persona de internet puede ver.
 *
 * Dos cosas se vigilan aquí por encima de todo: que el negocio salga del slug y
 * de ningún otro sitio (no hay sesión que valga), y que las respuestas no
 * lleven ni un campo más de los que alguien decidió publicar a conciencia.
 */
class PaginaPublicaTest extends TestCase
{
    use RefreshDatabase;

    private int $negocioA;

    private int $negocioB;

    private SvcNegocio $svcNegocio;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->svcNegocio = new SvcNegocio;

        $this->negocioA = $this->crearNegocio('Spa Fashion', 'spa-fashion');
        $this->negocioB = $this->crearNegocio('Casa Canela', 'casa-canela');
    }

    private function crearBanner(int $tenantId, array $info = []): int
    {
        return (new SvcBannerPromocional)->crear(
            $tenantId,
            array_merge(['titulo' => 'Promo', 'orden' => 0, 'usuario_registra' => 'test'], $info),
            UploadedFile::fake()->image('promo.jpg', 1200, 500)->size(100)
        );
    }

    private function crearNegocio(string $nombre, ?string $slug, int $estado = 1): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'slug' => $slug,
            'rubro' => 'spa',
            'telefono_contacto' => '3001234567',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    private function crearRecurso(int $tenantId, string $nombre, int $estado = 1): int
    {
        return DB::table('recursos_reservables')->insertGetId([
            'tenant_id' => $tenantId,
            'categoria' => 'Masajes',
            'nombre' => $nombre,
            'descripcion' => 'Notas internas que NO deben salir a la web',
            'duracion_minutos' => 60,
            'precio' => 100000,
            'capacidad' => 2,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    private function crearEmpleado(int $tenantId, string $nombre, int $estado = 1): int
    {
        return DB::table('empleados')->insertGetId([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'telefono' => '3009998877',
            'email' => 'interno@test.local',
            'cargo' => 'Estilista',
            'porcentaje_comision' => 15,
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    /* ================= 1) GENERACIÓN DEL SLUG ================= */

    public function test_el_slug_normaliza_tildes_espacios_y_mayusculas(): void
    {
        $this->assertSame('bella-estetica-y-spa', $this->svcNegocio->generarSlug('Bellá Estética y Spa'));
        $this->assertSame('salon-nunez', $this->svcNegocio->generarSlug('  Salón  Núñez  '));
        $this->assertSame('casa-canela-2026', $this->svcNegocio->generarSlug('CASA CANELA 2026'));
        $this->assertSame('spa-fashion-belleza', $this->svcNegocio->generarSlug('Spa Fashion & Belleza'));
    }

    /**
     * Un nombre del que no queda nada utilizable no puede dar una URL rota.
     */
    public function test_un_nombre_sin_letras_cae_a_un_slug_de_respaldo(): void
    {
        $this->assertSame('negocio', $this->svcNegocio->generarSlug('###'));
    }

    public function test_dos_negocios_con_el_mismo_nombre_terminan_con_slugs_distintos(): void
    {
        // "Spa Fashion" ya existe con el slug "spa-fashion" (ver setUp).
        $segundo = $this->svcNegocio->generarSlug('Spa Fashion');
        $this->assertSame('spa-fashion-2', $segundo);

        $this->crearNegocio('Spa Fashion', $segundo);
        $this->assertSame('spa-fashion-3', $this->svcNegocio->generarSlug('Spa Fashion'));
    }

    public function test_al_editar_un_negocio_su_propio_slug_no_cuenta_como_choque(): void
    {
        $this->assertSame(
            'spa-fashion',
            $this->svcNegocio->generarSlug('Spa Fashion', $this->negocioA),
            'Regenerar el slug del mismo negocio no debe empujarlo a spa-fashion-2'
        );
    }

    /**
     * La unicidad del slug es GLOBAL, no por tenant: es una URL pública.
     */
    public function test_el_slug_no_puede_repetirse_entre_negocios_distintos(): void
    {
        $this->assertTrue($this->svcNegocio->slugOcupado('casa-canela'));
        $this->assertTrue($this->svcNegocio->slugOcupado('casa-canela', $this->negocioA));
        $this->assertFalse($this->svcNegocio->slugOcupado('casa-canela', $this->negocioB));
        $this->assertFalse($this->svcNegocio->slugOcupado('nadie-usa-esto'));
    }

    /**
     * La migración le da slug a los negocios que ya existían.
     *
     * Se ejecuta el archivo de migración de verdad: se deshacen sus columnas,
     * se dejan negocios "antiguos" sin slug y se corre su up() completo, que es
     * lo que pasaría en una base ya en producción.
     */
    public function test_la_migracion_rellena_el_slug_de_los_negocios_existentes(): void
    {
        $migracion = require database_path('migrations/2026_09_22_120000_agregar_pagina_publica_a_negocios.php');
        $migracion->down();

        DB::table('negocios')->delete();
        $conTildes = $this->crearNegocioSinSlug('Salón Núñez & Spa');
        $primerRepetido = $this->crearNegocioSinSlug('Casa Canela');
        $segundoRepetido = $this->crearNegocioSinSlug('Casa Canela');

        $migracion->up();

        $slugs = DB::table('negocios')->pluck('slug', 'id_negocio');

        $this->assertSame('salon-nunez-spa', $slugs[$conTildes]);
        $this->assertSame('casa-canela', $slugs[$primerRepetido]);
        $this->assertSame('casa-canela-2', $slugs[$segundoRepetido], 'El segundo homónimo necesita su sufijo');
        $this->assertCount(3, array_unique($slugs->all()), 'Ningún slug puede repetirse tras el relleno');
    }

    private function crearNegocioSinSlug(string $nombre): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    /* ================= 2) RESOLUCIÓN DEL NEGOCIO Y 404 ================= */

    public function test_un_slug_inexistente_devuelve_404_en_los_tres_endpoints(): void
    {
        $this->getJson('publico/no-existe-este-negocio/informacion')->assertNotFound();
        $this->getJson('publico/no-existe-este-negocio/servicios')->assertNotFound();
        $this->getJson('publico/no-existe-este-negocio/equipo')->assertNotFound();
        $this->getJson('publico/no-existe-este-negocio/banners')->assertNotFound();
        $this->get('reservar/no-existe-este-negocio')->assertNotFound();
    }

    /**
     * Un negocio dado de baja responde EXACTAMENTE igual que uno inexistente:
     * desde fuera no se puede deducir que existe pero está cerrado.
     */
    public function test_un_negocio_inactivo_responde_igual_que_uno_inexistente(): void
    {
        $this->crearNegocio('Negocio Cerrado', 'negocio-cerrado', 0);

        $cerrado = $this->getJson('publico/negocio-cerrado/informacion');
        $inexistente = $this->getJson('publico/jamas-existio/informacion');

        $cerrado->assertNotFound();
        $inexistente->assertNotFound();
        $this->assertSame($inexistente->getStatusCode(), $cerrado->getStatusCode());
    }

    public function test_la_pagina_publica_responde_con_un_slug_valido(): void
    {
        $this->get('reservar/spa-fashion')
            ->assertOk()
            ->assertSee('Spa Fashion');
    }

    /* ================= 3) AISLAMIENTO ENTRE NEGOCIOS ================= */

    public function test_los_servicios_publicos_nunca_incluyen_los_de_otro_negocio(): void
    {
        $this->crearRecurso($this->negocioA, 'Masaje del A');
        $this->crearRecurso($this->negocioB, 'Facial del B');

        $servicios = $this->getJson('publico/spa-fashion/servicios')->json('data.servicios');

        $this->assertCount(1, $servicios);
        $this->assertSame('Masaje del A', $servicios[0]['nombre']);
        $this->assertNotContains('Facial del B', array_column($servicios, 'nombre'));
    }

    public function test_el_equipo_publico_nunca_incluye_al_de_otro_negocio(): void
    {
        $this->crearEmpleado($this->negocioA, 'Empleada Del A');
        $this->crearEmpleado($this->negocioB, 'Empleado Del B');

        $equipo = $this->getJson('publico/spa-fashion/equipo')->json('data.equipo');

        $this->assertCount(1, $equipo);
        $this->assertSame('Empleada Del A', $equipo[0]['nombre']);
    }

    public function test_la_informacion_publica_es_la_del_negocio_del_slug(): void
    {
        DB::table('negocios')->where('id_negocio', $this->negocioB)->update([
            'nombre_negocio' => 'Casa Canela',
            'telefono_contacto' => '3110000000',
        ]);

        $negocio = $this->getJson('publico/casa-canela/informacion')->json('data.negocio');

        $this->assertSame('Casa Canela', $negocio['nombre_negocio']);
        $this->assertSame('3110000000', $negocio['telefono_contacto']);
    }

    /* ================= 4) LISTA BLANCA DE CAMPOS ================= */

    /**
     * Estas tres pruebas son la red de seguridad contra el descuido futuro: si
     * alguien agrega un campo a una respuesta pública sin pensarlo, fallan.
     * No comprueban que estén los campos esperados, sino que NO haya ninguno
     * más — que es lo que protege de filtrar una columna nueva sin querer.
     */
    public function test_los_servicios_publicos_solo_exponen_los_campos_de_la_lista_blanca(): void
    {
        $this->crearRecurso($this->negocioA, 'Masaje del A');

        $servicios = $this->getJson('publico/spa-fashion/servicios')->json('data.servicios');

        $permitidos = ['nombre', 'categoria', 'duracion_minutos', 'precio'];

        foreach ($servicios as $servicio) {
            $this->assertSame(
                $permitidos,
                array_keys($servicio),
                'Un servicio público expone campos fuera de la lista blanca: '
                .implode(', ', array_diff(array_keys($servicio), $permitidos))
            );
        }

        // Y explícitamente: nada de lo que vive en la fila pero es interno.
        $crudo = $this->getJson('publico/spa-fashion/servicios')->getContent();

        foreach (['tenant_id', 'id_recurso', 'descripcion', 'capacidad', 'usuario_registra', 'fecha_registro', 'estado'] as $prohibido) {
            $this->assertStringNotContainsString('"'.$prohibido.'"', $crudo);
        }

        $this->assertStringNotContainsString('Notas internas', $crudo);
    }

    public function test_el_equipo_publico_solo_expone_el_nombre(): void
    {
        $this->crearEmpleado($this->negocioA, 'Empleada Del A');

        $equipo = $this->getJson('publico/spa-fashion/equipo')->json('data.equipo');

        foreach ($equipo as $persona) {
            $this->assertSame(['nombre'], array_keys($persona), 'Del equipo solo puede salir el nombre');
        }

        $crudo = $this->getJson('publico/spa-fashion/equipo')->getContent();

        foreach (['telefono', 'email', 'cargo', 'porcentaje_comision', 'id_usuario', 'tenant_id', 'id_empleado'] as $prohibido) {
            $this->assertStringNotContainsString('"'.$prohibido.'"', $crudo);
        }

        $this->assertStringNotContainsString('interno@test.local', $crudo);
        $this->assertStringNotContainsString('3009998877', $crudo);
    }

    public function test_la_informacion_publica_solo_expone_los_campos_de_la_lista_blanca(): void
    {
        $negocio = $this->getJson('publico/spa-fashion/informacion')->json('data.negocio');

        $permitidos = [
            'nombre_negocio',
            'telefono_contacto',
            'whatsapp_numero',
            'dias_atencion',
            'hora_apertura',
            'hora_cierre',
            'politica_cancelacion',
            'modo_tema',
            'color_acento',
        ];

        $this->assertSame(
            $permitidos,
            array_keys($negocio),
            'La información pública expone campos fuera de la lista blanca: '
            .implode(', ', array_diff(array_keys($negocio), $permitidos))
        );

        $crudo = $this->getJson('publico/spa-fashion/informacion')->getContent();

        foreach (['id_negocio', 'tenant_id', 'rubro', 'usuario_registra', 'tour_completado', 'estado'] as $prohibido) {
            $this->assertStringNotContainsString('"'.$prohibido.'"', $crudo);
        }
    }

    /**
     * Misma disciplina para los banners: la vigencia y el estado deciden qué
     * sale, pero no pueden salir ellos. Desde fuera no se lee el calendario
     * de promociones de un negocio, solo la promoción que corre hoy.
     */
    public function test_los_banners_publicos_solo_exponen_los_campos_de_la_lista_blanca(): void
    {
        $this->crearBanner($this->negocioA, [
            'titulo' => 'Promo de apertura',
            'texto' => '20% en masajes',
            'fecha_inicio' => now()->subDay()->toDateString(),
            'fecha_fin' => now()->addDay()->toDateString(),
        ]);

        $banners = $this->getJson('publico/spa-fashion/banners')->json('data.banners');

        $permitidos = ['imagen_url', 'titulo', 'texto', 'orden'];

        $this->assertNotEmpty($banners);

        foreach ($banners as $banner) {
            $this->assertSame(
                $permitidos,
                array_keys($banner),
                'Un banner público expone campos fuera de la lista blanca: '
                .implode(', ', array_diff(array_keys($banner), $permitidos))
            );
        }

        $crudo = $this->getJson('publico/spa-fashion/banners')->getContent();

        foreach (['tenant_id', 'id_banner', 'imagen_path', 'fecha_inicio', 'fecha_fin', 'usuario_registra', 'fecha_registro', 'estado'] as $prohibido) {
            $this->assertStringNotContainsString('"'.$prohibido.'"', $crudo);
        }
    }

    public function test_los_banners_publicos_nunca_incluyen_los_de_otro_negocio(): void
    {
        $this->crearBanner($this->negocioA, ['titulo' => 'Promo del A']);
        $this->crearBanner($this->negocioB, ['titulo' => 'Promo del B']);

        $banners = $this->getJson('publico/spa-fashion/banners')->json('data.banners');

        $this->assertCount(1, $banners);
        $this->assertSame('Promo del A', $banners[0]['titulo']);
    }

    /**
     * Un banner caducado sigue existiendo y activo para el admin, pero la
     * página pública no lo muestra.
     */
    public function test_un_banner_fuera_de_vigencia_no_sale_en_la_pagina_publica(): void
    {
        $this->crearBanner($this->negocioA, ['titulo' => 'Vigente hoy']);
        $this->crearBanner($this->negocioA, [
            'titulo' => 'Caducado',
            'fecha_fin' => now()->subDay()->toDateString(),
        ]);

        $titulos = array_column($this->getJson('publico/spa-fashion/banners')->json('data.banners'), 'titulo');

        $this->assertContains('Vigente hoy', $titulos);
        $this->assertNotContains('Caducado', $titulos);
    }

    public function test_un_banner_inactivo_no_sale_en_la_pagina_publica(): void
    {
        $id = $this->crearBanner($this->negocioA, ['titulo' => 'Retirado']);
        (new SvcBannerPromocional)->eliminar($id, $this->negocioA);

        $this->assertCount(0, $this->getJson('publico/spa-fashion/banners')->json('data.banners'));
    }

    /* ================= 5) SOLO LO ACTIVO SE PUBLICA ================= */

    public function test_un_servicio_inactivo_no_aparece_en_la_pagina_publica(): void
    {
        $this->crearRecurso($this->negocioA, 'Servicio Vigente');
        $this->crearRecurso($this->negocioA, 'Servicio Retirado', 0);

        $nombres = array_column($this->getJson('publico/spa-fashion/servicios')->json('data.servicios'), 'nombre');

        $this->assertContains('Servicio Vigente', $nombres);
        $this->assertNotContains('Servicio Retirado', $nombres);
    }

    public function test_un_empleado_inactivo_no_aparece_en_la_pagina_publica(): void
    {
        $this->crearEmpleado($this->negocioA, 'Sigue Trabajando');
        $this->crearEmpleado($this->negocioA, 'Ya No Trabaja', 0);

        $nombres = array_column($this->getJson('publico/spa-fashion/equipo')->json('data.equipo'), 'nombre');

        $this->assertContains('Sigue Trabajando', $nombres);
        $this->assertNotContains('Ya No Trabaja', $nombres);
    }

    /* ================= 6) THROTTLE ================= */

    /**
     * El límite del grupo público se comprueba gastándolo de verdad, no
     * mirando si el middleware está declarado en las rutas.
     */
    public function test_el_grupo_publico_responde_429_al_exceder_el_limite(): void
    {
        $ultimaRespuesta = null;

        // El grupo admite 60 por minuto; la petición 61 tiene que rebotar.
        for ($intento = 1; $intento <= 61; $intento++) {
            $ultimaRespuesta = $this->getJson('publico/spa-fashion/informacion');

            if ($intento <= 60) {
                $ultimaRespuesta->assertOk();
            }
        }

        $ultimaRespuesta->assertStatus(429);
    }

    public function test_el_limite_tambien_cubre_la_pagina_publica(): void
    {
        $ultimaRespuesta = null;

        for ($intento = 1; $intento <= 61; $intento++) {
            $ultimaRespuesta = $this->get('reservar/spa-fashion');
        }

        $ultimaRespuesta->assertStatus(429);
    }

    /* ================= 7) EL SLUG EN LA CONFIGURACIÓN ================= */

    private function slugDe(int $idNegocio): ?string
    {
        return DB::table('negocios')->where('id_negocio', $idNegocio)->value('slug');
    }

    /**
     * La garantía central: un slug existente NO se regenera solo, JAMÁS.
     *
     * Da igual cuántas veces cambie el nombre del negocio. El slug es una
     * dirección pública que ya se repartió en enlaces y códigos QR; que se
     * moviera sola al corregir una errata del nombre dejaría esos enlaces en
     * 404 sin que nadie lo pidiera.
     */
    public function test_renombrar_el_negocio_nunca_cambia_el_slug(): void
    {
        $slugInicial = $this->slugDe($this->negocioA);
        $this->assertSame('spa-fashion', $slugInicial);

        foreach (['Spa Fashion Premium', 'Centro de Belleza Aurora', 'Otro Nombre Cualquiera'] as $nombreNuevo) {
            $this->svcNegocio->actualizarConfiguracion($this->negocioA, [
                'nombre_negocio' => $nombreNuevo,
            ]);

            $this->assertSame(
                $slugInicial,
                $this->slugDe($this->negocioA),
                'Renombrar a "'.$nombreNuevo.'" movió el slug, y no debe moverse nunca solo'
            );
        }

        // El nombre sí cambió: lo que no se movió fue la dirección pública.
        $this->assertSame(
            'Otro Nombre Cualquiera',
            DB::table('negocios')->where('id_negocio', $this->negocioA)->value('nombre_negocio')
        );
    }

    /**
     * Lo mismo vale para un slug que el admin escribió él: tampoco lo arrastra
     * un cambio de nombre posterior.
     */
    public function test_un_slug_escrito_a_mano_tampoco_lo_mueve_un_cambio_de_nombre(): void
    {
        $this->svcNegocio->actualizarConfiguracion($this->negocioA, [
            'nombre_negocio' => 'Spa Fashion',
            'slug' => 'mi-spa-favorito',
        ]);

        $this->assertSame('mi-spa-favorito', $this->slugDe($this->negocioA));

        $this->svcNegocio->actualizarConfiguracion($this->negocioA, [
            'nombre_negocio' => 'Spa Fashion Renovado',
        ]);

        $this->assertSame('mi-spa-favorito', $this->slugDe($this->negocioA));
    }

    /**
     * La única puerta por la que el slug cambia: que el admin lo escriba.
     */
    public function test_el_slug_solo_cambia_cuando_el_admin_lo_escribe(): void
    {
        $this->svcNegocio->actualizarConfiguracion($this->negocioA, [
            'nombre_negocio' => 'Spa Fashion',
            'telefono_contacto' => '3009998877',
        ]);
        $this->assertSame('spa-fashion', $this->slugDe($this->negocioA));

        $this->svcNegocio->actualizarConfiguracion($this->negocioA, [
            'nombre_negocio' => 'Spa Fashion',
            'slug' => 'spa-fashion-centro',
        ]);
        $this->assertSame('spa-fashion-centro', $this->slugDe($this->negocioA));
    }

    /**
     * Un slug vacío en el formulario no es "bórralo": es "no lo estoy tocando".
     * Dejarlo en blanco no puede dejar al negocio sin dirección pública.
     */
    public function test_enviar_el_slug_vacio_no_borra_el_que_ya_tenia(): void
    {
        $this->svcNegocio->actualizarConfiguracion($this->negocioA, [
            'nombre_negocio' => 'Spa Fashion',
            'slug' => '',
        ]);

        $this->assertSame('spa-fashion', $this->slugDe($this->negocioA));

        // Y un valor que al normalizarse no deja nada tampoco lo borra.
        $this->svcNegocio->actualizarConfiguracion($this->negocioA, [
            'nombre_negocio' => 'Spa Fashion',
            'slug' => '###',
        ]);

        $this->assertSame('spa-fashion', $this->slugDe($this->negocioA));
    }

    public function test_no_se_puede_tomar_el_slug_de_otro_negocio(): void
    {
        $resultado = $this->svcNegocio->actualizarConfiguracion($this->negocioA, [
            'nombre_negocio' => 'Spa Fashion',
            'slug' => 'casa-canela',
        ]);

        $this->assertFalse($resultado);
        $this->assertSame('spa-fashion', DB::table('negocios')->where('id_negocio', $this->negocioA)->value('slug'));
        $this->assertSame('casa-canela', DB::table('negocios')->where('id_negocio', $this->negocioB)->value('slug'));
    }

    public function test_el_slug_escrito_a_mano_se_normaliza_antes_de_guardarse(): void
    {
        $this->svcNegocio->actualizarConfiguracion($this->negocioA, [
            'nombre_negocio' => 'Spa Fashion',
            'slug' => '  Mi Salón FAVORITO  ',
        ]);

        $this->assertSame('mi-salon-favorito', DB::table('negocios')->where('id_negocio', $this->negocioA)->value('slug'));
    }

    public function test_la_columna_slug_es_unica_en_la_base(): void
    {
        $this->assertTrue(Schema::hasColumn('negocios', 'slug'));
        $this->assertTrue(Schema::hasColumn('negocios', 'whatsapp_numero'));
        $this->assertTrue(Schema::hasColumn('negocios', 'politica_cancelacion'));

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('negocios')->insertGetId([
            'nombre_negocio' => 'Intruso',
            'slug' => 'spa-fashion',
            'rubro' => 'spa',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }
}
