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
