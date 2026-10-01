<?php

namespace App\Http\Controllers\Publico;

use App\Service\SvcPaginaPublica;

/**
 * La página que ve el cliente final. Sin sesión: el negocio sale del slug.
 *
 * Se arma completa en el servidor (nombre, horario, servicios, equipo,
 * banners) a través de SvcPaginaPublica, el mismo Service que usa
 * PublicoController para sus endpoints JSON: una sola consulta, un solo
 * filtro de tenant y una sola lista blanca de campos para toda esta zona.
 */
class PaginaPublicaViewController
{
    public function __construct(private SvcPaginaPublica $svcPaginaPublica)
    {
    }

    public function mostrar(string $slug)
    {
        $negocio = $this->svcPaginaPublica->resolverPorSlug($slug);

        // Mismo 404 que cualquier otra dirección inexistente del sitio.
        if ($negocio === null) {
            abort(404);
        }

        return view('publico.pagina', [
            'slug' => $negocio->slug,
            'negocio' => $this->svcPaginaPublica->informacion($negocio),
            'servicios' => $this->svcPaginaPublica->servicios($negocio->id_negocio),
            'equipo' => $this->svcPaginaPublica->equipo($negocio->id_negocio),
            'banners' => $this->svcPaginaPublica->banners($negocio->id_negocio),
        ]);
    }
}
