@extends('layout.backoffice')

@section('title', 'Panel de plataforma')

@section('estilos')
    <style>
        .saludo-dashboard h2 {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.6rem;
            margin-bottom: 0.15rem;
        }

        .saludo-dashboard .fecha-hoy {
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: capitalize;
        }

        .rejilla-resumen-plataforma {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .etiqueta-seccion-dashboard {
            color: var(--text-secondary);
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 1.75rem 0 0.85rem;
        }

        /* Tarjeta de módulo: el mismo esqueleto de kpi-tile, pero más baja,
           pensada para una fila de varias por pantalla. */
        .tarjeta-modulo-resumen .kpi-valor {
            font-size: 1.9rem;
        }

        .estado-vacio-plataforma,
        .estado-error-plataforma {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--text-secondary);
        }

        .estado-vacio-plataforma i,
        .estado-error-plataforma i {
            display: block;
            font-size: 2.2rem;
            margin-bottom: 0.75rem;
            color: var(--text-muted);
        }

        .estado-error-plataforma i {
            color: var(--danger);
        }
    </style>
@endsection

@section('content')
    <div class="saludo-dashboard mb-4">
        <h2>Hola, {{ session('nombre_usuario') }}</h2>
        <div class="fecha-hoy">Panel de la plataforma</div>
    </div>

    <div id="contenedor-resumen-plataforma">
        <div class="estado-vacio-plataforma">
            <i class="bi bi-hourglass-split"></i>
            Cargando el resumen...
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        /**
         * Solo agregados de plataforma: total de negocios, activos, inactivos,
         * nuevos en 30 días, y un contador por módulo. Nada operativo (ni
         * reservas, ni clientes, ni ingresos): eso pertenece a cada negocio,
         * no al super admin. Ver SvcSuperAdmin::resumenPlataforma().
         */
        function pintarResumenPlataforma(resumen) {
            var contenedor = jQuery('#contenedor-resumen-plataforma');

            var html = '<div class="rejilla-resumen-plataforma">' +
                '<div class="card-elevada card-acento kpi-tile">' +
                    '<div>' +
                        '<div class="kpi-icono"><i class="bi bi-buildings"></i></div>' +
                        '<div class="kpi-label">Total de negocios</div>' +
                        '<div class="kpi-valor">' + resumen.total_negocios + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="card-elevada kpi-tile">' +
                    '<div>' +
                        '<div class="kpi-icono"><i class="bi bi-check-circle"></i></div>' +
                        '<div class="kpi-label">Negocios activos</div>' +
                        '<div class="kpi-valor">' + resumen.negocios_activos + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="card-elevada kpi-tile">' +
                    '<div>' +
                        '<div class="kpi-icono"><i class="bi bi-slash-circle"></i></div>' +
                        '<div class="kpi-label">Negocios suspendidos</div>' +
                        '<div class="kpi-valor">' + resumen.negocios_inactivos + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="card-elevada kpi-tile">' +
                    '<div>' +
                        '<div class="kpi-icono"><i class="bi bi-stars"></i></div>' +
                        '<div class="kpi-label">Nuevos en 30 días</div>' +
                        '<div class="kpi-valor">' + resumen.negocios_nuevos_30_dias + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

            html += '<div class="etiqueta-seccion-dashboard">Módulos de pago</div>';

            if (!resumen.modulos || resumen.modulos.length === 0) {
                html += '<div class="estado-vacio-plataforma">' +
                    '<i class="bi bi-inboxes"></i>' +
                    'Todavía no hay módulos en el catálogo de la plataforma.' +
                '</div>';
            } else {
                html += '<div class="rejilla-resumen-plataforma">';

                resumen.modulos.forEach(function (modulo) {
                    // Nombre y clave del módulo son texto fijo del catálogo
                    // (los crea un desarrollador en una migración, no un
                    // usuario), pero se escapan igual por costumbre: ningún
                    // texto dinámico se concatena sin pasar por .text().
                    var nombreEscapado = escaparTexto(modulo.nombre);

                    html += '<div class="card-elevada kpi-tile tarjeta-modulo-resumen">' +
                        '<div>' +
                            '<div class="kpi-icono"><i class="bi bi-puzzle"></i></div>' +
                            '<div class="kpi-label">' + nombreEscapado + '</div>' +
                            '<div class="kpi-valor">' + modulo.negocios_con_modulo + '</div>' +
                        '</div>' +
                    '</div>';
                });

                html += '</div>';
            }

            contenedor.html(html);
        }

        function cargarResumenPlataforma() {
            axiosSipleInterno('GET', 'request/superadmin/resumen', {}, {}, false, function (respuesta) {
                if (!respuesta || respuesta.error != 0) {
                    jQuery('#contenedor-resumen-plataforma').html(
                        '<div class="estado-error-plataforma">' +
                        '<i class="bi bi-exclamation-triangle"></i>' +
                        'No pudimos cargar el resumen. Intenta recargar la página.' +
                        '</div>'
                    );

                    return;
                }

                pintarResumenPlataforma(respuesta.data.resumen);
            }, { silenciarError: true });
        }

        jQuery(document).ready(function () {
            cargarResumenPlataforma();
        });
    </script>
@endsection
