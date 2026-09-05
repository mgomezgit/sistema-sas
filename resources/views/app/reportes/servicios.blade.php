@extends('layout.backoffice')

@section('title', 'Ingresos por Servicio')

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
            <h2 class="titulo-pagina">Ingresos por Servicio</h2>
            <p class="subtitulo-pagina">Cuánto aportó cada servicio en el rango elegido. Solo cuenta reservas confirmadas y completadas.</p>
        </div>
        <button type="button" id="btn-descargar-excel" class="btn-primario-accento">
            <i class="bi bi-file-earmark-excel"></i> Descargar Excel
        </button>
    </div>

    <div class="card-elevada mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Desde</label>
                <input type="date" id="filtro-desde" class="form-control">
            </div>
            <div class="col-md-3">
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
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <table id="tabla-servicios" class="table table-striped align-middle w-100 fila-tabla-hover fila-tabla-amplia">
                <thead>
                    <tr>
                        <th></th>
                        <th>Servicio</th>
                        <th>Cantidad de Reservas</th>
                        <th>Ingresos Totales</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <div class="resumen-reporte">
                <div class="dato-resumen">
                    <div class="etiqueta">Servicios con ingresos</div>
                    <div class="valor" id="resumen-servicios">0</div>
                </div>
                <div class="dato-resumen">
                    <div class="etiqueta">Ingresos del periodo</div>
                    <div class="valor" id="resumen-total">$0</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        var tablaServicios;

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

        function filtrosActuales() {
            return {
                fecha_inicio: jQuery('#filtro-desde').val(),
                fecha_fin: jQuery('#filtro-hasta').val()
            };
        }

        function cargarReporte() {
            axiosSipleInterno('GET', 'request/reporte/servicios-preview', filtrosActuales(), {}, true, function (respuesta) {
                if (respuesta.error != 0) {
                    notificarUsuario(respuesta.mensaje, 'error');
                    return;
                }

                pintarTabla(respuesta.data.servicios);
            });
        }

        function pintarTabla(servicios) {
            if (tablaServicios) {
                tablaServicios.destroy();
                jQuery('#tabla-servicios tbody').empty();
            }

            tablaServicios = jQuery('#tabla-servicios').DataTable({
                data: servicios,
                language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' },
                // Ya vienen ordenados por ingresos desde el backend.
                order: [],
                columns: [
                    {
                        data: null,
                        orderable: false,
                        render: function (fila) {
                            return generarAvatar(fila.nombre_recurso, 'bi-stars');
                        }
                    },
                    { data: 'nombre_recurso' },
                    { data: 'cantidad_reservas' },
                    {
                        data: 'ingresos_totales',
                        render: function (data) {
                            return formatearPrecio(data);
                        }
                    }
                ]
            });

            var total = servicios.reduce(function (suma, servicio) {
                return suma + parseFloat(servicio.ingresos_totales);
            }, 0);

            jQuery('#resumen-servicios').text(servicios.length);
            jQuery('#resumen-total').html(formatearPrecio(total));
        }

        jQuery('#btn-filtrar').on('click', cargarReporte);

        jQuery('#btn-descargar-excel').on('click', function () {
            var filtros = filtrosActuales();

            if (!filtros.fecha_inicio || !filtros.fecha_fin) {
                notificarUsuario('Elige el rango de fechas antes de descargar el reporte', 'info');
                return;
            }

            // Archivo binario: se deja que lo descargue el navegador.
            window.location.href = UrlGlobal + 'request/reporte/servicios-descargar?' + jQuery.param(filtros);
        });

        jQuery(document).ready(function () {
            var hoy = new Date();

            jQuery('#filtro-desde').val(formatearFechaISO(new Date(hoy.getFullYear(), hoy.getMonth(), 1)));
            jQuery('#filtro-hasta').val(formatearFechaISO(new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0)));

            cargarReporte();
        });
    </script>
@endsection
