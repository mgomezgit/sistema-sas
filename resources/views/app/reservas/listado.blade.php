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

        .barra-fecha {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }

        .barra-fecha label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 0;
        }

        .barra-fecha input[type="date"] {
            max-width: 220px;
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

    <div class="barra-fecha">
        <label for="filtro-fecha">Ver día:</label>
        <input type="date" id="filtro-fecha" class="form-control">
    </div>

    <div id="contenedor-agenda"></div>

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
                            cargarReservas();
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

                        cargarReservas();
                    });

                    return;
                }

                cerrarModalReserva();
                notificarUsuario(modoFormularioReserva === 'crear' ? 'Reserva creada correctamente' : 'Reserva actualizada correctamente', 'success');
                cargarReservas();
            });
        });

        function cerrarModalReserva() {
            var modalReserva = bootstrap.Modal.getInstance(document.getElementById('modal-reserva'));
            if (modalReserva) {
                modalReserva.hide();
            }
        }

        jQuery('#filtro-fecha').on('change', function () {
            cargarReservas();
        });

        jQuery(document).ready(function () {
            jQuery('#filtro-fecha').val(fechaDeHoy());
            cargarReservas();
        });
    </script>
@endsection
