@extends('layout.backoffice')

@section('title', 'Reservas')

@section('estilos')
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

        /* ---------- Calendario mensual ---------- */
        .cabecera-calendario {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1.1rem;
        }

        .titulo-mes {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.15rem;
            text-transform: capitalize;
            min-width: 190px;
        }

        .btn-nav-mes {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            background-color: transparent;
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition-base);
        }

        .btn-nav-mes:hover {
            background-color: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
        }

        .btn-hoy {
            margin-left: auto;
            border: 1px solid var(--border-color);
            background-color: transparent;
            color: var(--text-secondary);
            border-radius: var(--radius-sm);
            padding: 0.35rem 0.85rem;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition-base);
        }

        .btn-hoy:hover {
            background-color: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
        }

        .nombres-dias,
        .cuadricula-dias {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.4rem;
        }

        .nombres-dias span {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding-bottom: 0.5rem;
        }

        .celda-dia {
            position: relative;
            min-height: 62px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background-color: var(--bg-input);
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            padding: 0.4rem;
            cursor: pointer;
            transition: var(--transition-base);
            font-size: 0.92rem;
        }

        .celda-dia:hover {
            background-color: var(--bg-card-hover);
            border-color: var(--border-color-strong);
        }

        .celda-dia.vacia {
            background-color: transparent;
            border-color: transparent;
            cursor: default;
        }

        .celda-dia.hoy {
            border-color: var(--accent);
            border-width: 2px;
            font-weight: 700;
        }

        .celda-dia.seleccionada {
            background-color: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
            font-weight: 700;
        }

        .celda-dia.sin-atencion {
            opacity: 0.45;
        }

        .contador-reservas {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 18px;
            padding: 0 0.35rem;
            border-radius: 999px;
            background-color: var(--accent);
            color: var(--text-sobre-accent);
            font-size: 0.68rem;
            font-weight: 700;
            line-height: 1;
        }

        /* ---------- Panel del día ---------- */
        .panel-dia {
            display: none;
        }

        .panel-dia.abierto {
            display: block;
            animation: aparecerPanel 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes aparecerPanel {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

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

        .lista-franjas {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .btn-franja {
            border: 1px solid var(--border-color);
            background-color: var(--bg-input);
            color: var(--text-primary);
            border-radius: var(--radius-sm);
            padding: 0.45rem 0.8rem;
            font-size: 0.86rem;
            font-weight: 600;
            transition: var(--transition-base);
        }

        .btn-franja:hover {
            background-color: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
        }

        .aviso-sin-atencion {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.85rem 1rem;
        }

        .aviso-sin-atencion i {
            color: var(--warning);
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

    <div class="card-elevada calendario-mes mb-4">
        <div class="cabecera-calendario">
            <button type="button" id="btn-mes-anterior" class="btn-nav-mes" aria-label="Mes anterior">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div class="titulo-mes" id="titulo-mes">—</div>
            <button type="button" id="btn-mes-siguiente" class="btn-nav-mes" aria-label="Mes siguiente">
                <i class="bi bi-chevron-right"></i>
            </button>
            <button type="button" id="btn-hoy" class="btn-hoy">Hoy</button>
        </div>

        <div class="nombres-dias">
            <span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span>
        </div>

        <div class="cuadricula-dias" id="cuadricula-dias"></div>
    </div>

    <div id="panel-dia" class="panel-dia">
        <div class="card-elevada mb-4">
            <div class="titulo-bloque-dia">
                <i class="bi bi-plus-circle"></i>
                Crear nueva reserva
                <span class="fecha-panel" id="fecha-panel-crear"></span>
            </div>
            <div id="franjas-horarias"></div>
        </div>

        <div class="titulo-bloque-dia mb-3">
            <i class="bi bi-calendar-event"></i>
            Reservas de este día
        </div>

        <div id="contenedor-agenda"></div>
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
    <script>
        var modoFormularioReserva = 'crear';
        var estadoReservaOriginal = null;
        var reservasDelDia = [];
        var catalogosCargados = false;

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
            var hoy = new Date();
            var mes = String(hoy.getMonth() + 1).padStart(2, '0');
            var dia = String(hoy.getDate()).padStart(2, '0');
            return hoy.getFullYear() + '-' + mes + '-' + dia;
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

        jQuery('#btn-nueva-reserva').on('click', function () {
            cargarCatalogos(function () {
                modoFormularioReserva = 'crear';
                estadoReservaOriginal = null;
                jQuery('#modal-reserva-titulo-texto').text('Nueva reserva');
                limpiarFormularioReserva();
                jQuery('#contenedor-estado-reserva').hide();
                jQuery('#fecha_reserva').val(jQuery('#filtro-fecha').val());

                var modalReserva = new bootstrap.Modal(document.getElementById('modal-reserva'));
                modalReserva.show();
            });
        });

        jQuery('#contenedor-agenda').on('click', '.btn-editar-reserva', function () {
            var idReserva = jQuery(this).data('id_reserva');
            var reserva = reservasDelDia.find(function (item) {
                return item.id_reserva == idReserva;
            });

            if (!reserva) {
                return;
            }

            cargarCatalogos(function () {
                modoFormularioReserva = 'editar';
                jQuery('#modal-reserva-titulo-texto').text('Editar reserva');
                limpiarFormularioReserva();
                jQuery('#contenedor-estado-reserva').show();

                jQuery('#id_reserva').val(reserva.id_reserva);
                jQuery('#fecha_reserva').val(reserva.fecha_reserva);
                jQuery('#hora_inicio').val(reserva.hora_inicio);
                jQuery('#notas').val(reserva.notas);
                jQuery('#estado_reserva').val(reserva.estado_reserva);

                estadoReservaOriginal = reserva.estado_reserva;

                // El listado trae los ids del cliente, recurso y empleado, así que
                // cada select se preselecciona haciendo match por el value de la opción.
                jQuery('#id_cliente').val(reserva.id_cliente);
                jQuery('#id_recurso').val(reserva.id_recurso);
                jQuery('#id_empleado').val(reserva.id_empleado ? reserva.id_empleado : '');

                var modalReserva = new bootstrap.Modal(document.getElementById('modal-reserva'));
                modalReserva.show();
            });
        });

        jQuery('#contenedor-agenda').on('click', '.btn-eliminar-reserva', function () {
            var idReserva = jQuery(this).data('id_reserva');

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
                if (resultado.isConfirmed) {
                    axiosSipleInterno('POST', 'request/reserva/eliminar', {}, { id_reserva: idReserva }, true, function (respuesta) {
                        if (respuesta.error == 0) {
                            notificarUsuario('Reserva eliminada correctamente', 'success');
                            refrescarVista();
                        } else {
                            notificarUsuario(respuesta.mensaje, 'error');
                        }
                    });
                }
            });
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
                notificarUsuario(modoFormularioReserva === 'crear' ? 'Reserva creada correctamente' : 'Reserva actualizada correctamente', 'success');
                refrescarVista();
            });
        });

        function cerrarModalReserva() {
            var modalReserva = bootstrap.Modal.getInstance(document.getElementById('modal-reserva'));
            if (modalReserva) {
                modalReserva.hide();
            }
        }

        /* Tras crear, editar o eliminar hay que refrescar dos cosas: la agenda del
           día abierto y los contadores del calendario. */
        function refrescarVista() {
            cargarReservas();
            cargarReservasDelMes(pintarCalendario);
        }

        /* ================= CALENDARIO MENSUAL ================= */

        var mesVisible;                 // primer día del mes que se está mostrando
        var reservasPorFecha = {};      // { 'YYYY-MM-DD': cantidad }
        var horarioNegocio = null;      // { dias_atencion, hora_apertura, hora_cierre }

        var NOMBRES_MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                             'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        function aTextoFecha(fecha) {
            var mes = String(fecha.getMonth() + 1).padStart(2, '0');
            var dia = String(fecha.getDate()).padStart(2, '0');
            return fecha.getFullYear() + '-' + mes + '-' + dia;
        }

        // Día de la semana en el formato del negocio: 1=lunes ... 7=domingo.
        function diaSemanaNegocio(fecha) {
            var dia = fecha.getDay();
            return dia === 0 ? 7 : dia;
        }

        function diasAtencionArray() {
            if (!horarioNegocio || !horarioNegocio.dias_atencion) {
                return null; // sin horario configurado no se restringe ningún día
            }
            return String(horarioNegocio.dias_atencion).split(',').map(function (d) {
                return parseInt(jQuery.trim(d), 10);
            });
        }

        function negocioAtiende(fecha) {
            var dias = diasAtencionArray();
            return dias === null ? true : dias.indexOf(diaSemanaNegocio(fecha)) !== -1;
        }

        // Trae las reservas del mes completo para pintar los indicadores.
        function cargarReservasDelMes(alTerminar) {
            var primero = new Date(mesVisible.getFullYear(), mesVisible.getMonth(), 1);
            var ultimo = new Date(mesVisible.getFullYear(), mesVisible.getMonth() + 1, 0);

            axiosSipleInterno('GET', 'request/reserva/listar', {
                fecha_inicio: aTextoFecha(primero),
                fecha_fin: aTextoFecha(ultimo)
            }, {}, false, function (respuesta) {
                reservasPorFecha = {};

                if (respuesta.error == 0) {
                    respuesta.data.reservas.forEach(function (reserva) {
                        var f = reserva.fecha_reserva;
                        reservasPorFecha[f] = (reservasPorFecha[f] || 0) + 1;
                    });
                }

                if (alTerminar) {
                    alTerminar();
                }
            });
        }

        function pintarCalendario() {
            var anio = mesVisible.getFullYear();
            var mes = mesVisible.getMonth();

            jQuery('#titulo-mes').text(NOMBRES_MESES[mes] + ' ' + anio);

            var primerDia = new Date(anio, mes, 1);
            var diasEnMes = new Date(anio, mes + 1, 0).getDate();
            // Cuántas celdas vacías van antes del día 1 (semana empieza en lunes).
            var desplazamiento = diaSemanaNegocio(primerDia) - 1;

            var hoy = aTextoFecha(new Date());
            var seleccionada = jQuery('#filtro-fecha').val();
            var cuadricula = jQuery('#cuadricula-dias');
            cuadricula.empty();

            for (var i = 0; i < desplazamiento; i++) {
                cuadricula.append('<div class="celda-dia vacia"></div>');
            }

            for (var dia = 1; dia <= diasEnMes; dia++) {
                var fecha = new Date(anio, mes, dia);
                var texto = aTextoFecha(fecha);
                var clases = 'celda-dia';

                if (texto === hoy) { clases += ' hoy'; }
                if (texto === seleccionada) { clases += ' seleccionada'; }
                if (!negocioAtiende(fecha)) { clases += ' sin-atencion'; }

                var cantidad = reservasPorFecha[texto] || 0;
                var indicador = cantidad > 0
                    ? '<span class="contador-reservas">' + cantidad + '</span>'
                    : '';

                cuadricula.append(
                    '<div class="' + clases + '" data-fecha="' + texto + '">' +
                    '<span>' + dia + '</span>' + indicador +
                    '</div>'
                );
            }
        }

        function pintarFranjas(fechaTexto) {
            var contenedor = jQuery('#franjas-horarias');
            contenedor.empty();

            var partes = fechaTexto.split('-');
            var fecha = new Date(partes[0], partes[1] - 1, partes[2]);

            if (!negocioAtiende(fecha)) {
                contenedor.html(
                    '<div class="aviso-sin-atencion">' +
                    '<i class="bi bi-exclamation-triangle"></i>' +
                    'El negocio no atiende este día.' +
                    '</div>'
                );
                return;
            }

            var apertura = (horarioNegocio && horarioNegocio.hora_apertura) ? horarioNegocio.hora_apertura : null;
            var cierre = (horarioNegocio && horarioNegocio.hora_cierre) ? horarioNegocio.hora_cierre : null;

            if (!apertura || !cierre) {
                contenedor.html(
                    '<div class="aviso-sin-atencion">' +
                    '<i class="bi bi-exclamation-triangle"></i>' +
                    'Aún no has definido el horario de atención. Configúralo en Configuración del negocio.' +
                    '</div>'
                );
                return;
            }

            // Franjas de 30 minutos entre apertura y cierre.
            var minutosInicio = parseInt(apertura.substring(0, 2), 10) * 60 + parseInt(apertura.substring(3, 5), 10);
            var minutosFin = parseInt(cierre.substring(0, 2), 10) * 60 + parseInt(cierre.substring(3, 5), 10);

            var html = '<div class="lista-franjas">';

            for (var m = minutosInicio; m < minutosFin; m += 30) {
                var hh = String(Math.floor(m / 60)).padStart(2, '0');
                var mm = String(m % 60).padStart(2, '0');
                var hora = hh + ':' + mm;
                html += '<button type="button" class="btn-franja" data-hora="' + hora + ':00">' + hora + '</button>';
            }

            html += '</div>';
            contenedor.html(html);
        }

        function seleccionarDia(fechaTexto) {
            jQuery('#filtro-fecha').val(fechaTexto);

            jQuery('.celda-dia').removeClass('seleccionada');
            jQuery('.celda-dia[data-fecha="' + fechaTexto + '"]').addClass('seleccionada');

            var partes = fechaTexto.split('-');
            var fecha = new Date(partes[0], partes[1] - 1, partes[2]);
            jQuery('#fecha-panel-crear').text(
                fecha.getDate() + ' de ' + NOMBRES_MESES[fecha.getMonth()].toLowerCase() + ' de ' + fecha.getFullYear()
            );

            pintarFranjas(fechaTexto);
            jQuery('#panel-dia').addClass('abierto');

            cargarReservas();
        }

        function cambiarMes(salto) {
            mesVisible = new Date(mesVisible.getFullYear(), mesVisible.getMonth() + salto, 1);
            cargarReservasDelMes(pintarCalendario);
        }

        jQuery('#btn-mes-anterior').on('click', function () { cambiarMes(-1); });
        jQuery('#btn-mes-siguiente').on('click', function () { cambiarMes(1); });

        jQuery('#btn-hoy').on('click', function () {
            var hoy = new Date();
            mesVisible = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
            cargarReservasDelMes(function () {
                pintarCalendario();
                seleccionarDia(fechaDeHoy());
            });
        });

        jQuery('#cuadricula-dias').on('click', '.celda-dia:not(.vacia)', function () {
            seleccionarDia(jQuery(this).data('fecha'));
        });

        // Al elegir una franja se abre el modal existente ya prellenado.
        jQuery('#franjas-horarias').on('click', '.btn-franja', function () {
            var hora = jQuery(this).data('hora');

            cargarCatalogos(function () {
                modoFormularioReserva = 'crear';
                estadoReservaOriginal = null;
                jQuery('#modal-reserva-titulo-texto').text('Nueva reserva');
                limpiarFormularioReserva();
                jQuery('#contenedor-estado-reserva').hide();

                jQuery('#fecha_reserva').val(jQuery('#filtro-fecha').val());
                jQuery('#hora_inicio').val(hora);

                var modalReserva = new bootstrap.Modal(document.getElementById('modal-reserva'));
                modalReserva.show();
            });
        });

        jQuery(document).ready(function () {
            var hoy = new Date();
            mesVisible = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
            jQuery('#filtro-fecha').val(fechaDeHoy());

            // El horario se consulta una sola vez: define las franjas y los días hábiles.
            axiosSipleInterno('GET', 'request/negocio/horario', {}, {}, true, function (respuesta) {
                horarioNegocio = (respuesta.error == 0) ? respuesta.data.horario : null;

                cargarReservasDelMes(function () {
                    pintarCalendario();
                    seleccionarDia(fechaDeHoy());
                });
            });
        });
    </script>
@endsection
