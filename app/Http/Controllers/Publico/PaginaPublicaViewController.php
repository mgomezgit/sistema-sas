<?php

namespace App\Http\Controllers\Publico;

use App\Models\Negocio;

/**
 * La página que ve el cliente final. Sin sesión: el negocio sale del slug.
 *
 * De momento solo resuelve el negocio y monta el armazón; el flujo de agendar
 * se construye aparte. Todo lo que se pinte aquí sale de los endpoints de
 * PublicoController, que son los que deciden qué es público y qué no.
 */
class PaginaPublicaViewController
{
    public function mostrar(string $slug)
    {
        $negocio = Negocio::where('slug', $slug)
            ->where('estado', 1)
            ->first();

        // Mismo 404 que cualquier otra dirección inexistente del sitio.
        if ($negocio === null) {
            abort(404);
        }

        return view('publico.pagina', [
            'slug' => $negocio->slug,
            'nombreNegocio' => $negocio->nombre_negocio,
        ]);
    }
}
