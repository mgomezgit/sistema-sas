<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Ayudante de pruebas para activar módulos de pago en negocios de prueba.
 *
 * Los negocios nacen SIN módulos, así que toda prueba que ejercite un módulo
 * de pago (reportes, inventario, comisiones, ia...) debe activarlo primero.
 * Inserta directo en negocio_modulos para poder fijar vigencias en el pasado
 * (módulo vencido) sin pelear con las validaciones de SvcModulo::activarModulo.
 */
trait ActivaModulos
{
    /**
     * Activa un módulo para un negocio. $vigenteHasta en formato Y-m-d (o null
     * = sin vencimiento); puede ser una fecha pasada para simular un módulo
     * vencido. Idempotente: vuelve a llamarlo no duplica la fila.
     */
    protected function activarModulo(int $tenantId, string $clave, ?string $vigenteHasta = null): void
    {
        $idModulo = DB::table('modulos_plataforma')->where('clave', $clave)->value('id_modulo');

        if ($idModulo === null) {
            throw new \RuntimeException("El modulo '{$clave}' no existe en el catalogo. ¿Falta correr su migracion?");
        }

        DB::table('negocio_modulos')->updateOrInsert(
            ['tenant_id' => $tenantId, 'id_modulo' => $idModulo],
            [
                'activo' => true,
                'vigente_hasta' => $vigenteHasta,
                'origen' => 'cortesia',
                'fecha_activacion' => now(),
                'fecha_desactivacion' => null,
                'usuario_registra' => 'test',
                'fecha_registro' => now(),
            ]
        );
    }

    /**
     * Desactiva un módulo para un negocio (deja la fila, apaga 'activo'),
     * igual que haría el super admin. No borra datos del módulo.
     */
    protected function desactivarModulo(int $tenantId, string $clave): void
    {
        $idModulo = DB::table('modulos_plataforma')->where('clave', $clave)->value('id_modulo');

        if ($idModulo === null) {
            return;
        }

        DB::table('negocio_modulos')
            ->where('tenant_id', $tenantId)
            ->where('id_modulo', $idModulo)
            ->update(['activo' => false, 'fecha_desactivacion' => now()]);
    }
}
