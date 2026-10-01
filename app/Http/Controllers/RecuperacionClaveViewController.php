<?php

namespace App\Http\Controllers;

/**
 * Pantalla pública de "Olvidé mi contraseña". Acepta dos parámetros para
 * llegar ya ubicado desde un correo:
 * - correo: precarga el campo (lo escapa Blade; nunca se confía en él).
 * - paso=codigo: abre directo el paso de escribir el código.
 */
class RecuperacionClaveViewController
{
    public function mostrar()
    {
        $templateView = [
            'correo' => (string) request()->query('correo', ''),
            'abrirPasoCodigo' => request()->query('paso') === 'codigo',
        ];

        return view('recuperar-clave', $templateView);
    }
}
