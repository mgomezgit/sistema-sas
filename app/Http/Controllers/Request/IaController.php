<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Service\SvcIA;
use Illuminate\Http\JsonResponse;

/**
 * Asistente creativo (módulo de pago "ia"): propone textos al admin del
 * negocio. No guarda nada en banners ni servicios: el admin copia lo que le
 * sirva.
 *
 * Las rutas llevan sesion.activa, restringir.empleado y verificar.modulo:ia
 * (hay una prueba guardián que exige los tres). Aquí se cubre además el caso
 * del super admin, que no pertenece a ningún negocio. El negocio sale SIEMPRE
 * de la sesión: un tenant_id en el cuerpo se ignora.
 */
class IaController extends Controller
{
    private SvcIA $svcIA;

    public function __construct()
    {
        parent::__construct();

        $this->svcIA = new SvcIA;
    }

    /** ¿Puede generar ahora y cuántas generaciones le quedan este mes? */
    public function estado(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('El asistente creativo se usa desde la cuenta de cada negocio.');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse([
            'disponible' => $this->svcIA->disponible($tenantId),
            'restantes' => $this->svcIA->restantes($tenantId),
        ]);

        return $this->sendResponse();
    }

    /** Genera 3 propuestas de texto. */
    public function generar(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('El asistente creativo se usa desde la cuenta de cada negocio.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'tipo' => 'required|string|in:'.implode(',', array_keys(SvcIA::TIPOS)),
            'tono' => 'required|string|in:'.implode(',', array_keys(SvcIA::TONOS)),
            'tema' => 'nullable|string|max:'.SvcIA::LARGO_MAXIMO_TEMA,
            'id_recurso' => 'required_if:tipo,descripcion_servicio|prohibited_unless:tipo,descripcion_servicio|nullable|integer|min:1',
        ], [
            'tipo.required' => 'Elige qué quieres generar.',
            'tipo.in' => 'Elige qué quieres generar: banner, frase o descripción de servicio.',
            'tono.required' => 'Elige un tono.',
            'tono.in' => 'Elige un tono: cálido, elegante, divertido o profesional.',
            'tema.string' => 'La idea debe ser un texto.',
            'tema.max' => 'La idea puede tener hasta '.SvcIA::LARGO_MAXIMO_TEMA.' caracteres.',
            'id_recurso.required_if' => 'Elige el servicio que quieres describir.',
            'id_recurso.prohibited_unless' => 'El servicio solo se indica al generar una descripción de servicio.',
            'id_recurso.integer' => SvcIA::MENSAJE_SERVICIO,
            'id_recurso.min' => SvcIA::MENSAJE_SERVICIO,
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        $resultado = $this->svcIA->generar(
            $tenantId,
            session('id_usuario'),
            $datos['tipo'],
            $datos['tono'],
            $datos['tema'] ?? null,
            $datos['id_recurso'] ?? null
        );

        if (! $resultado['ok']) {
            $this->agregarError($resultado['mensaje']);

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse([
            'opciones' => $resultado['opciones'],
            'restantes' => $resultado['restantes'],
        ]);

        return $this->sendResponse();
    }

    /**
     * Guarda (o quita) el perfil PRIVADO del negocio. Los dos campos son
     * opcionales; si ambos quedan vacíos se quita el perfil. El negocio sale
     * SIEMPRE de la sesión: un tenant_id o id_negocio en el cuerpo no se lee.
     * Se guarda tal cual lo escribió el admin (el escape se hace al pintar).
     */
    public function perfil(): JsonResponse
    {
        $tenantId = session('tenant_id');

        if ($tenantId === null) {
            $this->agregarError('El asistente creativo se usa desde la cuenta de cada negocio.');

            return $this->sendResponse();
        }

        $datos = $this->getRequestData();
        $especialidad = $datos['especialidad'] ?? null;
        $publico = $datos['publico'] ?? null;

        $esp = SvcIA::normalizarCampoPerfil($especialidad, SvcIA::LARGO_MAXIMO_ESPECIALIDAD);
        $pub = SvcIA::normalizarCampoPerfil($publico, SvcIA::LARGO_MAXIMO_PUBLICO);

        $errores = [];

        if (! $esp['ok']) {
            $errores[] = 'Lo que hace especial a tu negocio puede tener hasta '.SvcIA::LARGO_MAXIMO_ESPECIALIDAD.' caracteres y no puede llevar caracteres de control.';
        }

        if (! $pub['ok']) {
            $errores[] = 'A quién le hablas puede tener hasta '.SvcIA::LARGO_MAXIMO_PUBLICO.' caracteres y no puede llevar caracteres de control.';
        }

        if ($errores !== []) {
            $this->agregarError(implode(' ', $errores));

            return $this->sendResponse();
        }

        if (! $this->svcIA->guardarPerfil($tenantId, $especialidad, $publico)) {
            $this->agregarError('No se pudo guardar el perfil. Inténtalo de nuevo.');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($this->svcIA->obtenerPerfil($tenantId), 'perfil');

        return $this->sendResponse();
    }
}
