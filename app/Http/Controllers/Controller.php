<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

abstract class Controller
{
    public array $respuesta = ['error' => 1, 'mensaje' => '', 'data' => []];

    public Request $request;

    protected array $requestData = [];

    protected array $validationRules = [];

    public function __construct()
    {
        $this->request = request();
        $this->requestData = $this->request->all();
    }

    protected function getRequestData(): array
    {
        return $this->requestData;
    }

    protected function setRequestValidationRules(array $rules): static
    {
        $this->validationRules = $rules;

        return $this;
    }

    protected function validateRequestRules(): bool
    {
        $validator = Validator::make($this->requestData, $this->validationRules);

        if ($validator->fails()) {
            $this->agregarError($validator->errors()->all());

            return false;
        }

        return true;
    }

    public function agregarError($mensaje): void
    {
        $this->respuesta['mensaje'] = $mensaje;
    }

    /**
     * Error técnico: el usuario no puede resolverlo por su cuenta, así que se le
     * entrega un código corto para que lo reporte al soporte y quede rastreable
     * contra el log del canal "database".
     */
    public function agregarErrorSistema(string $codigo): void
    {
        $this->respuesta['mensaje'] = 'Ocurrió un problema técnico y la acción no se completó. '
            .'Vuelve a intentarlo en unos segundos. Si el problema continúa, comunícate con soporte '
            .'e indica este código: '.$codigo;
    }

    /**
     * El registro ya no está disponible para este negocio (fue eliminado, o la
     * pantalla quedó desactualizada). Es recuperable recargando.
     */
    public function agregarErrorNoDisponible(string $registro, string $codigo): void
    {
        $this->respuesta['mensaje'] = 'No se pudo completar la acción porque '.$registro.' ya no está disponible. '
            .'Es posible que se haya eliminado, o que otra persona lo haya modificado. '
            .'Recarga la página e inténtalo de nuevo. Si el problema continúa, comunícate con soporte '
            .'e indica este código: '.$codigo;
    }

    public function respSinError(): void
    {
        $this->respuesta['error'] = 0;
    }

    public function existeErrores(): bool
    {
        return ! empty($this->respuesta['mensaje']);
    }

    public function setDataResponse($valor, $nombreVar = ''): void
    {
        if (is_array($valor) && $nombreVar == '') {
            foreach ($valor as $key => $value) {
                $this->respuesta['data'][$key] = $value;
            }
        } else {
            if ($nombreVar == '') {
                $this->respuesta['data'] = $valor;
            } else {
                $this->respuesta['data'][$nombreVar] = $valor;
            }
        }
    }

    public function setDataLog($identificador, $valor, int $nivel): void
    {
        $mensaje = [
            'data' => is_array($valor) ? $valor : $valor,
            'nivel' => $nivel,
        ];
        $this->respuesta['data']['logs'][$identificador]['mensajes'][] = $mensaje;
    }

    public function sendResponse(): JsonResponse
    {
        return response()->json($this->respuesta);
    }
}
