<?php

namespace App\Http\Controllers\Request;

use App\Exports\PlantillaEmpleadosExport;
use App\Exports\PlantillaProductosExport;
use App\Exports\PlantillaRecursosExport;
use App\Exports\PlantillaUsuariosExport;
use App\Http\Controllers\Controller;
use App\Imports\EmpleadosImport;
use App\Imports\ProductosImport;
use App\Imports\RecursosImport;
use App\Imports\UsuariosImport;
use App\Service\SvcModulo;
use App\Support\Plataforma;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Carga masiva: descarga de plantillas e importación de los archivos llenos.
 *
 * El middleware "restringir.empleado" bloquea al rol empleado en las rutas, y
 * aquí se cubre además al super admin, que no tiene negocio al cual cargar.
 */
class CargaMasivaController extends Controller
{
    const TIPOS_VALIDOS = ['empleados', 'recursos', 'usuarios', 'productos'];

    /** Peso máximo del archivo subido, en kilobytes. */
    const PESO_MAXIMO_KB = 5120;

    /**
     * El tipo 'productos' pertenece al módulo de pago Inventario: sin el módulo
     * activo, ni se descarga su plantilla ni se importa. Los demás tipos
     * (empleados, recursos, usuarios) son del núcleo y no se tocan. Deja el
     * error del módulo en la respuesta y devuelve true si falta el módulo.
     */
    private function moduloInventarioFalta(string $tipo, $tenantId): bool
    {
        if ($tipo !== 'productos') {
            return false;
        }

        if (app(SvcModulo::class)->estaActivo($tenantId, 'inventario')) {
            return false;
        }

        $this->agregarError('El inventario es un módulo de pago que tu negocio no tiene activo. Para activarlo, '.Plataforma::textoContacto().'.');

        return true;
    }

    public function descargarPlantilla($tipo)
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('La carga masiva se hace desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        if (! in_array($tipo, self::TIPOS_VALIDOS, true)) {
            $this->agregarError('El tipo de plantilla solicitado no es válido.');

            return $this->sendResponse();
        }

        if ($this->moduloInventarioFalta($tipo, $tenantId)) {
            return $this->sendResponse();
        }

        $plantillas = [
            'empleados' => PlantillaEmpleadosExport::class,
            'recursos' => PlantillaRecursosExport::class,
            'usuarios' => PlantillaUsuariosExport::class,
            'productos' => PlantillaProductosExport::class,
        ];

        $clase = $plantillas[$tipo];

        return Excel::download(new $clase, 'plantilla-'.$tipo.'.xlsx');
    }

    public function importar($tipo): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('La carga masiva se hace desde la cuenta de cada negocio. Inicia sesión con el usuario del negocio correspondiente.');

            return $this->sendResponse();
        }

        if (! in_array($tipo, self::TIPOS_VALIDOS, true)) {
            $this->agregarError('El tipo de carga solicitado no es válido.');

            return $this->sendResponse();
        }

        if ($this->moduloInventarioFalta($tipo, $tenantId)) {
            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'archivo' => 'required|file|mimes:xlsx,xls|max:'.self::PESO_MAXIMO_KB,
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $archivo = $this->request->file('archivo');

        // El tenant sale de la sesión, nunca del archivo: así un Excel manipulado
        // no puede insertar registros en otro negocio.
        $importadores = [
            'empleados' => EmpleadosImport::class,
            'recursos' => RecursosImport::class,
            'usuarios' => UsuariosImport::class,
            'productos' => ProductosImport::class,
        ];

        $clase = $importadores[$tipo];
        $importador = new $clase($tenantId);

        try {
            Excel::import($importador, $archivo);
        } catch (\Exception $e) {
            Log::channel('database')->info($e);

            $this->agregarError('No fue posible leer el archivo. Revisa que sea un Excel válido generado a partir de la plantilla.');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($importador->resultados(), 'resultados');

        return $this->sendResponse();
    }
}
