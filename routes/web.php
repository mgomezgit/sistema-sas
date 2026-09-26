<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\LandingController::class, 'index']);
Route::get('/login', [App\Http\Controllers\AutenticacionController::class, 'mostrarLogin']);
Route::get('registro', [App\Http\Controllers\RegistroPublicoViewController::class, 'mostrar']);

// Alta autoservicio: pública, sin middleware de sesión ni restricción de rol.
Route::post('request/registro-publico/crear', [App\Http\Controllers\Request\RegistroPublicoController::class, 'crear']);

/*
 * ================= ZONA PÚBLICA DE AUTOGESTIÓN =================
 *
 * Páginas y datos que ve el cliente final de cada negocio, identificado por su
 * slug en la URL. No pasa por 'sesion.activa' ni por 'restringir.empleado': es
 * una zona anónima, separada a propósito de backoffice/* y request/*.
 *
 * El throttle va puesto desde ahora, con el grupo todavía de solo lectura: la
 * puerta se abre ya, y es mejor que el límite exista antes de que por aquí se
 * pueda escribir nada.
 */
Route::prefix('reservar/{slug}')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [App\Http\Controllers\Publico\PaginaPublicaViewController::class, 'mostrar']);
});

Route::prefix('publico/{slug}')->middleware('throttle:60,1')->group(function () {
    Route::get('informacion', [App\Http\Controllers\Publico\PublicoController::class, 'informacionNegocio']);
    Route::get('servicios', [App\Http\Controllers\Publico\PublicoController::class, 'servicios']);
    Route::get('equipo', [App\Http\Controllers\Publico\PublicoController::class, 'equipo']);
    Route::get('banners', [App\Http\Controllers\Publico\PublicoController::class, 'bannersPublicos']);

    // Única escritura de la zona pública: deja una solicitud pendiente.
    Route::post('agendar', [App\Http\Controllers\Publico\PublicoController::class, 'agendar']);
});

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
    Route::post('negocio/marcar-reportes-tour', [App\Http\Controllers\Request\NegocioController::class, 'marcarReportesTourVisto']);

    // Reportes: consulta en pantalla y descarga del archivo Excel.
    Route::get('reporte/ventas-preview', [App\Http\Controllers\Request\ReporteController::class, 'ventasPreview']);
    Route::get('reporte/ventas-descargar', [App\Http\Controllers\Request\ReporteController::class, 'ventasDescargar']);
    Route::get('reporte/servicios-preview', [App\Http\Controllers\Request\ReporteController::class, 'serviciosPreview']);
    Route::get('reporte/servicios-descargar', [App\Http\Controllers\Request\ReporteController::class, 'serviciosDescargar']);

    // Carga masiva: plantilla de ejemplo e importación del archivo lleno.
    Route::get('carga-masiva/plantilla/{tipo}', [App\Http\Controllers\Request\CargaMasivaController::class, 'descargarPlantilla']);
    Route::post('carga-masiva/importar/{tipo}', [App\Http\Controllers\Request\CargaMasivaController::class, 'importar']);

    // Comisiones: módulo solo del administrador del negocio, y además módulo de
    // pago. Bloquear solo la pantalla no bastaría: sin esto, un negocio sin el
    // módulo activo podría seguir llamando estos endpoints a mano.
    Route::middleware('verificar.modulo:comisiones')->group(function () {
        Route::get('comisiones/informe', [App\Http\Controllers\Request\ComisionController::class, 'informe']);
        Route::post('comisiones/marcar-pagado', [App\Http\Controllers\Request\ComisionController::class, 'marcarPagado']);
        Route::get('comisiones/tarifas', [App\Http\Controllers\Request\ComisionController::class, 'listarTarifas']);
        Route::post('comisiones/tarifas/guardar', [App\Http\Controllers\Request\ComisionController::class, 'guardarTarifa']);
        Route::post('comisiones/tarifas/eliminar', [App\Http\Controllers\Request\ComisionController::class, 'eliminarTarifa']);
        Route::get('comisiones/historial-pagos', [App\Http\Controllers\Request\ComisionController::class, 'historialPagos']);
    });

    Route::post('banner/crear', [App\Http\Controllers\Request\BannerPromocionalController::class, 'crear']);
    Route::post('banner/editar', [App\Http\Controllers\Request\BannerPromocionalController::class, 'editar']);
    Route::post('banner/eliminar', [App\Http\Controllers\Request\BannerPromocionalController::class, 'eliminar']);
    Route::get('banner/listar', [App\Http\Controllers\Request\BannerPromocionalController::class, 'listar']);

    // Inventario de productos.
    Route::post('producto/crear', [App\Http\Controllers\Request\ProductoController::class, 'crear']);
    Route::post('producto/editar', [App\Http\Controllers\Request\ProductoController::class, 'editar']);
    Route::post('producto/eliminar', [App\Http\Controllers\Request\ProductoController::class, 'eliminar']);
    Route::get('producto/listar', [App\Http\Controllers\Request\ProductoController::class, 'listar']);
    Route::post('producto/ingresar-stock', [App\Http\Controllers\Request\ProductoController::class, 'ingresarStock']);
    Route::get('producto/stock-bajo', [App\Http\Controllers\Request\ProductoController::class, 'stockBajo']);
    // GET: solo sugiere un código libre, no guarda ni lo reserva.
    Route::get('producto/generar-sku', [App\Http\Controllers\Request\ProductoController::class, 'generarSku']);
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
    Route::get('reportes/ventas', [App\Http\Controllers\ReporteViewController::class, 'ventas'])->middleware('restringir.empleado');
    Route::get('reportes/servicios', [App\Http\Controllers\ReporteViewController::class, 'servicios'])->middleware('restringir.empleado');
    Route::get('carga-masiva', [App\Http\Controllers\CargaMasivaViewController::class, 'index'])->middleware('restringir.empleado');
    Route::get('productos', [App\Http\Controllers\ProductoViewController::class, 'listar'])->middleware('restringir.empleado');
    Route::get('comisiones', [App\Http\Controllers\ComisionViewController::class, 'informe'])->middleware(['restringir.empleado', 'verificar.modulo:comisiones']);
});
