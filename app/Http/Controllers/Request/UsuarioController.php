<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Usuario;
use App\Service\SvcUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UsuarioController extends Controller
{
    protected SvcUsuario $svcUsuario;

    public function __construct()
    {
        parent::__construct();

        $this->svcUsuario = new SvcUsuario;
    }

    /**
     * Una cuenta sin negocio (tenant_id null) SOLO puede gestionar usuarios de
     * toda la plataforma si su rol es super_admin.
     *
     * Este módulo es el único que interpreta tenant_id null como "no filtres
     * por negocio" (SvcUsuario) y que acepta el tenant_id del cuerpo al crear.
     * Sin esta comprobación, un usuario mal cargado con rol admin y sin negocio
     * heredaría poderes de plataforma. Manda el rol, no la ausencia de negocio.
     */
    private function sinNegocioNiRolDePlataforma(): bool
    {
        return session('tenant_id') === null && ! Rol::esRolSuperAdmin(session('id_rol'));
    }

    /**
     * Rol asignable desde este módulo: solo admin o empleado, para quien sea
     * que actúe (admin de negocio o super admin). super_admin JAMÁS se asigna
     * aquí: esa cuenta es única y se siembra aparte. Lista blanca por nombre,
     * no "que exista": un id cualquiera que apunte a super_admin se rechaza.
     *
     * Deja rastro de cada intento rechazado, como pide la política de seguridad.
     */
    private function rolAsignableRechazado($idRol): bool
    {
        if (Rol::esRolDeNegocio($idRol)) {
            return false;
        }

        Log::channel('database')->info(
            'SEGURIDAD: intento rechazado de asignar el rol '.($idRol ?? 'null').' desde el modulo de usuarios. '
            .'Quien actúa: usuario '.(session('id_usuario') ?? 'sin sesion').' (negocio '.(session('tenant_id') ?? 'plataforma').').'
        );

        return true;
    }

    /**
     * ¿Esa credencial (usuario o correo) ya la tiene OTRA cuenta ACTIVA?
     *
     * La comprobación es global, no por negocio: el login es una sola pantalla
     * para toda la plataforma, así que ni el correo ni el nombre de usuario
     * pueden repetirse entre negocios mientras las cuentas estén activas.
     *
     * Solo cuentan las activas: al desactivar a alguien, sus credenciales
     * quedan libres para reutilizarse. La base impone lo mismo con un índice
     * único sobre columnas generadas que valen NULL si la cuenta está inactiva;
     * esto se adelanta para poder decir cuál de los dos campos está ocupado.
     *
     * @param  string  $campo  'usuario' o 'email'.
     * @param  int|null  $idExcluir  El propio registro, al editar.
     */
    private function credencialOcupada(string $campo, $valor, $idExcluir = null): bool
    {
        $query = Usuario::where($campo, $valor)->where('estado', 1);

        if ($idExcluir !== null) {
            $query->where('id_usuario', '!=', $idExcluir);
        }

        return $query->exists();
    }

    public function crear(): JsonResponse
    {
        if ($this->sinNegocioNiRolDePlataforma()) {
            $this->agregarError('No tienes permiso para gestionar usuarios. Tu cuenta no está vinculada a ningún negocio.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'usuario' => 'required',
            'nombre' => 'required',
            'email' => 'required|email',
            'clave' => 'required|min:'.SvcUsuario::LARGO_MINIMO_CLAVE,
            'tenant_id' => 'required',
            'id_rol' => 'required',
        ], SvcUsuario::mensajesDeClave());

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        if ($this->rolAsignableRechazado($datos['id_rol'])) {
            $this->agregarError('Solo puedes asignar los roles "admin" o "empleado".');

            return $this->sendResponse();
        }

        $info = [
            'usuario' => $datos['usuario'],
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'clave' => $datos['clave'],
            'tenant_id' => $datos['tenant_id'],
            'id_rol' => $datos['id_rol'],
            'usuario_registra' => session('nombre_usuario'),
            'fecha_registro' => date('Y-m-d H:i:s'),
            'estado' => 1,
        ];

        if (session('tenant_id') !== null) {
            $info['tenant_id'] = session('tenant_id');
        }

        // El usuario y el correo son únicos entre cuentas activas: se avisa cuál
        // está ocupado antes de intentar el insert, para no mostrar un error
        // genérico. Las credenciales de una cuenta desactivada quedan libres.
        if ($this->credencialOcupada('usuario', $datos['usuario'])) {
            $this->agregarError('El usuario "'.$datos['usuario'].'" ya está en uso. Elige otro nombre de usuario, por ejemplo agregándole un número o un apellido.');

            return $this->sendResponse();
        }

        if ($this->credencialOcupada('email', $datos['email'])) {
            $this->agregarError('El correo "'.$datos['email'].'" ya está registrado en otra cuenta. Usa un correo diferente.');

            return $this->sendResponse();
        }

        $idUsuario = $this->svcUsuario->crear($info);

        if ($idUsuario === false) {
            $this->agregarErrorSistema('USR-CREAR');

            return $this->sendResponse();
        }

        $this->respSinError();
        $this->setDataResponse($idUsuario, 'id_usuario');

        return $this->sendResponse();
    }

    public function editar(): JsonResponse
    {
        if ($this->sinNegocioNiRolDePlataforma()) {
            $this->agregarError('No tienes permiso para gestionar usuarios. Tu cuenta no está vinculada a ningún negocio.');

            return $this->sendResponse();
        }

        // La cuenta de plataforma no tiene negocio, así que al editarla el
        // tenant_id llega nulo y no puede exigirse; para cualquier otra cuenta
        // se sigue exigiendo. Se consulta el id antes de validar.
        $idUsuarioEditar = $this->getRequestData()['id_usuario'] ?? null;
        $esPlataforma = $idUsuarioEditar !== null && SvcUsuario::esCuentaDePlataforma($idUsuarioEditar);

        $this->setRequestValidationRules([
            'id_usuario' => 'required',
            'usuario' => 'required',
            'nombre' => 'required',
            'email' => 'required|email',
            'tenant_id' => $esPlataforma ? 'nullable' : 'required',
            'id_rol' => 'required',
            'estado' => 'required|in:0,1',
            // Al editar la clave es opcional (vacía = no se cambia); si
            // viene, cumple el mismo largo mínimo que al crear.
            'clave' => 'nullable|min:'.SvcUsuario::LARGO_MINIMO_CLAVE,
        ], SvcUsuario::mensajesDeClave());

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        // La cuenta de plataforma solo admite editar nombre, correo y clave. Un
        // admin de negocio ni siquiera la ve (su tenant no coincide); solo el
        // propio super admin llega aquí. Se rechaza cambiarle el rol o darla de
        // baja; su negocio nulo lo conserva el Service.
        if (SvcUsuario::esCuentaDePlataforma($datos['id_usuario'])) {
            if (! Rol::esRolSuperAdmin($datos['id_rol']) || (int) $datos['estado'] === 0) {
                Log::channel('database')->info(
                    'SEGURIDAD: intento rechazado de cambiar el rol o desactivar la cuenta de plataforma (usuario '
                    .$datos['id_usuario'].'). Quien actúa: usuario '.(session('id_usuario') ?? 'sin sesion').'.'
                );
                $this->agregarError('La cuenta de plataforma no puede cambiar de rol ni desactivarse. Solo puedes editar su nombre, correo y clave.');

                return $this->sendResponse();
            }
        } elseif ($this->rolAsignableRechazado($datos['id_rol'])) {
            $this->agregarError('Solo puedes asignar los roles "admin" o "empleado".');

            return $this->sendResponse();
        }

        $info = [
            'usuario' => $datos['usuario'],
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'tenant_id' => $datos['tenant_id'],
            'id_rol' => $datos['id_rol'],
            'estado' => (int) $datos['estado'],
        ];

        if (array_key_exists('clave', $datos)) {
            $info['clave'] = $datos['clave'];
        }

        // El usuario y el correo son únicos en toda la plataforma. Se comprueban
        // antes de escribir, excluyendo el propio registro para que guardar sin
        // cambiarlos no choque consigo mismo. Sin esto, el alta fallaba contra la
        // restricción de la base y se mostraba "el usuario no está disponible",
        // que sugiere que el registro no existe cuando el problema es otro.
        if ($this->credencialOcupada('usuario', $datos['usuario'], $datos['id_usuario'])) {
            $this->agregarError('El usuario "'.$datos['usuario'].'" ya está en uso por otra cuenta. Elige otro nombre de usuario.');

            return $this->sendResponse();
        }

        if ($this->credencialOcupada('email', $datos['email'], $datos['id_usuario'])) {
            $this->agregarError('El correo "'.$datos['email'].'" ya está registrado en otra cuenta. Usa un correo diferente.');

            return $this->sendResponse();
        }

        $tenantId = session('tenant_id');

        $resultado = $this->svcUsuario->editar($datos['id_usuario'], $info, $tenantId);

        if (! $resultado) {
            $this->agregarErrorNoDisponible('el usuario', 'USR-EDIT');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function eliminar(): JsonResponse
    {
        if ($this->sinNegocioNiRolDePlataforma()) {
            $this->agregarError('No tienes permiso para gestionar usuarios. Tu cuenta no está vinculada a ningún negocio.');

            return $this->sendResponse();
        }

        $this->setRequestValidationRules([
            'id_usuario' => 'required',
        ]);

        if (! $this->validateRequestRules()) {
            return $this->sendResponse();
        }

        $datos = $this->getRequestData();

        // La cuenta de plataforma no se elimina por ningún camino (el Service lo
        // vuelve a impedir); aquí se da el mensaje claro.
        if (SvcUsuario::esCuentaDePlataforma($datos['id_usuario'])) {
            Log::channel('database')->info(
                'SEGURIDAD: intento rechazado de eliminar la cuenta de plataforma (usuario '.$datos['id_usuario'].') '
                .'desde el modulo de usuarios. Quien actúa: usuario '.(session('id_usuario') ?? 'sin sesion').'.'
            );
            $this->agregarError('La cuenta de plataforma no puede eliminarse.');

            return $this->sendResponse();
        }

        $tenantId = session('tenant_id');

        $resultado = $this->svcUsuario->eliminar($datos['id_usuario'], $tenantId);

        if (! $resultado) {
            $this->agregarErrorNoDisponible('el usuario', 'USR-ELIM');

            return $this->sendResponse();
        }

        $this->respSinError();

        return $this->sendResponse();
    }

    public function listar(): JsonResponse
    {
        if ($this->sinNegocioNiRolDePlataforma()) {
            $this->agregarError('No tienes permiso para gestionar usuarios. Tu cuenta no está vinculada a ningún negocio.');

            return $this->sendResponse();
        }

        $tenantId = session('tenant_id');

        $datos = $this->getRequestData();

        // "incluir_inactivos=1" es lo que activa el filtro "Mostrar inactivos"
        // de la tabla. Sin él, una cuenta dada de baja no aparece: es la única
        // forma de que desactivar se sienta reversible y no como un borrado.
        $incluirInactivos = (bool) ($datos['incluir_inactivos'] ?? false);

        $this->respSinError();
        $this->setDataResponse($this->svcUsuario->listar($tenantId, $incluirInactivos), 'usuarios');

        return $this->sendResponse();
    }
}
