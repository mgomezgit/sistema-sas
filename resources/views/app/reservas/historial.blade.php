@extends('layout.backoffice')

@section('title', 'Historial de Reservas')

@section('estilos')
    <style>
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
            max-width: 560px;
        }

        .card-tabla {
            padding: 0;
            overflow: hidden;
        }

        .card-tabla .card-tabla-body {
            padding: 1.25rem 1.5rem;
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

        .acciones-filtros {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
    </style>
@endsection

@section('content')
    <div class="mb-4">
        <h2 class="titulo-pagina">Historial de Reservas</h2>
        <p class="subtitulo-pagina">Consulta y filtra todas las reservas de tu negocio.</p>
    </div>

    <div class="card-elevada mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Cliente</label>
                <select id="filtro-cliente" class="form-select">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select id="filtro-estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="confirmada">Confirmada</option>
                    <option value="completada">Completada</option>
                    <option value="cancelada">Cancelada</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" id="filtro-desde" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" id="filtro-hasta" class="form-control">
            </div>
            <div class="col-md-2">
                <div class="acciones-filtros">
                    <button type="button" id="btn-filtrar" class="btn-primario-accento">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button type="button" id="btn-limpiar-filtros" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i> Limpiar filtros
            </button>
        </div>
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <table id="tabla-historial" class="table table-striped align-middle w-100 fila-tabla-hover">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Horario</th>
                        <th>Cliente</th>
                        <th>Servicio</th>
                        <th>Empleado</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        var tablaHistorial;

        function recortarHora(hora) {
            return hora ? hora.substring(0, 5) : '';
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

        function cargarClientesFiltro() {
            axiosSipleInterno('GET', 'request/cliente/listar', {}, {}, false, function (respuesta) {
                if (respuesta.error != 0) {
                    return;
                }

                var selectCliente = jQuery('#filtro-cliente');
                respuesta.data.clientes.forEach(function (cliente) {
                    selectCliente.append(jQuery('<option>').val(cliente.id_cliente).text(cliente.nombre));
                });
            });
        }

        function cargarHistorial() {
            // Solo se envían los filtros con valor: los vacíos se omiten.
            var parametros = {};

            if (jQuery('#filtro-cliente').val()) {
                parametros.id_cliente = jQuery('#filtro-cliente').val();
            }

            if (jQuery('#filtro-estado').val()) {
                parametros.estado_reserva = jQuery('#filtro-estado').val();
            }

            var desde = jQuery('#filtro-desde').val();
            var hasta = jQuery('#filtro-hasta').val();

            // El rango de fechas requiere ambos extremos para aplicarse.
            if (desde && hasta) {
                parametros.fecha_inicio = desde;
                parametros.fecha_fin = hasta;
            }

            axiosSipleInterno('GET', 'request/reserva/listar', parametros, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarHistorial(respuesta.data.reservas);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        function pintarHistorial(reservas) {
            if (tablaHistorial) {
                tablaHistorial.destroy();
                jQuery('#tabla-historial tbody').empty();
            }

            tablaHistorial = jQuery('#tabla-historial').DataTable({
                data: reservas,
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.11/i18n/es-ES.json' },
                order: [[0, 'desc']],
                columns: [
                    { data: 'fecha_reserva' },
                    {
                        data: null,
                        orderable: false,
                        render: function (fila) {
                            return recortarHora(fila.hora_inicio) + ' - ' + recortarHora(fila.hora_fin);
                        }
                    },
                    { data: 'nombre_cliente' },
                    { data: 'nombre_recurso' },
                    {
                        data: 'nombre_empleado',
                        render: function (data) {
                            return data ? data : '<span class="text-muted">Sin asignar</span>';
                        }
                    },
                    {
                        data: 'estado_reserva',
                        render: function (data) {
                            return badgeEstadoReserva(data);
                        }
                    }
                ]
            });
        }

        jQuery('#btn-filtrar').on('click', function () {
            var desde = jQuery('#filtro-desde').val();
            var hasta = jQuery('#filtro-hasta').val();

            if ((desde && !hasta) || (!desde && hasta)) {
                notificarUsuario('Para filtrar por fechas debes indicar tanto la fecha desde como la fecha hasta.', 'warning');
                return;
            }

            if (desde && hasta && hasta < desde) {
                notificarUsuario('La fecha "hasta" no puede ser anterior a la fecha "desde".', 'warning');
                return;
            }

            cargarHistorial();
        });

        jQuery('#btn-limpiar-filtros').on('click', function () {
            jQuery('#filtro-cliente').val('');
            jQuery('#filtro-estado').val('');
            jQuery('#filtro-desde').val('');
            jQuery('#filtro-hasta').val('');
            cargarHistorial();
        });

        jQuery(document).ready(function () {
            cargarClientesFiltro();
            cargarHistorial();
        });
    </script>
@endsection
