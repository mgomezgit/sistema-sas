<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Service\SvcBannerPromocional;
use Illuminate\Http\JsonResponse;

class BannerPromocionalController extends Controller
{
    private SvcBannerPromocional $svcBanner;

    public function __construct()
    {
        parent::__construct();

        $this->svcBanner = new SvcBannerPromocional;
    }

    /**
     * Los banners son de un negocio concreto: sin tenant no hay dónde ponerlos.
     * El super admin no administra la página pública de nadie.
     */
    private function tenantOError()
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los banners se administran desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return null;
        }

        return $tenantId;
    }

    /**
     * Reglas del archivo de imagen. Van tanto aquí como en el Service: el
     * Controller da el mensaje de error entendible, y el Service se niega a
     * escribir en disco aunque lo llame otro código que no pase por aquí.
     */
    private function reglasDeImagen(bool $obligatoria): string
    {
        return ($obligatoria ? 'required' : 'sometimes')
            .'|file|mimes:jpg,jpeg,png,webp|max:'.SvcBannerPromocional::PESO_MAXIMO_KB;
    }

    private function reglasComunes(): array
    {
        return [
            'titulo' => 'nullable|max:150',
            'texto' => 'nullable|max:300',
            // Las dos fechas son opcionales: un banner permanente no tiene por
            // qué inventarse un rango.
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'orden' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Un campo vacío que llega desde FormData viaja como cadena vacía, no como
     * null: se normaliza para que la columna quede en NULL y no en "".
     */
    private function opcional($valor)
    {
        return (isset($valor) && $valor !== '') ? $valor : null;
    }

    public function crear(): JsonResponse
    {
        $tenantId = $this->tenantOError();

        if ($tenantId === null) {
            return $this->sendResponse();
        }

        $this->setRequestValidationRules(array_merge(
            ['imagen' => $this->reglasDeImagen(true)],
            $this->reglasComunes()
        ));

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $idBanner = $this->svcBanner->crear($tenantId, [
            'titulo' => $this->opcional($datos['titulo'] ?? null),
            'texto' => $this->opcional($datos['texto'] ?? null),
            'fecha_inicio' => $this->opcional($datos['fecha_inicio'] ?? null),
            'fecha_fin' => $this->opcional($datos['fecha_fin'] ?? null),
            'orden' => (int) ($datos['orden'] ?? 0),
            'usuario_registra' => session('nombre_usuario'),
        ], $this->request->file('imagen'));

        if ($idBanner === false) {
            $this->agregarErrorSistema('BAN-CREAR');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($idBanner, 'id_banner');

        return $this->sendResponse();
    }

    public function editar(): JsonResponse
    {
        $tenantId = $this->tenantOError();

        if ($tenantId === null) {
            return $this->sendResponse();
        }

        $this->setRequestValidationRules(array_merge(
            [
                'id_banner' => 'required',
                // Al editar la imagen es opcional: sin archivo nuevo se
                // conserva la que ya tenía.
                'imagen' => $this->reglasDeImagen(false),
                'estado' => 'required|in:0,1',
            ],
            $this->reglasComunes()
        ));

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $resultado = $this->svcBanner->editar($datos['id_banner'], $tenantId, [
            'titulo' => $this->opcional($datos['titulo'] ?? null),
            'texto' => $this->opcional($datos['texto'] ?? null),
            'fecha_inicio' => $this->opcional($datos['fecha_inicio'] ?? null),
            'fecha_fin' => $this->opcional($datos['fecha_fin'] ?? null),
            'orden' => (int) ($datos['orden'] ?? 0),
            'estado' => (int) $datos['estado'],
        ], $this->request->file('imagen'));

        if (! $resultado) {
            $this->agregarErrorNoDisponible('el banner', 'BAN-EDIT');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function eliminar(): JsonResponse
    {
        $tenantId = $this->tenantOError();

        if ($tenantId === null) {
            return $this->sendResponse();
        }

        $this->setRequestValidationRules(['id_banner' => 'required']);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        if (! $this->svcBanner->eliminar($datos['id_banner'], $tenantId)) {
            $this->agregarErrorNoDisponible('el banner', 'BAN-ELIM');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function listar(): JsonResponse
    {
        $tenantId = $this->tenantOError();

        if ($tenantId === null) {
            return $this->sendResponse();
        }

        // "incluir_inactivos=1" es lo que activa el filtro de la pestaña. Sin
        // él, un banner dado de baja no aparece.
        $incluirInactivos = (bool) $this->request->query('incluir_inactivos');

        $this->respSinError();
        $this->setDataResponse($this->svcBanner->listar($tenantId, $incluirInactivos), 'banners');

        return $this->sendResponse();
    }
}
