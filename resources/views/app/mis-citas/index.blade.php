@extends('layout.backoffice')

@section('title', 'Mis Citas')

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
            max-width: 520px;
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

        /* Badges de estado: mismos colores que el listado de reservas del admin */
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

        .card-cita {
            margin-bottom: 1rem;
        }

        .card-cita.cita-cancelada .horario-cita,
        .card-cita.cita-cancelada .cliente-cita,
        .card-cita.cita-cancelada .recurso-cita {
            text-decoration: line-through;
            color: var(--text-muted);
        }

        .cabecera-cita {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 0.85rem;
        }

        .horario-cita {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.05rem;
        }

        .horario-cita i {
            color: var(--accent);
        }

        .cabecera-cita .badge-reserva {
            margin-left: auto;
        }

        .datos-cita {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 0.85rem;
        }

        .dato-cita .etiqueta-dato {
            display: block;
            color: var(--text-secondary);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
            margin-bottom: 0.15rem;
        }

        .dato-cita .valor-dato {
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .notas-cita {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.65rem 0.85rem;
            color: var(--text-secondary);
            font-size: 0.88rem;
            margin-bottom: 0.85rem;
        }

        .acciones-cita {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            padding-top: 0.85rem;
            border-top: 1px solid var(--border-color);
        }

        .btn-accion-cita {
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.85rem;
            padding: 0.45rem 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: 1px solid var(--border-color);
            background-color: transparent;
            color: var(--text-secondary);
            transition: var(--transition-base);
        }

        .btn-accion-cita.btn-confirmar:hover {
            background-color: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
        }

        .btn-accion-cita.btn-completar:hover {
            background-color: var(--success-soft);
            border-color: var(--success);
            color: var(--success);
        }

        .estado-vacio {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .estado-vacio i {
            font-size: 2.5rem;
            color: var(--text-muted);
            display: block;
            margin-bottom: 0.9rem;
        }
    </style>
@endsection

@section('content')
    <div class="mb-4">
        <h2 class="titulo-pagina">Mis Citas</h2>
        <p class="subtitulo-pagina">Consulta y actualiza tus citas asignadas.</p>
    </div>

    <div class="barra-fecha">
        <label for="filtro-fecha">Ver día:</label>
        <input type="date" id="filtro-fecha" class="form-control">
    </div>

    <div id="contenedor-citas"></div>
@endsection

@section('scripts')
    <script>
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

        function cargarMisCitas() {
            var fecha = jQuery('#filtro-fecha').val();

            if (!fecha) {
                return;
            }

            axiosSipleInterno('GET', 'request/reserva/mis-citas', { fecha: fecha }, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarCitas(respuesta.data.citas);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        function pintarCitas(citas) {
            var contenedor = jQuery('#contenedor-citas');
            contenedor.empty();

            if (!citas || citas.length === 0) {
                contenedor.html(
                    '<div class="card-elevada estado-vacio">' +
                    '<i class="bi bi-calendar-x"></i>' +
                    'No tienes citas asignadas este día.' +
                    '</div>'
                );
                return;
            }

            citas.forEach(function (cita) {
                contenedor.append(construirCardCita(cita));
            });
        }

        function construirCardCita(cita) {
            var claseCancelada = cita.estado_reserva === 'cancelada' ? ' cita-cancelada' : '';

            var html = '<div class="card-elevada card-cita' + claseCancelada + '">' +
                '<div class="cabecera-cita">' +
                '<span class="horario-cita"><i class="bi bi-clock"></i> ' +
                recortarHora(cita.hora_inicio) + ' - ' + recortarHora(cita.hora_fin) +
                '</span>' +
                badgeEstadoReserva(cita.estado_reserva) +
                '</div>' +
                '<div class="datos-cita">' +
                '<div class="dato-cita">' +
                '<span class="etiqueta-dato">Cliente</span>' +
                '<span class="valor-dato cliente-cita">' + escaparTexto(cita.nombre_cliente) + '</span>' +
                '</div>' +
                '<div class="dato-cita">' +
                '<span class="etiqueta-dato">Teléfono</span>' +
                '<span class="valor-dato">' + escaparTexto(cita.telefono_cliente) + '</span>' +
                '</div>' +
                '<div class="dato-cita">' +
                '<span class="etiqueta-dato">Servicio</span>' +
                '<span class="valor-dato recurso-cita">' + escaparTexto(cita.nombre_recurso) + '</span>' +
                '</div>' +
                '</div>';

            if (cita.notas) {
                html += '<div class="notas-cita"><i class="bi bi-sticky"></i> ' + escaparTexto(cita.notas) + '</div>';
            }

            // El empleado solo puede avanzar el estado: confirmar o completar.
            // Una cita cancelada o ya completada no ofrece acciones.
            var botones = '';

            if (cita.estado_reserva === 'pendiente') {
                botones += '<button type="button" class="btn-accion-cita btn-confirmar" data-id_reserva="' + cita.id_reserva + '" data-estado="confirmada">' +
                           '<i class="bi bi-check-circle"></i> Confirmar</button>';
            }

            if (cita.estado_reserva === 'pendiente' || cita.estado_reserva === 'confirmada') {
                botones += '<button type="button" class="btn-accion-cita btn-completar" data-id_reserva="' + cita.id_reserva + '" data-estado="completada">' +
                           '<i class="bi bi-check2-all"></i> Marcar completada</button>';
            }

            if (botones !== '') {
                html += '<div class="acciones-cita">' + botones + '</div>';
            }

            html += '</div>';

            return html;
        }

        jQuery('#contenedor-citas').on('click', '.btn-accion-cita', function () {
            var idReserva = jQuery(this).data('id_reserva');
            var estadoReserva = jQuery(this).data('estado');

            axiosSipleInterno('POST', 'request/reserva/cambiar-estado-mi-cita', {}, {
                id_reserva: idReserva,
                estado_reserva: estadoReserva
            }, true, function (respuesta) {
                if (respuesta.error == 0) {
                    notificarUsuario(
                        estadoReserva === 'confirmada' ? 'Cita confirmada' : 'Cita marcada como completada',
                        'success'
                    );
                    cargarMisCitas();
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        jQuery('#filtro-fecha').on('change', function () {
            cargarMisCitas();
        });

        jQuery(document).ready(function () {
            jQuery('#filtro-fecha').val(fechaDeHoy());
            cargarMisCitas();
        });
    </script>
@endsection
