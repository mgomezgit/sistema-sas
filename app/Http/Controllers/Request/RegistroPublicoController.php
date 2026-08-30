<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Usuario;
use App\Service\SvcNegocio;
use App\Service\SvcUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegistroPublicoController extends Controller
{
    protected SvcNegocio $svcNegocio;

    protected SvcUsuario $svcUsuario;

    // Rubros habilitados hoy para el registro autoservicio.
    const RUBROS_DISPONIBLES = ['spa'];

    public function __construct()
    {
        parent::__construct();

        $this->svcNegocio = new SvcNegocio;
        $this->svcUsuario = new SvcUsuario;
    }

    /**
     * Alta autoservicio de un negocio nuevo con su usuario administrador.
     * Es una ruta pública: no exige sesión previa.
     */
    public function crear(): JsonResponse
    {
        $this->setRequestValidationRules([
            'nombre_negocio' => 'required',
            'telefono_contacto' => 'required',
            'rubro' => 'required',
            'nombre' => 'required',
            'email' => 'required|email',
            'clave' => 'required',
            'confirmar_clave' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        if (! in_array($datos['rubro'], self::RUBROS_DISPONIBLES, true)) {
            $this->agregarError('Este rubro estará disponible próximamente. Por ahora solo puedes registrar un negocio de tipo Spa.');

            return $this->sendResponse();
        }

        if ($datos['clave'] !== $datos['confirmar_clave']) {
            $this->agregarError('Las claves no coinciden');

            return $this->sendResponse();
        }

        if (Usuario::where('email', $datos['email'])->exists()) {
            $this->agregarError('Ya existe una cuenta con ese correo');

            return $this->sendResponse();
        }

        // El negocio y su usuario administrador se crean juntos: si algo falla,
        // no debe quedar un negocio sin nadie que pueda entrar a administrarlo.
        try {
            DB::transaction(function () use ($datos) {
                $idNegocio = $this->svcNegocio->crear([
                    'nombre_negocio' => $datos['nombre_negocio'],
                    'rubro' => 'spa',
                    'telefono_contacto' => $datos['telefono_contacto'],
                    'usuario_registra' => 'Registro Publico',
                    'fecha_registro' => date('Y-m-d H:i:s'),
                    'estado' => 1,
                ]);

                if ($idNegocio === false) {
                    throw new \RuntimeException('REG-NEGOCIO');
                }

                $idRolAdmin = Rol::where('nombre_rol', 'admin')->value('id_rol');

                if (! $idRolAdmin) {
                    throw new \RuntimeException('REG-SIN-ROL-ADMIN');
                }

                $idUsuario = $this->svcUsuario->crear([
                    'tenant_id' => $idNegocio,
                    'id_rol' => $idRolAdmin,
                    'usuario' => $datos['email'],
                    'nombre' => $datos['nombre'],
                    'email' => $datos['email'],
                    'clave' => $datos['clave'],
                    'usuario_registra' => 'Registro Publico',
                    'fecha_registro' => date('Y-m-d H:i:s'),
                    'estado' => 1,
                ]);

                if ($idUsuario === false) {
                    throw new \RuntimeException('REG-USUARIO');
                }
            });
        } catch (\Exception $e) {
            Log::channel('database')->info($e);
            $this->agregarError('No fue posible completar el registro, intenta nuevamente');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse('Cuenta creada correctamente', 'mensaje');

        return $this->sendResponse();
    }
}
