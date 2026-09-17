<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Service\SvcProducto;
use Illuminate\Http\JsonResponse;

/**
 * Inventario de productos.
 *
 * El middleware "restringir.empleado" ya bloquea al rol empleado en estas
 * rutas; aquí se cubre además el caso del super admin, que no pertenece a
 * ningún negocio y por tanto no tiene inventario que gestionar.
 *
 * "stockBajo" queda con la misma restricción que el resto por ahora: hoy solo
 * el admin gestiona el inventario. Si en el futuro un empleado necesita ver
 * las alertas de stock (por ejemplo, para reponer sin depender del admin),
 * esa decisión implica exponer esta ruta fuera del grupo "restringir.empleado"
 * y es mejor tomarla cuando exista ese caso de uso real, no antes.
 */
class ProductoController extends Controller
{
    protected SvcProducto $svcProducto;

    public function __construct()
    {
        parent::__construct();

        $this->svcProducto = new SvcProducto;
    }

    /**
     * ¿Ese SKU ya está tomado por OTRO producto del mismo negocio?
     *
     * Se comprueba antes de escribir, en vez de dejar que reviente la
     * restricción única de MySQL: así el usuario recibe un mensaje claro y no
     * un error técnico genérico.
     *
     * Un SKU vacío no se valida: la columna admite varios NULL a propósito,
     * porque el SKU es opcional.
     *
     * @param  int|null  $idExcluir  El propio registro, al editar.
     */
    private function skuYaExiste(?string $sku, $tenantId, $idExcluir = null): bool
    {
        if (trim((string) $sku) === '') {
            return false;
        }

        $existente = $this->svcProducto->buscarPorSku($sku, $tenantId);

        if ($existente === null) {
            return false;
        }

        return $idExcluir === null || (int) $existente['id_producto'] !== (int) $idExcluir;
    }

    public function crear(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los productos se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'nombre' => 'required',
            'cantidad_actual' => 'required|numeric|min:0',
            'cantidad_minima' => 'required|numeric|min:0',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $sku = trim((string) ($datos['sku'] ?? ''));

        if ($this->skuYaExiste($sku, $tenantId)) {
            $this->agregarError('Ya existe un producto con ese SKU');

            return $this->sendResponse();
        }

        $info = [
            'tenant_id' => $tenantId,
            'nombre' => $datos['nombre'],
            'sku' => $sku !== '' ? $sku : null,
            'descripcion' => $datos['descripcion'] ?? null,
            'cantidad_actual' => $datos['cantidad_actual'],
            'cantidad_minima' => $datos['cantidad_minima'],
            'usuario_registra' => session('nombre_usuario'),
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ];

        $idProducto = $this->svcProducto->crear($info);

        if ($idProducto === false) {
            $this->agregarErrorSistema('PROD-CREAR');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($idProducto, 'id_producto');

        return $this->sendResponse();
    }

    public function editar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los productos se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_producto' => 'required',
            'nombre' => 'required',
            'cantidad_actual' => 'required|numeric|min:0',
            'cantidad_minima' => 'required|numeric|min:0',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $sku = trim((string) ($datos['sku'] ?? ''));

        if ($this->skuYaExiste($sku, $tenantId, $datos['id_producto'])) {
            $this->agregarError('Ya existe un producto con ese SKU');

            return $this->sendResponse();
        }

        $info = [
            'nombre' => $datos['nombre'],
            'sku' => $sku !== '' ? $sku : null,
            'descripcion' => $datos['descripcion'] ?? null,
            'cantidad_actual' => $datos['cantidad_actual'],
            'cantidad_minima' => $datos['cantidad_minima'],
        ];

        $resultado = $this->svcProducto->editar($datos['id_producto'], $info, $tenantId);

        if (! $resultado) {
            $this->agregarErrorNoDisponible('el producto', 'PROD-EDIT');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function eliminar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los productos se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_producto' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $resultado = $this->svcProducto->eliminar($datos['id_producto'], $tenantId);

        if (! $resultado) {
            $this->agregarErrorNoDisponible('el producto', 'PROD-ELIM');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function listar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los productos se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($this->svcProducto->listar($tenantId), 'productos');

        return $this->sendResponse();
    }

    public function ingresarStock(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los productos se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_producto' => 'required',
            'cantidad_agregar' => 'required|numeric|min:0.01',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $resultado = $this->svcProducto->ingresarStock($datos['id_producto'], $datos['cantidad_agregar'], $tenantId);

        if (! $resultado) {
            $this->agregarErrorNoDisponible('el producto', 'PROD-STOCK');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function stockBajo(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('Los productos se gestionan desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($this->svcProducto->listarStockBajo($tenantId), 'productos_bajos');

        return $this->sendResponse();
    }
}
