<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\LandingController::class, 'index']);
Route::get('/login', [App\Http\Controllers\AutenticacionController::class, 'mostrarLogin']);
Route::get('registro', [App\Http\Controllers\RegistroPublicoViewController::class, 'mostrar']);

// Alta autoservicio: pública, sin middleware de sesión ni restricción de rol.
Route::post('request/registro-publico/crear', [App\Http\Controllers\Request\RegistroPublicoController::class, 'crear']);

Route::prefix('request')->group(function () {
    Route::post('autenticacion/login', [App\Http\Controllers\AutenticacionController::class, 'validarLogin']);

    // Agenda propia del empleado: son las únicas rutas de datos que puede usar.
    Route::get('reserva/mis-citas', [App\Http\Controllers\Request\ReservaController::class, 'misCitas']);
    Route::post('reserva/cambiar-estado-mi-cita', [App\Http\Controllers\Request\ReservaController::class, 'cambiarEstadoMiCita']);

    // Solo lectura del horario: cualquier usuario del negocio lo necesita para
    // calcular las franjas del calendario.
    Route::get('negocio/horario', [App\Http\Controllers\Request\NegocioController::class, 'obtenerHorario']);

    // Devuelve una respuesta neutra a empleados y super admin (el widget
    // simplemente no se muestra), por eso no va en el grupo restringido.
    Route::get('negocio/progreso-onboarding', [App\Http\Controllers\Request\NegocioController::class, 'obtenerProgresoOnboarding']);
});

// Endpoints administrativos: cerrados para el rol "empleado", que de otro modo
// podría consultarlos directamente aunque no vea las pantallas.
Route::prefix('request')->middleware('restringir.empleado')->group(function () {
    Route::post('usuario/crear', [App\Http\Controllers\Request\UsuarioController::class, 'crear']);
    Route::post('usuario/editar', [App\Http\Controllers\Request\UsuarioController::class, 'editar']);
    Route::post('usuario/eliminar', [App\Http\Controllers\Request\UsuarioController::class, 'eliminar']);
    Route::get('usuario/listar', [App\Http\Controllers\Request\UsuarioController::class, 'listar']);
    Route::post('cliente/crear', [App\Http\Controllers\Request\ClienteController::class, 'crear']);
    Route::post('cliente/editar', [App\Http\Controllers\Request\ClienteController::class, 'editar']);
    Route::post('cliente/eliminar', [App\Http\Controllers\Request\ClienteController::class, 'eliminar']);
    Route::get('cliente/listar', [App\Http\Controllers\Request\ClienteController::class, 'listar']);
    Route::post('recurso/crear', [App\Http\Controllers\Request\RecursoReservableController::class, 'crear']);
    Route::post('recurso/editar', [App\Http\Controllers\Request\RecursoReservableController::class, 'editar']);
    Route::post('recurso/eliminar', [App\Http\Controllers\Request\RecursoReservableController::class, 'eliminar']);
    Route::get('recurso/listar', [App\Http\Controllers\Request\RecursoReservableController::class, 'listar']);
    Route::post('empleado/crear', [App\Http\Controllers\Request\EmpleadoController::class, 'crear']);
    Route::post('empleado/editar', [App\Http\Controllers\Request\EmpleadoController::class, 'editar']);
    Route::post('empleado/eliminar', [App\Http\Controllers\Request\EmpleadoController::class, 'eliminar']);
    Route::get('empleado/listar', [App\Http\Controllers\Request\EmpleadoController::class, 'listar']);
    Route::post('empleado/crear-acceso', [App\Http\Controllers\Request\EmpleadoController::class, 'crearAcceso']);
    Route::post('reserva/crear', [App\Http\Controllers\Request\ReservaController::class, 'crear']);
    Route::post('reserva/editar', [App\Http\Controllers\Request\ReservaController::class, 'editar']);
    Route::post('reserva/cambiar-estado', [App\Http\Controllers\Request\ReservaController::class, 'cambiarEstado']);
    Route::post('reserva/eliminar', [App\Http\Controllers\Request\ReservaController::class, 'eliminar']);
    Route::get('reserva/listar', [App\Http\Controllers\Request\ReservaController::class, 'listar']);
    Route::get('reserva/listar-calendario', [App\Http\Controllers\Request\ReservaController::class, 'listarParaCalendario']);
    Route::post('negocio/actualizar-tema', [App\Http\Controllers\Request\NegocioController::class, 'actualizarTema']);
    Route::get('negocio/configuracion', [App\Http\Controllers\Request\NegocioController::class, 'obtenerConfiguracion']);
    Route::post('negocio/actualizar-configuracion', [App\Http\Controllers\Request\NegocioController::class, 'actualizarConfiguracion']);
    Route::post('negocio/completar-onboarding', [App\Http\Controllers\Request\NegocioController::class, 'completarOnboarding']);
    Route::post('negocio/marcar-bienvenida', [App\Http\Controllers\Request\NegocioController::class, 'marcarBienvenidaVista']);
});

Route::prefix('backoffice')->middleware('sesion.activa')->group(function () {
    Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index']);
    Route::get('mis-citas', [App\Http\Controllers\MisCitasViewController::class, 'index']);
    Route::get('logout', [App\Http\Controllers\AutenticacionController::class, 'logout']);

    // Módulos administrativos: un usuario con rol "empleado" queda fuera de estos.
    Route::get('usuarios', [App\Http\Controllers\UsuarioViewController::class, 'listar'])->middleware('restringir.empleado');
    Route::get('clientes', [App\Http\Controllers\ClienteViewController::class, 'listar'])->middleware('restringir.empleado');
    Route::get('recursos', [App\Http\Controllers\RecursoReservableViewController::class, 'listar'])->middleware('restringir.empleado');
    Route::get('empleados', [App\Http\Controllers\EmpleadoViewController::class, 'listar'])->middleware('restringir.empleado');
    Route::get('reservas', [App\Http\Controllers\ReservaViewController::class, 'listar'])->middleware('restringir.empleado');
    Route::get('reservas/historial', [App\Http\Controllers\ReservaViewController::class, 'historial'])->middleware('restringir.empleado');
    Route::get('personalizar', [App\Http\Controllers\PersonalizarViewController::class, 'mostrar'])->middleware('restringir.empleado');
    Route::get('configuracion', [App\Http\Controllers\ConfiguracionViewController::class, 'mostrar'])->middleware('restringir.empleado');
});
