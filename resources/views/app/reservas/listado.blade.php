@extends('layout.backoffice')

@section('title', 'Reservas')

@section('estilos')
    {{-- El bundle global de FullCalendar inyecta su propio CSS en tiempo de
         ejecución; no existe una hoja de estilos separada que cargar aquí. --}}
    <style>
        .input_vacio {
            border-color: var(--danger) !important;
        }

        .titulo-pagina {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .subtitulo-pagina {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0;
            max-width: 520px;
        }

        #modal-reserva .modal-title i {
            color: var(--accent);
        }

        /* ---------- Calendario (FullCalendar) ---------- */
        #calendario-reservas {
            --fc-page-bg-color: transparent;
            --fc-neutral-bg-color: var(--bg-input);
            --fc-neutral-text-color: var(--text-secondary);
            --fc-border-color: var(--border-color);
            --fc-button-text-color: var(--text-primary);
            --fc-button-bg-color: var(--bg-input);
            --fc-button-border-color: var(--border-color);
            --fc-button-hover-bg-color: var(--bg-card-hover);
            --fc-button-hover-border-color: var(--border-color-strong);
            --fc-button-active-bg-color: var(--accent);
            --fc-button-active-border-color: var(--accent);
            --fc-today-bg-color: var(--accent-soft);
            --fc-now-indicator-color: var(--accent);
            --fc-highlight-color: var(--accent-soft);
            --fc-list-event-hover-bg-color: var(--bg-card-hover);
            --fc-event-selected-overlay-color: rgba(0, 0, 0, 0.2);
            color: var(--text-primary);
        }

        #calendario-reservas a {
            color: inherit;
            text-decoration: none;
        }

        #calendario-reservas .fc-toolbar-title {
            /* El locale español ya entrega "septiembre de 2026" en minúsculas
               tal cual debe verse; capitalize aquí pondría "De" en mayúscula. */
            color: var(--text-primary);
            font-size: 1.15rem;
            font-weight: 700;
        }

        #calendario-reservas .fc-button {
            box-shadow: none !important;
            text-transform: capitalize;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 0.4rem 0.85rem;
        }

        #calendario-reservas .fc-button:focus {
            box-shadow: none !important;
        }

        #calendario-reservas .fc-button-primary:not(:disabled).fc-button-active {
            color: var(--text-sobre-accent);
        }

        #calendario-reservas .fc-col-header-cell-cushion {
            color: var(--text-secondary);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.6rem 0;
        }

        #calendario-reservas .fc-daygrid-day-number {
            color: var(--text-primary);
            font-size: 0.88rem;
            padding: 0.4rem;
        }

        #calendario-reservas .fc-day-other .fc-daygrid-day-number {
            color: var(--text-muted);
        }

        #calendario-reservas .fc-daygrid-day-frame,
        #calendario-reservas .fc-timegrid-col-frame {
            cursor: pointer;
        }

        #calendario-reservas .fc-timegrid-slot-label,
        #calendario-reservas .fc-timegrid-axis {
            color: var(--text-secondary);
            font-size: 0.78rem;
        }

        #calendario-reservas .fc-event,
        #calendario-reservas .fc-event .fc-event-title,
        #calendario-reservas .fc-event .fc-event-time,
        #calendario-reservas .fc-list-event-title {
            /* El fondo del evento es siempre un pastel claro (variantes -soft),
               así que el texto necesita un color oscuro fijo para leerse bien,
               incluso en tema oscuro. Lo fija inicializarCalendario() como
               variable inline sobre #calendario-reservas; con "!important"
               porque FullCalendar no expone su --fc-event-text-color de forma
               confiable sobre los eventos en bloque de dayGrid/timeGrid. */
            color: var(--color-texto-evento, var(--text-primary)) !important;
        }

        #calendario-reservas .fc-event {
            border-radius: var(--radius-sm);
            border-width: 1px;
            padding: 1px 4px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
        }

        #calendario-reservas .fc-list-day-cushion {
            background-color: var(--bg-input);
        }

        #calendario-reservas .fc-scrollgrid {
            border-color: var(--border-color);
        }

        /* ---------- Panel de detalle de un evento ---------- */
        .panel-detalle-evento {
            display: none;
            position: fixed;
            width: 320px;
            z-index: 1500;
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 1.1rem;
        }

        .panel-detalle-evento.visible {
            display: block;
            animation: aparecerPanel 0.2s ease;
        }

        @keyframes aparecerPanel {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .detalle-cabecera {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.9rem;
        }

        .detalle-cabecera .detalle-nombres {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
        }

        #detalle-titulo-cliente {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1rem;
        }

        #detalle-servicio {
            color: var(--text-secondary);
            font-size: 0.82rem;
        }

        .btn-cerrar-detalle {
            background: none;
            border: none;
            color: var(--text-muted);
            line-height: 1;
            padding: 0.15rem;
            flex-shrink: 0;
        }

        .btn-cerrar-detalle:hover {
            color: var(--text-primary);
        }

        .detalle-cuerpo {
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            margin-bottom: 1rem;
        }

        .detalle-fila {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            color: var(--text-secondary);
            font-size: 0.86rem;
        }

        .detalle-fila i {
            color: var(--accent);
            width: 16px;
            text-align: center;
        }

        .detalle-etiqueta-estados {
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.5rem;
        }

        .detalle-estados {
            display: flex;
            gap: 0.65rem;
            margin-bottom: 0.3rem;
        }

        .chip-estado {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: transparent;
            cursor: pointer;
            transition: var(--transition-base);
            padding: 0;
        }

        .chip-estado i {
            font-size: 0.8rem;
        }

        .chip-estado:hover {
            transform: scale(1.1);
        }

        .chip-pendiente { background-color: var(--warning-soft); }
        .chip-confirmada { background-color: var(--accent-soft); }
        .chip-completada { background-color: var(--success-soft); }
        .chip-cancelada { background-color: var(--danger-soft); }

        .chip-estado.activo {
            box-shadow: 0 0 0 2px var(--bg-card), 0 0 0 4px currentColor;
        }

        .chip-pendiente.activo { color: var(--warning); }
        .chip-confirmada.activo { color: var(--accent); }
        .chip-completada.activo { color: var(--success); }
        .chip-cancelada.activo { color: var(--danger); }

        .detalle-leyenda-estados {
            display: flex;
            gap: 0.65rem;
            margin-bottom: 1rem;
        }

        .detalle-leyenda-estados span {
            width: 30px;
            text-align: center;
            font-size: 0.58rem;
            color: var(--text-muted);
        }

        .detalle-acciones {
            display: flex;
            gap: 0.6rem;
            padding-top: 0.85rem;
            border-top: 1px solid var(--border-color);
        }

        .detalle-acciones button {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: var(--radius-sm);
            padding: 0.5rem 0.7rem;
            transition: var(--transition-base);
        }

        .btn-detalle-eliminar {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--danger);
        }

        .btn-detalle-eliminar:hover {
            background-color: var(--danger-soft);
            border-color: var(--danger);
        }

        /* ---------- Reservas de este día (tarjetas por empleado) ---------- */
        .titulo-bloque-dia {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-primary);
            font-size: 1.02rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .titulo-bloque-dia i {
            color: var(--accent);
        }

        .fecha-panel {
            margin-left: auto;
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .card-empleado {
            margin-bottom: 1rem;
        }

        .card-empleado .encabezado-empleado {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding-bottom: 0.75rem;
            margin-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-weight: 600;
        }

        .card-empleado .encabezado-empleado i {
            color: var(--accent);
        }

        .card-empleado .contador-citas {
            margin-left: auto;
            color: var(--text-secondary);
            font-size: 0.8rem;
            font-weight: 500;
        }

        .linea-reserva {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            padding: 0.7rem 0.5rem;
            border-radius: var(--radius-sm);
            transition: var(--transition-base);
        }

        .linea-reserva:hover {
            background-color: var(--bg-card-hover);
        }

        .linea-reserva .horario-reserva {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.9rem;
            min-width: 130px;
        }

        .linea-reserva .cliente-reserva {
            color: var(--text-primary);
            font-size: 0.9rem;
            min-width: 160px;
        }

        .linea-reserva .recurso-reserva {
            color: var(--text-secondary);
            font-size: 0.85rem;
            flex: 1;
            min-width: 150px;
        }

        .linea-reserva .acciones-reserva {
            margin-left: auto;
        }

        .linea-reserva.reserva-cancelada .horario-reserva,
        .linea-reserva.reserva-cancelada .cliente-reserva,
        .linea-reserva.reserva-cancelada .recurso-reserva {
            text-decoration: line-through;
            color: var(--text-muted);
        }

        .badge-reserva {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-reserva-pendiente {
            background-color: var(--warning-soft);
            color: var(--warning);
        }

        .badge-reserva-confirmada {
            background-color: var(--accent-soft);
            color: var(--accent);
        }

        .badge-reserva-completada {
            background-color: var(--success-soft);
            color: var(--success);
        }

        .badge-reserva-cancelada {
            background-color: var(--danger-soft);
            color: var(--danger);
        }

        .mensaje-vacio {
            color: var(--text-secondary);
            text-align: center;
            padding: 2.5rem 1rem;
        }

        .mensaje-vacio i {
            font-size: 2rem;
            color: var(--text-muted);
            display: block;
            margin-bottom: 0.75rem;
        }

        .texto-ayuda-campo {
            color: var(--text-muted);
            font-size: 0.8rem;
        }
    </style>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="titulo-pagina">Reservas</h2>
            <p class="subtitulo-pagina">Calendario y gestión de citas.</p>
        </div>
        <button type="button" id="btn-nueva-reserva" class="btn-primario-accento">
            <i class="bi bi-calendar-plus"></i> Nueva reserva
        </button>
    </div>

    {{-- Guarda el día seleccionado; el resto de la vista sigue leyéndolo de aquí. --}}
    <input type="hidden" id="filtro-fecha">

    <div class="card-elevada mb-4">
        <div id="calendario-reservas"></div>
    </div>

    <div id="panel-dia">
        <div class="titulo-bloque-dia">
            <i class="bi bi-calendar-event"></i>
            Reservas de este día
            <span class="fecha-panel" id="fecha-panel-agenda"></span>
        </div>

        <div id="contenedor-agenda"></div>
    </div>

    {{-- Panel flotante con el detalle de un evento del calendario. --}}
    <div id="panel-detalle-evento" class="panel-detalle-evento">
        <div class="detalle-cabecera">
            <div class="detalle-nombres">
                <span id="detalle-titulo-cliente"></span>
                <span id="detalle-servicio"></span>
            </div>
            <button type="button" id="btn-cerrar-detalle" class="btn-cerrar-detalle" aria-label="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="detalle-cuerpo">
            <div class="detalle-fila">
                <i class="bi bi-telephone"></i>
                <span id="detalle-telefono"></span>
            </div>
            <div class="detalle-fila">
                <i class="bi bi-clock"></i>
                <span id="detalle-horario"></span>
            </div>
            <div class="detalle-fila">
                <i class="bi bi-person-badge"></i>
                <span id="detalle-empleado"></span>
            </div>
            <div class="detalle-fila" id="fila-detalle-notas" style="display: none;">
                <i class="bi bi-sticky"></i>
                <span id="detalle-notas"></span>
            </div>
        </div>

        <div class="detalle-etiqueta-estados">Estado de la reserva</div>
        <div class="detalle-estados">
            <button type="button" class="chip-estado chip-pendiente" data-estado="pendiente" title="Pendiente"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="chip-estado chip-confirmada" data-estado="confirmada" title="Confirmada"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="chip-estado chip-completada" data-estado="completada" title="Completada"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="chip-estado chip-cancelada" data-estado="cancelada" title="Cancelada"><i class="bi bi-check-lg"></i></button>
        </div>
        <div class="detalle-leyenda-estados">
            <span>Pend.</span><span>Confirm.</span><span>Complet.</span><span>Cancel.</span>
        </div>

        <div class="detalle-acciones">
            <button type="button" id="btn-detalle-editar" class="btn btn-secondary">
                <i class="bi bi-pencil-square"></i> Editar
            </button>
            <button type="button" id="btn-detalle-eliminar" class="btn-detalle-eliminar">
                <i class="bi bi-trash3"></i> Eliminar
            </button>
        </div>
    </div>

    <div class="modal fade" id="modal-reserva" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="bi bi-calendar-check"></i>
                        <span id="modal-reserva-titulo-texto">Nueva reserva</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-reserva">
                        <input type="hidden" id="id_reserva" name="id_reserva">

                        <div class="mb-3">
                            <label class="form-label">Cliente</label>
                            <select id="id_cliente" name="id_cliente" class="form-select system_validador_vacio">
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Servicio / Recurso</label>
                            <select id="id_recurso" name="id_recurso" class="form-select system_validador_vacio">
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Empleado</label>
                            <select id="id_empleado" name="id_empleado" class="form-select">
                                <option value="">Sin asignar</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Fecha</label>
                            <input type="date" id="fecha_reserva" name="fecha_reserva" class="form-control system_validador_vacio">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Hora de inicio</label>
                            <input type="time" id="hora_inicio" name="hora_inicio" class="form-control system_validador_vacio">
                            <small class="texto-ayuda-campo">La hora de fin se calcula según la duración del servicio seleccionado.</small>
                        </div>

                        <div class="mb-3" id="contenedor-estado-reserva" style="display: none;">
                            <label class="form-label">Estado de la reserva</label>
                            <select id="estado_reserva" name="estado_reserva" class="form-select">
                                <option value="pendiente">Pendiente</option>
                                <option value="confirmada">Confirmada</option>
                                <option value="completada">Completada</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notas</label>
                            <textarea id="notas" name="notas" rows="3" class="form-control"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-reserva" class="btn-primario-accento">
                        <i class="bi bi-check2"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    {{-- El bundle principal no trae datos de idioma; el paquete "core" sí los expone por separado. --}}
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/es.global.min.js"></script>
    <script>
        var modoFormularioReserva = 'crear';
        var estadoReservaOriginal = null;
        var reservasDelDia = [];
        var catalogosCargados = false;
        var calendar;
        var eventoEnPanel = null;

        function inicializarTooltips() {
            jQuery('[data-bs-toggle="tooltip"]').each(function () {
                var tooltipExistente = bootstrap.Tooltip.getInstance(this);
                if (tooltipExistente) {
                    tooltipExistente.dispose();
                }
                new bootstrap.Tooltip(this);
            });
        }

        function fechaDeHoy() {
            return formatearFechaISO(new Date());
        }

        function formatearFechaISO(fecha) {
            var mes = String(fecha.getMonth() + 1).padStart(2, '0');
            var dia = String(fecha.getDate()).padStart(2, '0');
            return fecha.getFullYear() + '-' + mes + '-' + dia;
        }

        function formatearFechaLarga(fechaTexto) {
            var partes = fechaTexto.split('-');
            var fecha = new Date(partes[0], partes[1] - 1, partes[2]);
            return fecha.toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' });
        }

        function formatearHora(fechaObj) {
            if (!fechaObj) {
                return '';
            }
            var h = String(fechaObj.getHours()).padStart(2, '0');
            var m = String(fechaObj.getMinutes()).padStart(2, '0');
            return h + ':' + m;
        }

        function escaparTexto(texto) {
            if (texto === null || texto === undefined) {
                return '';
            }
            return jQuery('<div>').text(texto).html();
        }

        function badgeEstadoReserva(estadoReserva) {
            var iconos = {
                pendiente: 'bi-hourglass-split',
                confirmada: 'bi-check-circle-fill',
                completada: 'bi-check2-all',
                cancelada: 'bi-x-circle-fill'
            };
            var etiquetas = {
                pendiente: 'Pendiente',
                confirmada: 'Confirmada',
                completada: 'Completada',
                cancelada: 'Cancelada'
            };
            var icono = iconos[estadoReserva] || 'bi-question-circle';
            var etiqueta = etiquetas[estadoReserva] || estadoReserva;

            return '<span class="badge-reserva badge-reserva-' + estadoReserva + '"><i class="bi ' + icono + '"></i> ' + etiqueta + '</span>';
        }

        function recortarHora(hora) {
            return hora ? hora.substring(0, 5) : '';
        }

        function cargarReservas() {
            var fecha = jQuery('#filtro-fecha').val();

            if (!fecha) {
                return;
            }

            axiosSipleInterno('GET', 'request/reserva/listar', { fecha_inicio: fecha, fecha_fin: fecha }, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    reservasDelDia = respuesta.data.reservas;
                    pintarAgenda(reservasDelDia);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        function pintarAgenda(reservas) {
            var contenedor = jQuery('#contenedor-agenda');
            contenedor.empty();

            if (!reservas || reservas.length === 0) {
                contenedor.html(
                    '<div class="card-elevada mensaje-vacio">' +
                    '<i class="bi bi-calendar-x"></i>' +
                    'No hay reservas para la fecha seleccionada.' +
                    '</div>'
                );
                return;
            }

            // Agrupa las reservas por empleado; las que no tienen empleado van en un grupo aparte.
            var grupos = {};
            var sinAsignar = [];

            reservas.forEach(function (reserva) {
                if (reserva.nombre_empleado) {
                    if (!grupos[reserva.nombre_empleado]) {
                        grupos[reserva.nombre_empleado] = [];
                    }
                    grupos[reserva.nombre_empleado].push(reserva);
                } else {
                    sinAsignar.push(reserva);
                }
            });

            var nombresEmpleados = Object.keys(grupos).sort();

            nombresEmpleados.forEach(function (nombreEmpleado) {
                contenedor.append(construirCardGrupo(nombreEmpleado, grupos[nombreEmpleado], 'bi-person-badge'));
            });

            if (sinAsignar.length > 0) {
                contenedor.append(construirCardGrupo('Sin asignar', sinAsignar, 'bi-person-dash'));
            }

            inicializarTooltips();
        }

        function construirCardGrupo(titulo, reservas, icono) {
            reservas.sort(function (a, b) {
                return a.hora_inicio.localeCompare(b.hora_inicio);
            });

            var html = '<div class="card-elevada card-empleado">' +
                       '<div class="encabezado-empleado">' +
                       '<i class="bi ' + icono + '"></i>' +
                       '<span>' + escaparTexto(titulo) + '</span>' +
                       '<span class="contador-citas">' + reservas.length + (reservas.length === 1 ? ' cita' : ' citas') + '</span>' +
                       '</div>';

            reservas.forEach(function (reserva) {
                var claseCancelada = reserva.estado_reserva === 'cancelada' ? ' reserva-cancelada' : '';

                html += '<div class="linea-reserva' + claseCancelada + '">' +
                        '<span class="horario-reserva">' + recortarHora(reserva.hora_inicio) + ' - ' + recortarHora(reserva.hora_fin) + '</span>' +
                        '<span class="cliente-reserva">' + escaparTexto(reserva.nombre_cliente) + '</span>' +
                        '<span class="recurso-reserva">' + escaparTexto(reserva.nombre_recurso) + '</span>' +
                        badgeEstadoReserva(reserva.estado_reserva) +
                        '<span class="acciones-reserva">' +
                        '<button type="button" class="btn-accion-icono btn-editar-reserva" data-bs-toggle="tooltip" title="Editar" data-id_reserva="' + reserva.id_reserva + '"><i class="bi bi-pencil-square"></i></button>' +
                        '<button type="button" class="btn-accion-icono btn-accion-eliminar btn-eliminar-reserva" data-bs-toggle="tooltip" title="Eliminar" data-id_reserva="' + reserva.id_reserva + '"><i class="bi bi-trash3"></i></button>' +
                        '</span>' +
                        '</div>';
            });

            html += '</div>';

            return html;
        }

        // Carga los catálogos (clientes, recursos, empleados) que alimentan los selects del modal.
        function cargarCatalogos(alTerminar) {
            if (catalogosCargados) {
                if (alTerminar) {
                    alTerminar();
                }
                return;
            }

            axiosSipleInterno('GET', 'request/cliente/listar', {}, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    var selectCliente = jQuery('#id_cliente');
                    respuesta.data.clientes.forEach(function (cliente) {
                        if (cliente.estado == 1) {
                            selectCliente.append(jQuery('<option>').val(cliente.id_cliente).text(cliente.nombre));
                        }
                    });
                }

                axiosSipleInterno('GET', 'request/recurso/listar', {}, {}, false, function (respuestaRecursos) {
                    if (respuestaRecursos.error == 0) {
                        var selectRecurso = jQuery('#id_recurso');
                        respuestaRecursos.data.recursos.forEach(function (recurso) {
                            if (recurso.estado == 1) {
                                selectRecurso.append(
                                    jQuery('<option>').val(recurso.id_recurso).text(recurso.nombre + ' (' + recurso.duracion_minutos + ' min)')
                                );
                            }
                        });
                    }

                    axiosSipleInterno('GET', 'request/empleado/listar', {}, {}, false, function (respuestaEmpleados) {
                        if (respuestaEmpleados.error == 0) {
                            var selectEmpleado = jQuery('#id_empleado');
                            respuestaEmpleados.data.empleados.forEach(function (empleado) {
                                if (empleado.estado == 1) {
                                    selectEmpleado.append(jQuery('<option>').val(empleado.id_empleado).text(empleado.nombre));
                                }
                            });
                        }

                        catalogosCargados = true;

                        if (alTerminar) {
                            alTerminar();
                        }
                    });
                });
            });
        }

        function limpiarFormularioReserva() {
            jQuery('#id_reserva').val('');
            jQuery('#id_cliente').val('');
            jQuery('#id_recurso').val('');
            jQuery('#id_empleado').val('');
            jQuery('#fecha_reserva').val('');
            jQuery('#hora_inicio').val('');
            jQuery('#estado_reserva').val('pendiente');
            jQuery('#notas').val('');
            jQuery('#contenedor-form-reserva .input_vacio').removeClass('input_vacio');
            jQuery('#contenedor-form-reserva #system_validador').remove();
        }

        // Punto único para abrir el modal en modo "crear", con fecha (y opcionalmente hora) prellenadas.
        function abrirFormularioNuevaReserva(fechaTexto, horaTexto) {
            cargarCatalogos(function () {
                modoFormularioReserva = 'crear';
                estadoReservaOriginal = null;
                jQuery('#modal-reserva-titulo-texto').text('Nueva reserva');
                limpiarFormularioReserva();
                jQuery('#contenedor-estado-reserva').hide();
                jQuery('#fecha_reserva').val(fechaTexto || jQuery('#filtro-fecha').val());

                if (horaTexto) {
                    jQuery('#hora_inicio').val(horaTexto);
                }

                var modalReserva = new bootstrap.Modal(document.getElementById('modal-reserva'));
                modalReserva.show();
            });
        }

        // Punto único para abrir el modal en modo "editar", a partir de un objeto plano
        // con los mismos campos sin importar si viene de la agenda o del panel del calendario.
        function abrirEdicionReserva(datos) {
            cargarCatalogos(function () {
                modoFormularioReserva = 'editar';
                jQuery('#modal-reserva-titulo-texto').text('Editar reserva');
                limpiarFormularioReserva();
                jQuery('#contenedor-estado-reserva').show();

                jQuery('#id_reserva').val(datos.id_reserva);
                jQuery('#fecha_reserva').val(datos.fecha_reserva);
                jQuery('#hora_inicio').val(datos.hora_inicio);
                jQuery('#notas').val(datos.notas);
                jQuery('#estado_reserva').val(datos.estado_reserva);

                estadoReservaOriginal = datos.estado_reserva;

                jQuery('#id_cliente').val(datos.id_cliente);
                jQuery('#id_recurso').val(datos.id_recurso);
                jQuery('#id_empleado').val(datos.id_empleado ? datos.id_empleado : '');

                var modalReserva = new bootstrap.Modal(document.getElementById('modal-reserva'));
                modalReserva.show();
            });
        }

        // Confirmación + eliminación reutilizada tanto por la agenda como por el panel del calendario.
        function eliminarReserva(idReserva, alTerminar) {
            Swal.fire({
                title: '¿Eliminar reserva?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                background: colorVariable('--bg-card'),
                color: colorVariable('--text-primary'),
                confirmButtonColor: colorVariable('--accent'),
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(function (resultado) {
                if (!resultado.isConfirmed) {
                    return;
                }

                axiosSipleInterno('POST', 'request/reserva/eliminar', {}, { id_reserva: idReserva }, true, function (respuesta) {
                    if (respuesta.error == 0) {
                        notificarUsuario('Reserva eliminada correctamente', 'success');
                        refrescarVista();

                        if (alTerminar) {
                            alTerminar();
                        }
                    } else {
                        notificarUsuario(respuesta.mensaje, 'error');
                    }
                });
            });
        }

        jQuery('#btn-nueva-reserva').on('click', function () {
            abrirFormularioNuevaReserva(jQuery('#filtro-fecha').val(), null);
        });

        jQuery('#contenedor-agenda').on('click', '.btn-editar-reserva', function () {
            var idReserva = jQuery(this).data('id_reserva');
            var reserva = reservasDelDia.find(function (item) {
                return item.id_reserva == idReserva;
            });

            if (!reserva) {
                return;
            }

            abrirEdicionReserva(reserva);
        });

        jQuery('#contenedor-agenda').on('click', '.btn-eliminar-reserva', function () {
            eliminarReserva(jQuery(this).data('id_reserva'));
        });

        jQuery('#btn-guardar-reserva').on('click', function () {
            if (!system_validarcampos('contenedor-form-reserva', 1)) {
                return;
            }

            var datos = getDataJson('contenedor-form-reserva');

            var url = modoFormularioReserva === 'crear' ? 'request/reserva/crear' : 'request/reserva/editar';

            axiosSipleInterno('POST', url, {}, datos, true, function (respuesta) {
                if (respuesta.error != 0) {
                    // Se muestra el mensaje tal como llega del backend (por ejemplo, el choque de horario).
                    notificarUsuario(respuesta.mensaje, 'error');
                    return;
                }

                // En edición, si además cambió el estado de la reserva, se envía en una
                // segunda llamada al endpoint dedicado de cambio de estado.
                if (modoFormularioReserva === 'editar' && datos.estado_reserva !== estadoReservaOriginal) {
                    axiosSipleInterno('POST', 'request/reserva/cambiar-estado', {}, {
                        id_reserva: datos.id_reserva,
                        estado_reserva: datos.estado_reserva
                    }, false, function (respuestaEstado) {
                        cerrarModalReserva();

                        if (respuestaEstado.error == 0) {
                            notificarUsuario('Reserva actualizada correctamente', 'success');
                        } else {
                            notificarUsuario(respuestaEstado.mensaje, 'error');
                        }

                        refrescarVista();
                    });

                    return;
                }

                cerrarModalReserva();
                refrescarVista();
                avisarGuardado(modoFormularioReserva === 'crear' ? 'Reserva creada correctamente' : 'Reserva actualizada correctamente');
            });
        });

        function cerrarModalReserva() {
            var modalReserva = bootstrap.Modal.getInstance(document.getElementById('modal-reserva'));
            if (modalReserva) {
                modalReserva.hide();
            }
        }

        // Tras crear, editar, eliminar o cambiar de estado hay que refrescar dos cosas:
        // los eventos del calendario y la agenda del día que esté abierto.
        function refrescarVista() {
            cargarReservas();
            if (calendar) {
                calendar.refetchEvents();
            }
        }

        function seleccionarDia(fechaTexto) {
            jQuery('#filtro-fecha').val(fechaTexto);
            jQuery('#fecha-panel-agenda').text(formatearFechaLarga(fechaTexto));
            cargarReservas();
        }

        /* ================= CALENDARIO (FullCalendar) ================= */

        // Colores por estado: el backend no puede leer variables CSS, así que se
        // resuelven aquí (según el tema/acento activos) y se mandan como query string.
        function coloresPorEstado() {
            return {
                pendiente: { fondo: colorVariable('--warning-soft'), borde: colorVariable('--warning') },
                confirmada: { fondo: colorVariable('--accent-soft'), borde: colorVariable('--accent') },
                completada: { fondo: colorVariable('--success-soft'), borde: colorVariable('--success') },
                cancelada: { fondo: colorVariable('--danger-soft'), borde: colorVariable('--danger') }
            };
        }

        // El fondo de los eventos es siempre un pastel claro (las variantes -soft),
        // así que el texto necesita un color oscuro fijo para leerse bien encima,
        // incluso en tema oscuro donde el resto del texto del panel es claro.
        function colorTextoEventosFijo() {
            // "body.modo-claro" solo puede matchear la etiqueta <body> real (no un
            // <div> aparte), así que se agrega esa clase al body de verdad por un
            // instante para leer su --text-primary, y se retira antes de que el
            // navegador llegue a pintar nada (todo ocurre en el mismo tick).
            var body = document.body;
            var teniaModoClaro = body.classList.contains('modo-claro');

            if (!teniaModoClaro) {
                body.classList.add('modo-claro');
            }

            var color = getComputedStyle(body).getPropertyValue('--text-primary').trim();

            if (!teniaModoClaro) {
                body.classList.remove('modo-claro');
            }

            return color || '#3a2b28';
        }

        function separarTituloEvento(titulo) {
            var indice = titulo.indexOf(' - ');
            if (indice === -1) {
                return { cliente: titulo, servicio: '' };
            }
            return { cliente: titulo.substring(0, indice), servicio: titulo.substring(indice + 3) };
        }

        function inicializarCalendario() {
            var contenedor = document.getElementById('calendario-reservas');

            // FullCalendar no aplica de forma confiable su opción "eventTextColor"
            // sobre los eventos en bloque de dayGrid/timeGrid, así que el color fijo
            // se fuerza con una variable CSS propia (ver regla ".fc-event" arriba).
            contenedor.style.setProperty('--color-texto-evento', colorTextoEventosFijo());

            // El bundle global (index.global.min.js) trae ya incluidos y auto-registrados
            // los plugins gratuitos dayGrid, timeGrid, interaction y list (MIT); no expone
            // esos plugins como propiedades nombradas, así que no hace falta (ni es posible)
            // listarlos en un array "plugins". No se usa ningún plugin Premium/Scheduler.
            calendar = new FullCalendar.Calendar(contenedor, {
                initialView: 'dayGridMonth',
                locale: 'es',
                firstDay: 1,
                height: 700,
                dayMaxEvents: true,
                slotMinTime: '06:00:00',
                slotMaxTime: '22:00:00',
                // Por defecto, la vista mensual pinta los eventos como un punto +
                // texto (sin relleno). Se fuerza a bloque para que el color pastel
                // de fondo sea visible en las 3 vistas, no solo en semana/día.
                eventDisplay: 'block',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: function (fetchInfo, successCallback, failureCallback) {
                    var colores = coloresPorEstado();

                    axiosSipleInterno('GET', 'request/reserva/listar-calendario', {
                        fecha_inicio: fetchInfo.startStr.substring(0, 10),
                        fecha_fin: fetchInfo.endStr.substring(0, 10),
                        fondo_pendiente: colores.pendiente.fondo,
                        borde_pendiente: colores.pendiente.borde,
                        fondo_confirmada: colores.confirmada.fondo,
                        borde_confirmada: colores.confirmada.borde,
                        fondo_completada: colores.completada.fondo,
                        borde_completada: colores.completada.borde,
                        fondo_cancelada: colores.cancelada.fondo,
                        borde_cancelada: colores.cancelada.borde
                    }, {}, false, function (respuesta) {
                        if (respuesta.error == 0) {
                            successCallback(respuesta.data.eventos);
                        } else {
                            notificarUsuario(respuesta.mensaje, 'error');
                            failureCallback(respuesta.mensaje);
                        }
                    });
                },
                dateClick: function (info) {
                    var fechaTexto = info.dateStr.substring(0, 10);
                    var horaTexto = (!info.allDay && info.dateStr.length > 10) ? info.dateStr.substring(11, 16) : null;

                    seleccionarDia(fechaTexto);
                    abrirFormularioNuevaReserva(fechaTexto, horaTexto);
                },
                eventClick: function (info) {
                    mostrarPanelDetalle(info);
                },
                datesSet: function () {
                    cerrarPanelDetalle();
                }
            });

            calendar.render();
        }

        /* ================= PANEL DE DETALLE DE UN EVENTO ================= */

        function mostrarPanelDetalle(info) {
            var evento = info.event;
            var props = evento.extendedProps;
            var partesTitulo = separarTituloEvento(evento.title);

            eventoEnPanel = evento;

            jQuery('#detalle-titulo-cliente').text(partesTitulo.cliente);
            jQuery('#detalle-servicio').text(partesTitulo.servicio);
            jQuery('#detalle-telefono').text(props.telefono_cliente || 'Sin teléfono registrado');
            jQuery('#detalle-empleado').text(props.nombre_empleado || 'Sin asignar');
            jQuery('#detalle-horario').text(formatearHora(evento.start) + ' - ' + formatearHora(evento.end));

            if (props.notas) {
                jQuery('#detalle-notas').text(props.notas);
                jQuery('#fila-detalle-notas').show();
            } else {
                jQuery('#fila-detalle-notas').hide();
            }

            marcarChipActivo(props.estado_reserva);

            jQuery('#panel-detalle-evento').addClass('visible');
            posicionarPanelDetalle(info.jsEvent);
        }

        function marcarChipActivo(estado) {
            jQuery('#panel-detalle-evento .chip-estado').removeClass('activo');
            jQuery('#panel-detalle-evento .chip-estado[data-estado="' + estado + '"]').addClass('activo');
        }

        function posicionarPanelDetalle(jsEvent) {
            var panel = document.getElementById('panel-detalle-evento');
            var margen = 14;
            var x = jsEvent ? jsEvent.clientX : window.innerWidth / 2;
            var y = jsEvent ? jsEvent.clientY : window.innerHeight / 2;

            panel.style.left = x + 'px';
            panel.style.top = y + 'px';

            // Se posiciona primero para poder medir su tamaño real y ajustarlo
            // después, de modo que nunca quede fuera de la pantalla.
            var rect = panel.getBoundingClientRect();
            var maxX = window.innerWidth - rect.width - margen;
            var maxY = window.innerHeight - rect.height - margen;

            panel.style.left = Math.max(margen, Math.min(x, maxX)) + 'px';
            panel.style.top = Math.max(margen, Math.min(y, maxY)) + 'px';
        }

        function cerrarPanelDetalle() {
            jQuery('#panel-detalle-evento').removeClass('visible');
            eventoEnPanel = null;
        }

        jQuery('#btn-cerrar-detalle').on('click', cerrarPanelDetalle);

        // Clic fuera del panel (y fuera de un evento del calendario) lo cierra.
        jQuery(document).on('click', function (e) {
            if (!jQuery('#panel-detalle-evento').hasClass('visible')) {
                return;
            }
            if (jQuery(e.target).closest('#panel-detalle-evento').length === 0 && jQuery(e.target).closest('.fc-event').length === 0) {
                cerrarPanelDetalle();
            }
        });

        jQuery('#panel-detalle-evento').on('click', '.chip-estado', function () {
            var chip = jQuery(this);

            if (chip.hasClass('activo') || !eventoEnPanel) {
                return;
            }

            var nuevoEstado = chip.data('estado');
            var idReserva = eventoEnPanel.id;

            axiosSipleInterno('POST', 'request/reserva/cambiar-estado', {}, {
                id_reserva: idReserva,
                estado_reserva: nuevoEstado
            }, true, function (respuesta) {
                if (respuesta.error == 0) {
                    marcarChipActivo(nuevoEstado);
                    eventoEnPanel.setExtendedProp('estado_reserva', nuevoEstado);
                    calendar.refetchEvents();
                    cargarReservas();
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        jQuery('#btn-detalle-editar').on('click', function () {
            if (!eventoEnPanel) {
                return;
            }

            var props = eventoEnPanel.extendedProps;

            var datos = {
                id_reserva: eventoEnPanel.id,
                id_cliente: props.id_cliente,
                id_recurso: props.id_recurso,
                id_empleado: props.id_empleado,
                fecha_reserva: formatearFechaISO(eventoEnPanel.start),
                hora_inicio: formatearHora(eventoEnPanel.start),
                notas: props.notas,
                estado_reserva: props.estado_reserva
            };

            cerrarPanelDetalle();
            abrirEdicionReserva(datos);
        });

        jQuery('#btn-detalle-eliminar').on('click', function () {
            if (!eventoEnPanel) {
                return;
            }

            eliminarReserva(eventoEnPanel.id, cerrarPanelDetalle);
        });

        jQuery(document).ready(function () {
            jQuery('#filtro-fecha').val(fechaDeHoy());

            inicializarCalendario();
            seleccionarDia(fechaDeHoy());

            iniciarGuiaSiCorresponde('reserva', function () {
                iniciarTourContextual('reserva', [
                    {
                        attachTo: { element: '#calendario-reservas', on: 'top' },
                        title: 'Tu calendario de reservas',
                        text: 'Haz clic en un día para crear tu primera reserva ahí mismo.'
                    }
                ]);
            });
        });
    </script>
@endsection
