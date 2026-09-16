@extends('layout.backoffice')

@section('title', 'Reporte de Ventas')

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

        .acciones-filtros {
            display: flex;
            align-items: flex-end;
            height: 100%;
            gap: 0.5rem;
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

        .badge-reserva-pendiente { background-color: var(--warning-soft); color: var(--warning); }
        .badge-reserva-confirmada { background-color: var(--accent-soft); color: var(--accent); }
        .badge-reserva-completada { background-color: var(--success-soft); color: var(--success); }
        .badge-reserva-cancelada { background-color: var(--danger-soft); color: var(--danger); }

        .resumen-reporte {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .resumen-reporte .dato-resumen .etiqueta {
            color: var(--text-secondary);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .resumen-reporte .dato-resumen .valor {
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 700;
        }
    </style>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="titulo-pagina">Reporte de Ventas</h2>
            <p class="subtitulo-pagina">Detalle de las reservas de tu negocio en el rango de fechas que elijas.</p>
        </div>
        <button type="button" id="btn-descargar-excel" class="btn-primario-accento">
            <i class="bi bi-file-earmark-excel"></i> Descargar Excel
        </button>
    </div>

    <div class="card-elevada mb-4">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" id="filtro-desde" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" id="filtro-hasta" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Empleado</label>
                <select id="filtro-empleado" class="form-select">
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
                <div class="acciones-filtros">
                    <button type="button" id="btn-filtrar" class="btn-primario-accento">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <table id="tabla-ventas" class="table table-striped align-middle w-100 fila-tabla-hover fila-tabla-amplia">
                <thead>
                    <tr>
                        <th></th>
                        <th>Fecha</th>
                        <th>Horario</th>
                        <th>Cliente</th>
                        <th>Servicio</th>
                        <th>Empleado</th>
                        <th>Estado</th>
                        <th>Precio</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <div class="resumen-reporte">
                <div class="dato-resumen">
                    <div class="etiqueta">Reservas</div>
                    <div class="valor" id="resumen-cantidad">0</div>
                </div>
                <div class="dato-resumen">
                    <div class="etiqueta">Total facturado</div>
                    <div class="valor" id="resumen-total">$0</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        var tablaVentas;

        function formatearFechaISO(fecha) {
            var mes = String(fecha.getMonth() + 1).padStart(2, '0');
            var dia = String(fecha.getDate()).padStart(2, '0');

            return fecha.getFullYear() + '-' + mes + '-' + dia;
        }

        function formatearPrecio(valor) {
            var numero = parseFloat(valor);

            if (isNaN(numero)) {
                return '<span class="text-muted">—</span>';
            }

            return '$' + numero.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }

        function recortarHora(hora) {
            return hora ? hora.substring(0, 5) : '';
        }

        function badgeEstadoReserva(estadoReserva) {
            var etiquetas = {
                pendiente: 'Pendiente',
                confirmada: 'Confirmada',
                completada: 'Completada',
                cancelada: 'Cancelada'
            };

            return '<span class="badge-reserva badge-reserva-' + estadoReserva + '">' +
                   (etiquetas[estadoReserva] || estadoReserva) + '</span>';
        }

        // Los mismos filtros alimentan la tabla y la descarga del Excel.
        function filtrosActuales() {
            return {
                fecha_inicio: jQuery('#filtro-desde').val(),
                fecha_fin: jQuery('#filtro-hasta').val(),
                id_empleado: jQuery('#filtro-empleado').val(),
                estado_reserva: jQuery('#filtro-estado').val()
            };
        }

        function cargarEmpleados() {
            axiosSipleInterno('GET', 'request/empleado/listar', {}, {}, false, function (respuesta) {
                if (respuesta.error != 0) {
                    return;
                }

                var select = jQuery('#filtro-empleado');

                respuesta.data.empleados.forEach(function (empleado) {
                    if (empleado.estado == 1) {
                        select.append(jQuery('<option>').val(empleado.id_empleado).text(empleado.nombre));
                    }
                });
            });
        }

        function cargarReporte() {
            axiosSipleInterno('GET', 'request/reporte/ventas-preview', filtrosActuales(), {}, true, function (respuesta) {
                if (respuesta.error != 0) {
                    notificarUsuario(respuesta.mensaje, 'error');
                    return;
                }

                pintarTabla(respuesta.data.ventas);
            });
        }

        function pintarTabla(ventas) {
            if (tablaVentas) {
                tablaVentas.destroy();
                jQuery('#tabla-ventas tbody').empty();
            }

            tablaVentas = jQuery('#tabla-ventas').DataTable({
                data: ventas,
                language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' },
                columns: [
                    {
                        data: null,
                        orderable: false,
                        render: function (fila) {
                            return generarAvatar(fila.nombre_cliente);
                        }
                    },
                    { data: 'fecha_reserva' },
                    {
                        data: null,
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
                    },
                    {
                        data: 'precio',
                        render: function (data) {
                            return formatearPrecio(data);
                        }
                    }
                ]
            });

            // El total solo suma lo que de verdad cuenta como ingreso, igual que
            // el reporte por servicio y la tarjeta del panel.
            var total = ventas.reduce(function (suma, venta) {
                var cuenta = venta.estado_reserva === 'confirmada' || venta.estado_reserva === 'completada';

                return cuenta ? suma + parseFloat(venta.precio) : suma;
            }, 0);

            jQuery('#resumen-cantidad').text(ventas.length);
            jQuery('#resumen-total').html(formatearPrecio(total));
        }

        jQuery('#btn-filtrar').on('click', cargarReporte);

        jQuery('#btn-descargar-excel').on('click', function () {
            var filtros = filtrosActuales();

            if (!filtros.fecha_inicio || !filtros.fecha_fin) {
                notificarUsuario('Elige el rango de fechas antes de descargar el reporte', 'info');
                return;
            }

            // Es un archivo binario: se navega a la URL para que el navegador lo
            // descargue, en vez de traerlo por axios.
            window.location.href = UrlGlobal + 'request/reporte/ventas-descargar?' + jQuery.param(filtros);
        });

        jQuery(document).ready(function () {
            // Por defecto, el mes en curso.
            var hoy = new Date();
            var primero = new Date(hoy.getFullYear(), hoy.getMonth(), 1);

            jQuery('#filtro-desde').val(formatearFechaISO(primero));
            jQuery('#filtro-hasta').val(formatearFechaISO(new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0)));

            cargarEmpleados();
            cargarReporte();

            iniciarGuiaSiCorresponde('reportes', function () {
                iniciarTourContextual('reportes', [
                    {
                        attachTo: { element: '#filtro-desde', on: 'bottom' },
                        title: 'Elige el periodo',
                        text: 'Todos los reportes parten de un rango de fechas. Por defecto viene el mes en curso.'
                    },
                    {
                        attachTo: { element: '#filtro-empleado', on: 'bottom' },
                        title: 'Afina lo que quieres ver',
                        text: 'Puedes mirar solo las ventas de un empleado, o filtrar por el estado de la reserva.'
                    },
                    {
                        attachTo: { element: '#tabla-ventas', on: 'top' },
                        title: 'Ventas por fecha',
                        text: 'Este reporte lista cita por cita: quién atendió, qué servicio fue y cuánto costó.'
                    },
                    {
                        attachTo: { element: '#btn-descargar-excel', on: 'bottom' },
                        title: 'Llévatelo en Excel',
                        text: 'Cualquier reporte que veas en pantalla lo puedes descargar con los mismos filtros aplicados.'
                    },
                    {
                        attachTo: { element: '#submenu-reportes', on: 'right' },
                        title: 'El otro reporte',
                        text: 'En "Por servicio" ves cuánto aportó cada servicio del catálogo: cuántas veces se reservó y cuánto dinero dejó. Útil para saber qué conviene impulsar.'
                    }
                ], function () {
                    // Solo al terminar el tour completo: este paso del onboarding
                    // no tiene nada que crear en base de datos, así que lo que se
                    // persiste es justamente haberlo visto entero.
                    axiosSipleInterno('POST', 'request/negocio/marcar-reportes-tour', {}, {}, false, function (respuesta) {
                        if (respuesta.error != 0) {
                            return;
                        }

                        dispararConfeti(110);

                        // Refresca el drawer para que el paso se marque al vuelo.
                        if (typeof cargarProgresoOnboarding === 'function') {
                            cargarProgresoOnboarding();
                        }
                    });
                });
            });
        });
    </script>
@endsection
