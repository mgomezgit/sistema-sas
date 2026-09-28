<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarSesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Edición desde el backoffice de los dos campos nuevos que la página pública
 * ya sabía leer: whatsapp_numero y politica_cancelacion.
 *
 * Todo se ejerce por los endpoints reales con withSession(); el aislamiento
 * multi-tenant se verifica por HTTP, no en el Service directo, para que la
 * prueba recorra el mismo camino que un cliente real.
 *
 * ================= PRUEBA DE MUTACIÓN DEL TENANT_ID =================
 *
 * test_no_se_puede_editar_otro_negocio_enviando_su_tenant_id_en_el_cuerpo() se
 * verificó rompiendo el código a propósito. Procedimiento ejecutado:
 *
 *   1. En app/Http/Controllers/Request/NegocioController.php, línea del update:
 *          $this->svcNegocio->actualizarConfiguracion(session('tenant_id'), $info);
 *      Se cambió a:
 *          $this->svcNegocio->actualizarConfiguracion($datos['tenant_id'] ?? session('tenant_id'), $info);
 *      Es decir: el controller pasa a confiar en un tenant_id enviado en el
 *      cuerpo si el atacante lo incluye, en vez de ignorarlo como debe.
 *   2. Se ejecutó: php artisan test --filter=ConfiguracionNegocioTest
 *      Resultado: 12 tests, 11 passed, 1 FAILED:
 *        - test_no_se_puede_editar_otro_negocio_enviando_su_tenant_id_en_el_cuerpo:
 *          "El cambio debe aplicarse al negocio de la sesión (A)
 *           Failed asserting that null is identical to '573009999999'."
 *      Con el filtro roto, el update fue al negocio B (el que venía en el
 *      cuerpo) y el A quedó sin actualizar — exactamente el desvío que esta
 *      prueba debe custodiar.
 *   3. Se restauró la llamada tal cual estaba.
 *   4. Se volvió a ejecutar: 13 passed.
 *
 * La prueba falla cuando el tenant_id de la sesión deja de ser la única
 * fuente de verdad, que es lo que debe custodiar.
 */
class ConfiguracionNegocioTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private int $otroTenantId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre_rol' => 'admin', 'estado' => 1],
            ['id_rol' => 2, 'nombre_rol' => 'empleado', 'estado' => 1],
            ['id_rol' => 3, 'nombre_rol' => 'super_admin', 'estado' => 1],
        ]);

        $this->tenantId = $this->crearNegocio('Spa de Pruebas', 'spa-pruebas');
        $this->otroTenantId = $this->crearNegocio('Negocio Ajeno', 'negocio-ajeno');
    }

    private function crearNegocio(string $nombre, string $slug): int
    {
        return DB::table('negocios')->insertGetId([
            'nombre_negocio' => $nombre,
            'slug' => $slug,
            'rubro' => 'spa',
            'dias_atencion' => '1,2,3,4,5',
            'hora_apertura' => '08:00:00',
            'hora_cierre' => '18:00:00',
            'usuario_registra' => 'test',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ]);
    }

    private function sesionAdmin(?int $tenantId = null): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 1,
            'usuario' => 'admin.test',
            'nombre_usuario' => 'Admin Test',
            'tenant_id' => $tenantId ?? $this->tenantId,
            'id_rol' => 1,
        ];
    }

    private function sesionEmpleado(): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 2,
            'usuario' => 'empleado.test',
            'nombre_usuario' => 'Empleado Test',
            'tenant_id' => $this->tenantId,
            'id_rol' => 2,
        ];
    }

    private function sesionSuperAdmin(): array
    {
        return [
            'app_sesion' => VerificarSesion::CLAVE_SESION,
            'id_usuario' => 3,
            'usuario' => 'superadmin.test',
            'nombre_usuario' => 'Super Admin',
            'tenant_id' => null,
            'id_rol' => 3,
        ];
    }

    private function datosCompletos(array $sobreescribir = []): array
    {
        return array_merge([
            'nombre_negocio' => 'Spa de Pruebas',
            'telefono_contacto' => '3000000000',
            'dias_atencion' => ['1', '2', '3', '4', '5'],
            'hora_apertura' => '08:00',
            'hora_cierre' => '18:00',
        ], $sobreescribir);
    }

    /* ================= 1) GUARDAR Y LEER ================= */

    public function test_guardar_y_leer_los_dos_campos_nuevos(): void
    {
        $respuestaGuardar = $this->withSession($this->sesionAdmin())->postJson(
            'request/negocio/actualizar-configuracion',
            $this->datosCompletos([
                'whatsapp_numero' => '573001234567',
                'politica_cancelacion' => 'Cancelaciones con 24 horas de anticipación.',
            ])
        );
        $this->assertSame(0, $respuestaGuardar->json('error'), $respuestaGuardar->json('mensaje'));

        $respuestaLeer = $this->withSession($this->sesionAdmin())->getJson('request/negocio/configuracion');

        $this->assertSame('573001234567', $respuestaLeer->json('data.negocio.whatsapp_numero'));
        $this->assertSame('Cancelaciones con 24 horas de anticipación.', $respuestaLeer->json('data.negocio.politica_cancelacion'));
    }

    public function test_whatsapp_con_signos_y_espacios_queda_solo_como_digitos(): void
    {
        $respuesta = $this->withSession($this->sesionAdmin())->postJson(
            'request/negocio/actualizar-configuracion',
            $this->datosCompletos(['whatsapp_numero' => '+57 300 123 4567'])
        );
        $this->assertSame(0, $respuesta->json('error'));

        $this->assertSame(
            '573001234567',
            DB::table('negocios')->where('id_negocio', $this->tenantId)->value('whatsapp_numero')
        );
    }

    /* ================= 2) VALIDACIÓN ================= */

    public function test_whatsapp_con_menos_de_8_digitos_es_rechazado(): void
    {
        $respuesta = $this->withSession($this->sesionAdmin())->postJson(
            'request/negocio/actualizar-configuracion',
            $this->datosCompletos(['whatsapp_numero' => '1234567'])
        );

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertStringContainsString('entre 8 y 15 dígitos', $respuesta->json('mensaje'));
    }

    public function test_whatsapp_con_mas_de_15_digitos_es_rechazado(): void
    {
        $respuesta = $this->withSession($this->sesionAdmin())->postJson(
            'request/negocio/actualizar-configuracion',
            $this->datosCompletos(['whatsapp_numero' => '1234567890123456'])
        );

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertStringContainsString('entre 8 y 15 dígitos', $respuesta->json('mensaje'));
    }

    public function test_politica_de_mas_de_1000_caracteres_es_rechazada(): void
    {
        $respuesta = $this->withSession($this->sesionAdmin())->postJson(
            'request/negocio/actualizar-configuracion',
            $this->datosCompletos(['politica_cancelacion' => str_repeat('a', 1001)])
        );

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertStringContainsString('1000 caracteres', $respuesta->json('mensaje'));
    }

    public function test_politica_exacta_de_1000_caracteres_pasa(): void
    {
        $texto = str_repeat('a', 1000);
        $respuesta = $this->withSession($this->sesionAdmin())->postJson(
            'request/negocio/actualizar-configuracion',
            $this->datosCompletos(['politica_cancelacion' => $texto])
        );

        $this->assertSame(0, $respuesta->json('error'), $respuesta->json('mensaje'));
        $this->assertSame($texto, DB::table('negocios')->where('id_negocio', $this->tenantId)->value('politica_cancelacion'));
    }

    /* ================= 3) VACÍO BORRA ================= */

    public function test_whatsapp_vacio_borra_el_valor_existente(): void
    {
        DB::table('negocios')->where('id_negocio', $this->tenantId)->update(['whatsapp_numero' => '573001234567']);

        $respuesta = $this->withSession($this->sesionAdmin())->postJson(
            'request/negocio/actualizar-configuracion',
            $this->datosCompletos(['whatsapp_numero' => ''])
        );

        $this->assertSame(0, $respuesta->json('error'));
        $this->assertNull(DB::table('negocios')->where('id_negocio', $this->tenantId)->value('whatsapp_numero'));
    }

    public function test_politica_vacia_borra_el_valor_existente(): void
    {
        DB::table('negocios')->where('id_negocio', $this->tenantId)->update(['politica_cancelacion' => 'texto existente']);

        $respuesta = $this->withSession($this->sesionAdmin())->postJson(
            'request/negocio/actualizar-configuracion',
            $this->datosCompletos(['politica_cancelacion' => ''])
        );

        $this->assertSame(0, $respuesta->json('error'));
        $this->assertNull(DB::table('negocios')->where('id_negocio', $this->tenantId)->value('politica_cancelacion'));
    }

    /* ================= 4) AISLAMIENTO MULTI-TENANT ================= */

    /** Ver la nota de MUTACIÓN en el encabezado de la clase. */
    public function test_no_se_puede_editar_otro_negocio_enviando_su_tenant_id_en_el_cuerpo(): void
    {
        // El negocio B tiene un WhatsApp ya cargado que no queremos que cambie.
        DB::table('negocios')->where('id_negocio', $this->otroTenantId)->update([
            'whatsapp_numero' => '573001234567',
            'nombre_negocio' => 'Negocio Ajeno',
        ]);

        // El admin de A hace el payload de guardado normal, pero AÑADE en el
        // cuerpo el tenant_id (y por si acaso también id_negocio) del negocio
        // B: intenta apuntar la escritura al otro negocio.
        $respuesta = $this->withSession($this->sesionAdmin($this->tenantId))->postJson(
            'request/negocio/actualizar-configuracion',
            $this->datosCompletos([
                'tenant_id' => $this->otroTenantId,
                'id_negocio' => $this->otroTenantId,
                'whatsapp_numero' => '573009999999',
                'politica_cancelacion' => 'Texto intruso',
            ])
        );

        // El guardado responde OK, pero se aplica al negocio DE LA SESIÓN,
        // nunca al que venga en el cuerpo.
        $this->assertSame(0, $respuesta->json('error'));

        $negocioA = DB::table('negocios')->where('id_negocio', $this->tenantId)->first();
        $negocioB = DB::table('negocios')->where('id_negocio', $this->otroTenantId)->first();

        $this->assertSame('573009999999', $negocioA->whatsapp_numero, 'El cambio debe aplicarse al negocio de la sesión (A)');

        $this->assertSame('573001234567', $negocioB->whatsapp_numero, 'El WhatsApp del negocio B no puede haber cambiado');
        $this->assertNull($negocioB->politica_cancelacion, 'La política de B no puede haberse escrito');
        $this->assertSame('Negocio Ajeno', $negocioB->nombre_negocio, 'El nombre de B tampoco puede haberse tocado');
    }

    /* ================= 5) ROLES ================= */

    public function test_empleado_no_puede_guardar_estos_campos(): void
    {
        $respuesta = $this->withSession($this->sesionEmpleado())->postJson(
            'request/negocio/actualizar-configuracion',
            $this->datosCompletos(['whatsapp_numero' => '573001234567'])
        );

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertNull(DB::table('negocios')->where('id_negocio', $this->tenantId)->value('whatsapp_numero'));
    }

    /**
     * El super admin no pertenece a ningún negocio: el endpoint rechaza el
     * cambio por eso mismo (no hay tenant_id de sesión al que aplicarlo).
     * Se documenta acá porque es una decisión explícita, no un olvido:
     * cambios a estos datos entran por la cuenta del admin del negocio, no
     * por el super admin.
     */
    public function test_super_admin_no_puede_guardar_estos_campos_por_este_endpoint(): void
    {
        $respuesta = $this->withSession($this->sesionSuperAdmin())->postJson(
            'request/negocio/actualizar-configuracion',
            $this->datosCompletos([
                'tenant_id' => $this->tenantId,
                'whatsapp_numero' => '573001234567',
            ])
        );

        $this->assertSame(1, $respuesta->json('error'));
        $this->assertNull(DB::table('negocios')->where('id_negocio', $this->tenantId)->value('whatsapp_numero'));
    }

    /* ================= 6) ENDPOINT PÚBLICO SIGUE DEVOLVIENDO LISTA BLANCA ================= */

    public function test_el_endpoint_publico_incluye_los_dos_campos_nuevos(): void
    {
        $this->withSession($this->sesionAdmin())->postJson(
            'request/negocio/actualizar-configuracion',
            $this->datosCompletos([
                'whatsapp_numero' => '573001234567',
                'politica_cancelacion' => 'Cancelaciones con 24 horas.',
            ])
        );

        $respuesta = $this->getJson('publico/spa-pruebas/informacion');

        $this->assertSame(0, $respuesta->json('error'));

        $negocio = $respuesta->json('data.negocio');
        $this->assertSame('573001234567', $negocio['whatsapp_numero']);
        $this->assertSame('Cancelaciones con 24 horas.', $negocio['politica_cancelacion']);

        // La lista blanca sigue siendo estricta: solo los campos permitidos.
        // Cualquier fuga (id_negocio, rubro, etc.) rompe esta prueba.
        $permitidos = [
            'nombre_negocio', 'telefono_contacto', 'whatsapp_numero',
            'dias_atencion', 'hora_apertura', 'hora_cierre',
            'politica_cancelacion', 'modo_tema', 'color_acento',
        ];
        sort($permitidos);
        $clavesDevueltas = array_keys($negocio);
        sort($clavesDevueltas);
        $this->assertSame($permitidos, $clavesDevueltas, 'La lista blanca no puede exponer campos extra del modelo');
    }
}
