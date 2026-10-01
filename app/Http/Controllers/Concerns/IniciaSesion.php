<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Middleware\VerificarSesion;
use App\Models\Empleado;
use App\Models\Negocio;

/**
 * Único punto que abre una sesión autenticada. Lo usan el login normal
 * (AutenticacionController) y la confirmación del registro público
 * (RegistroPublicoController), para que entrar por cualquiera de los dos
 * deje exactamente la misma sesión.
 *
 * Quien lo llama ya verificó la identidad (clave correcta, o token de
 * confirmación válido): aquí no se vuelve a comprobar nada.
 */
trait IniciaSesion
{
    /**
     * @param  array  $usuario  Fila de usuarios con id_usuario, usuario, nombre,
     *                          email, tenant_id e id_rol (la de
     *                          SvcUsuario::getUsuarioByEmail()).
     */
    protected function iniciarSesionDeUsuario(array $usuario): void
    {
        // Si la cuenta corresponde a un empleado, se guarda su id en la sesión
        // para poder filtrar "sus" citas. Un admin o super admin queda en null.
        $idEmpleado = Empleado::where('id_usuario', $usuario['id_usuario'])
            ->where('estado', 1)
            ->value('id_empleado');

        // ID de sesión nuevo al autenticarse: un ID que alguien haya plantado
        // en el navegador antes del login (fijación de sesión) deja de servir
        // justo cuando empieza a valer algo.
        session()->regenerate();

        session([
            'id_usuario' => $usuario['id_usuario'],
            'usuario' => $usuario['usuario'],
            'nombre_usuario' => $usuario['nombre'],
            'email' => $usuario['email'],
            'tenant_id' => $usuario['tenant_id'],
            'id_rol' => $usuario['id_rol'],
            'id_empleado' => $idEmpleado ?: null,
            // Si la versión en usuarios cambia (cambio de clave), VerificarSesion
            // corta esta sesión en la siguiente petición.
            'version_sesion' => (int) ($usuario['version_sesion'] ?? 0),
            'app_sesion' => VerificarSesion::CLAVE_SESION,
        ]);

        // El rubro del negocio define el tema visual del backoffice, y su
        // nombre se muestra en el sidebar. El super admin no pertenece a
        // ningún negocio, así que ambos quedan en null.
        if ($usuario['tenant_id'] !== null) {
            $negocio = Negocio::where('id_negocio', $usuario['tenant_id'])
                ->select('rubro', 'nombre_negocio', 'modo_tema', 'color_acento')
                ->first();

            session([
                'rubro_negocio' => $negocio->rubro ?? null,
                'nombre_negocio_sesion' => $negocio->nombre_negocio ?? null,
                'modo_tema' => $negocio->modo_tema ?? 'claro',
                'color_acento' => $negocio->color_acento ?? 'oro_rosa',
            ]);
        } else {
            // La plataforma tiene su propio tema fijo, no personalizable.
            session([
                'rubro_negocio' => null,
                'nombre_negocio_sesion' => null,
                'modo_tema' => 'oscuro',
                'color_acento' => 'rojo',
            ]);
        }
    }
}
