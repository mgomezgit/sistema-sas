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

        /* Tarjeta del chatbot: mensajes del mes contra el tope global. */
        .barra-uso-chatbot {
            height: 8px;
            border-radius: 999px;
            background: var(--border-color);
            overflow: hidden;
            margin: 0.6rem 0 0.4rem;
        }

        .barra-uso-chatbot > span {
            display: block;
            height: 100%;
            border-radius: 999px;
            background: var(--accent);
        }

        .barra-uso-chatbot.casi-llena > span {
            background: var(--warning);
        }

        .barra-uso-chatbot.llena > span {
            background: var(--danger);
        }

        .detalle-chatbot-plataforma {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .detalle-chatbot-plataforma strong {
            color: var(--text-primary);
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

    <div class="etiqueta-seccion-dashboard">Chatbot</div>
    <div id="contenedor-chatbot-plataforma">
        <div class="estado-vacio-plataforma">
            <i class="bi bi-hourglass-split"></i>
            Cargando el uso del chatbot...
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

        /* ---------- Tarjeta del chatbot ----------
         * Solo números agregados (ver SvcSuperAdmin::resumenChatbot()). Todo se
         * pinta con createElement/textContent. Va aparte del resumen general:
         * si este endpoint falla, el resto de la pantalla sigue funcionando.
         */
        function crearNodo(etiqueta, clase, texto) {
            var nodo = document.createElement(etiqueta);

            if (clase) {
                nodo.className = clase;
            }

            if (texto !== undefined) {
                nodo.textContent = texto;
            }

            return nodo;
        }

        function mensajeEnChatbot(clase, icono, texto) {
            var contenedor = document.getElementById('contenedor-chatbot-plataforma');
            var mensaje = crearNodo('div', clase);

            while (contenedor.firstChild) {
                contenedor.removeChild(contenedor.firstChild);
            }

            mensaje.appendChild(crearNodo('i', 'bi ' + icono));
            mensaje.appendChild(document.createTextNode(texto));
            contenedor.appendChild(mensaje);
        }

        function pintarTarjetaChatbot(resumen) {
            var contenedor = document.getElementById('contenedor-chatbot-plataforma');
            var formato = function (n) { return Number(n || 0).toLocaleString('es-CO'); };

            // Estado vacío: nadie lo tiene activado y no hay uso este mes.
            if (!resumen || (resumen.negocios_con_modulo === 0 && resumen.mensajes_del_mes === 0)) {
                mensajeEnChatbot('estado-vacio-plataforma', 'bi-chat-dots', 'Ningún negocio tiene el chatbot activado todavía. Se activa por negocio desde Negocios > Módulos.');

                return;
            }

            while (contenedor.firstChild) {
                contenedor.removeChild(contenedor.firstChild);
            }

            var tope = resumen.tope_global_mensual > 0 ? resumen.tope_global_mensual : 0;
            var porcentaje = tope > 0 ? Math.min(100, Math.round((resumen.mensajes_del_mes / tope) * 100)) : 100;

            var tarjeta = crearNodo('div', 'card-elevada kpi-tile tarjeta-modulo-resumen');
            var interior = crearNodo('div');

            var icono = crearNodo('div', 'kpi-icono');
            icono.appendChild(crearNodo('i', 'bi bi-chat-dots'));
            interior.appendChild(icono);
            interior.appendChild(crearNodo('div', 'kpi-label', 'Mensajes del mes contra el tope global'));
            interior.appendChild(crearNodo('div', 'kpi-valor', formato(resumen.mensajes_del_mes) + ' / ' + formato(tope)));

            var barra = crearNodo('div', 'barra-uso-chatbot' + (porcentaje >= 100 ? ' llena' : (porcentaje >= 80 ? ' casi-llena' : '')));
            var relleno = crearNodo('span');
            relleno.style.width = porcentaje + '%';
            barra.appendChild(relleno);
            interior.appendChild(barra);

            var detalle = crearNodo('div', 'detalle-chatbot-plataforma');
            var linea1 = crearNodo('div');
            linea1.appendChild(crearNodo('strong', null, formato(resumen.negocios_con_modulo)));
            linea1.appendChild(document.createTextNode(resumen.negocios_con_modulo === 1 ? ' negocio con el chatbot activo' : ' negocios con el chatbot activo'));
            detalle.appendChild(linea1);
            detalle.appendChild(crearNodo('div', null, 'Tokens: ' + formato(resumen.tokens_entrada) + ' de entrada / ' + formato(resumen.tokens_salida) + ' de salida'));

            if (!resumen.habilitado) {
                detalle.appendChild(crearNodo('div', null, 'El interruptor global del chatbot está apagado.'));
            }

            interior.appendChild(detalle);
            tarjeta.appendChild(interior);

            var rejilla = crearNodo('div', 'rejilla-resumen-plataforma');
            rejilla.appendChild(tarjeta);
            contenedor.appendChild(rejilla);
        }

        function cargarResumenChatbot() {
            // Se usa el valor que devuelve la promesa (false si falló la red o el
            // servidor): con silenciarError el callback no se ejecuta en un fallo.
            axiosSipleInterno('GET', 'request/superadmin/chatbot/resumen', {}, {}, false, undefined, { silenciarError: true }).then(function (respuesta) {
                if (!respuesta || respuesta.error != 0 || !respuesta.data || !respuesta.data.resumen) {
                    mensajeEnChatbot('estado-error-plataforma', 'bi-exclamation-triangle', 'No pudimos cargar el uso del chatbot. El resto del panel sigue funcionando; intenta recargar la página.');

                    return;
                }

                pintarTarjetaChatbot(respuesta.data.resumen);
            });
        }

        jQuery(document).ready(function () {
            cargarResumenPlataforma();
            cargarResumenChatbot();
        });
    </script>
@endsection
