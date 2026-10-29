@extends('layout.backoffice')

@section('title', 'Negocios')

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
            max-width: 620px;
        }

        .card-tabla {
            padding: 0;
            overflow: hidden;
        }

        .card-tabla .card-tabla-body {
            padding: 1.25rem 1.5rem;
        }

        .celda-negocio {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .celda-negocio .nombre-negocio-fila {
            font-weight: 600;
            color: var(--text-primary);
            display: block;
        }

        .celda-negocio .slug-negocio-fila {
            font-size: 0.78rem;
            color: var(--text-secondary);
        }

        .celda-contacto .nombre-contacto-fila {
            display: block;
            color: var(--text-primary);
        }

        .celda-contacto .email-contacto-fila {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .badge-modulo-activo {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.22rem 0.55rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
            background-color: var(--accent-soft);
            color: var(--accent);
            margin: 0.1rem 0.2rem 0.1rem 0;
        }

        .sin-modulos-fila {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        /* ---------- Modal: módulos por negocio ---------- */

        .fila-modulo-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.85rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .fila-modulo-toggle:last-child {
            border-bottom: none;
        }

        .fila-modulo-toggle .nombre-modulo-toggle {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.92rem;
        }

        .fila-modulo-toggle .descripcion-modulo-toggle {
            color: var(--text-secondary);
            font-size: 0.8rem;
            margin-top: 0.15rem;
        }

        /* Uso y límite del chatbot, debajo de su interruptor. */
        .bloque-chatbot-uso {
            padding: 0.9rem 1rem 1rem;
            margin: 0 0 0.85rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: color-mix(in srgb, var(--accent) 6%, transparent);
        }

        .bloque-chatbot-uso .uso-chatbot-texto {
            color: var(--text-primary);
            font-size: 0.88rem;
            line-height: 1.45;
        }

        .bloque-chatbot-uso .etiqueta-chatbot-bloqueado {
            display: inline-block;
            margin-left: 0.5rem;
            padding: 0.1rem 0.55rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-primary);
            border: 1px solid var(--danger);
            background: var(--danger-soft);
        }

        .bloque-chatbot-uso .ayuda-chatbot {
            color: var(--text-secondary);
            font-size: 0.78rem;
            margin-top: 0.35rem;
            line-height: 1.4;
        }

        /* ---------- Vigencia de módulos ---------- */

        .insignia-estado-modulo {
            display: inline-block;
            margin-top: 0.4rem;
            padding: 0.18rem 0.6rem;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 600;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .insignia-estado-modulo.estado-activo {
            color: var(--success);
            border-color: var(--success);
            background: color-mix(in srgb, var(--success) 12%, transparent);
        }

        .insignia-estado-modulo.estado-por-vencer,
        .insignia-estado-modulo.estado-vencido {
            color: var(--danger);
            border-color: var(--danger);
            background: color-mix(in srgb, var(--danger) 14%, transparent);
        }

        .insignia-estado-modulo.estado-inactivo {
            color: var(--text-muted);
        }

        .panel-vigencia-modulo {
            padding: 0.9rem 1rem 1rem;
            margin: 0 0 0.85rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: color-mix(in srgb, var(--accent) 6%, transparent);
        }

        .panel-vigencia-modulo.oculto {
            display: none;
        }

        .panel-vigencia-modulo .ayuda-vigencia,
        .panel-vigencia-modulo .contador-nota-vigencia {
            color: var(--text-secondary);
            font-size: 0.78rem;
            margin-top: 0.35rem;
            line-height: 1.4;
        }

        .panel-vigencia-modulo .contador-nota-vigencia {
            text-align: right;
        }

        .panel-vigencia-modulo .acciones-vigencia {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.9rem;
        }

        .panel-vigencia-modulo .acciones-vigencia .btn,
        .panel-vigencia-modulo .acciones-vigencia .btn-guardar-moderno {
            min-height: 44px;
        }
    </style>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="titulo-pagina">Negocios</h2>
            <p class="subtitulo-pagina">Todos los negocios registrados en la plataforma: su estado de cuenta, su contacto administrador y qué módulos de pago tienen activos.</p>
        </div>
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <table id="tabla-negocios" class="table table-striped align-middle w-100 fila-tabla-hover">
                <thead>
                    <tr>
                        <th>Negocio</th>
                        <th>Rubro</th>
                        <th>Estado</th>
                        <th>Registrado</th>
                        <th>Contacto</th>
                        <th>Módulos activos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- ================= MODAL: MÓDULOS DEL NEGOCIO ================= --}}
    <div class="modal fade modal-moderno" id="modal-modulos-negocio" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header-moderno">
                    <span class="insignia-encabezado"><i class="bi bi-puzzle"></i></span>
                    <div>
                        <h5 class="titulo-modal-moderno">Módulos de pago</h5>
                        <p class="subtitulo-modal-moderno" id="modal-modulos-subtitulo">&nbsp;</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="tarjeta-seccion-form">
                        <div class="ayuda-bloque">
                            Desactivar un módulo le bloquea el acceso al negocio de inmediato, pero conserva todos sus datos: tarifas, historial de pagos y cualquier configuración quedan intactos, listos por si se reactiva.
                        </div>

                        <div id="lista-modulos-negocio">
                            <div class="panel-campana-cargando">Cargando...</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        var tablaNegocios = null;

        function inicializarTooltips() {
            jQuery('[data-bs-toggle="tooltip"]').each(function () {
                var tooltipExistente = bootstrap.Tooltip.getInstance(this);
                if (tooltipExistente) {
                    tooltipExistente.dispose();
                }
                new bootstrap.Tooltip(this);
            });
        }

        function formatearFechaCorta(fechaTexto) {
            if (!fechaTexto) {
                return '—';
            }

            var fecha = new Date(fechaTexto.replace(' ', 'T'));

            if (isNaN(fecha.getTime())) {
                return '—';
            }

            return fecha.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' });
        }

        function pintarModulosActivos(claves) {
            if (!claves || claves.length === 0) {
                return '<span class="sin-modulos-fila">Ninguno</span>';
            }

            return claves.map(function (clave) {
                return '<span class="badge-modulo-activo"><i class="bi bi-check-circle-fill"></i> ' + escaparTexto(clave) + '</span>';
            }).join('');
        }

        function pintarTablaNegocios(negocios) {
            if (tablaNegocios) {
                tablaNegocios.destroy();
                jQuery('#tabla-negocios tbody').empty();
            }

            tablaNegocios = jQuery('#tabla-negocios').DataTable({
                data: negocios,
                language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' },
                columns: [
                    {
                        data: null,
                        render: function (fila) {
                            return '<div class="celda-negocio">' +
                                generarAvatar(fila.nombre_negocio) +
                                '<span>' +
                                '<span class="nombre-negocio-fila">' + escaparTexto(fila.nombre_negocio) + '</span>' +
                                '<span class="slug-negocio-fila">' + escaparTexto(fila.slug || 'sin dirección pública') + '</span>' +
                                '</span>' +
                                '</div>';
                        }
                    },
                    {
                        data: 'rubro',
                        render: function (data) {
                            return escaparTexto(data);
                        }
                    },
                    {
                        data: 'estado',
                        render: function (data) {
                            return data == 1
                                ? '<span class="badge-estado-activo"><i class="bi bi-check-circle-fill"></i> Activo</span>'
                                : '<span class="badge-estado-inactivo"><i class="bi bi-dash-circle-fill"></i> Suspendido</span>';
                        }
                    },
                    {
                        data: 'fecha_registro',
                        render: function (data) {
                            return formatearFechaCorta(data);
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function (fila) {
                            if (!fila.email_admin) {
                                return '<span class="sin-modulos-fila">Sin admin activo</span>';
                            }

                            return '<span class="celda-contacto">' +
                                '<span class="nombre-contacto-fila">' + escaparTexto(fila.nombre_admin || 'Sin nombre') + '</span>' +
                                '<span class="email-contacto-fila">' + escaparTexto(fila.email_admin) + '</span>' +
                                '</span>';
                        }
                    },
                    {
                        data: 'modulos_activos',
                        orderable: false,
                        render: function (data) {
                            return pintarModulosActivos(data);
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function (fila) {
                            var esActivo = fila.estado == 1;
                            var botonEstado = esActivo
                                ? '<button type="button" class="btn-accion-icono btn-accion-eliminar btn-suspender-negocio" data-bs-toggle="tooltip" title="Suspender" data-id_negocio="' + fila.id_negocio + '" data-nombre="' + escaparTexto(fila.nombre_negocio) + '"><i class="bi bi-slash-circle"></i></button>'
                                : '<button type="button" class="btn-accion-icono btn-reactivar-negocio" data-bs-toggle="tooltip" title="Reactivar" data-id_negocio="' + fila.id_negocio + '" data-nombre="' + escaparTexto(fila.nombre_negocio) + '"><i class="bi bi-arrow-counterclockwise"></i></button>';

                            return botonEstado +
                                '<button type="button" class="btn-accion-icono btn-modulos-negocio" data-bs-toggle="tooltip" title="Módulos" data-id_negocio="' + fila.id_negocio + '" data-nombre="' + escaparTexto(fila.nombre_negocio) + '"><i class="bi bi-puzzle"></i></button>';
                        }
                    }
                ]
            });

            tablaNegocios.on('draw', function () {
                inicializarTooltips();
            });
        }

        function cargarNegocios() {
            axiosSipleInterno('GET', 'request/superadmin/negocios', {}, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarTablaNegocios(respuesta.data.negocios);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        /* ================= SUSPENDER / REACTIVAR ================= */

        jQuery('#tabla-negocios').on('click', '.btn-suspender-negocio, .btn-reactivar-negocio', function () {
            var boton = jQuery(this);
            var idNegocio = boton.data('id_negocio');
            // .attr() y no .data(): un negocio llamado "123" o '{"a":1}' haria
            // que jQuery lo convirtiera a numero u objeto en vez de dejarlo
            // como texto, y el aviso de abajo mostraria "[object Object]".
            var nombreNegocio = boton.attr('data-nombre');
            var suspendiendo = boton.hasClass('btn-suspender-negocio');

            // OJO: "title" de SweetAlert2 SÍ interpreta HTML (a diferencia de
            // "text", que es texto plano) — verificado con un navegador real:
            // sin este escape, un negocio llamado "<img src=x onerror=alert(1)>"
            // disparaba el alert() al abrir este mismo diálogo de confirmación.
            var nombreEscapadoParaTitulo = escaparTexto(nombreNegocio);

            Swal.fire({
                title: suspendiendo ? '¿Suspender "' + nombreEscapadoParaTitulo + '"?' : '¿Reactivar "' + nombreEscapadoParaTitulo + '"?',
                text: suspendiendo
                    ? 'Sus usuarios perderán el acceso de inmediato. Sus datos no se borran: reactivarlo lo restaura tal como estaba.'
                    : 'Sus usuarios podrán volver a iniciar sesión de inmediato.',
                icon: 'warning',
                background: 'var(--bg-card)',
                color: 'var(--text-primary)',
                confirmButtonColor: colorVariable('--accent'),
                showCancelButton: true,
                confirmButtonText: suspendiendo ? 'Sí, suspender' : 'Sí, reactivar',
                cancelButtonText: 'Cancelar'
            }).then(function (resultado) {
                if (!resultado.isConfirmed) {
                    return;
                }

                axiosSipleInterno('POST', 'request/superadmin/cambiar-estado-negocio', {}, {
                    id_negocio: idNegocio,
                    estado: suspendiendo ? 0 : 1
                }, true, function (respuesta) {
                    if (respuesta.error == 0) {
                        notificarUsuario(suspendiendo ? 'Negocio suspendido' : 'Negocio reactivado', 'success');
                        cargarNegocios();
                    } else {
                        notificarUsuario(respuesta.mensaje, 'error');
                    }
                });
            });
        });

        /* ================= MÓDULOS POR NEGOCIO ================= */

        var idNegocioEnModalModulos = null;
        var nombreNegocioEnModal = '';

        /* === INICIO JS VIGENCIA MODULOS ===
           Todo este bloque pinta con createElement + textContent: los nombres,
           la nota y cualquier texto del servidor se tratan como texto, nunca
           como marcado crudo. */

        // Insignia de estado, con el texto ya calculado en el servidor
        // (vencido, dias_restantes). Devuelve un <span> con su clase.
        function insigniaEstadoModulo(modulo) {
            var clase = 'insignia-estado-modulo';
            var texto;

            if (modulo.activo != 1) {
                clase += ' estado-inactivo';
                texto = 'Inactivo';
            } else if (modulo.vencido == 1) {
                clase += ' estado-vencido';
                texto = 'Vencido el ' + modulo.vigente_hasta;
            } else if (modulo.vigente_hasta === null || modulo.vigente_hasta === undefined) {
                clase += ' estado-activo';
                texto = 'Activo, sin vencimiento';
            } else if (modulo.dias_restantes !== null && modulo.dias_restantes <= 7) {
                clase += ' estado-por-vencer';
                texto = 'Vence pronto: ' + modulo.vigente_hasta + ' (faltan ' + modulo.dias_restantes + ' días)';
            } else {
                clase += ' estado-activo';
                texto = 'Activo, vence ' + modulo.vigente_hasta + ' (faltan ' + modulo.dias_restantes + ' días)';
            }

            return crearElemento('span', clase, texto);
        }

        // Campo de formulario de vigencia (fecha, origen, nota). Reusado por el
        // panel de activar (oculto) y el de renovar (visible).
        function construirPanelVigencia(modulo) {
            var activo = modulo.activo == 1;

            var panel = crearElemento('div', 'panel-vigencia-modulo' + (activo ? '' : ' oculto'));
            panel.setAttribute('data-clave', modulo.clave);

            // Vigente hasta.
            var campoFecha = crearElemento('div', 'campo-flotante sin-icono');
            var fecha = crearElemento('input');
            fecha.type = 'date';
            fecha.className = 'vigente-hasta-vigencia';
            fecha.placeholder = ' ';
            if (modulo.vigente_hasta) {
                fecha.value = modulo.vigente_hasta;
            }
            var etiquetaFecha = crearElemento('label', null, 'Vigente hasta');
            campoFecha.appendChild(fecha);
            campoFecha.appendChild(etiquetaFecha);
            panel.appendChild(campoFecha);
            panel.appendChild(crearElemento('div', 'ayuda-vigencia', 'Vacía = sin vencimiento.'));

            // Origen.
            var campoOrigen = crearElemento('div', 'campo-flotante sin-icono mt-3');
            var origen = crearElemento('select');
            origen.className = 'origen-vigencia';
            [['', 'Sin especificar'], ['cortesia', 'Cortesía'], ['pagado_fuera', 'Pagado por fuera']].forEach(function (par) {
                var opcion = crearElemento('option', null, par[1]);
                opcion.value = par[0];
                if (modulo.origen === par[0]) {
                    opcion.selected = true;
                }
                origen.appendChild(opcion);
            });
            var etiquetaOrigen = crearElemento('label', null, 'Origen');
            campoOrigen.appendChild(origen);
            campoOrigen.appendChild(etiquetaOrigen);
            panel.appendChild(campoOrigen);

            // Nota + contador.
            var campoNota = crearElemento('div', 'campo-flotante sin-icono mt-3');
            var nota = crearElemento('textarea');
            nota.className = 'nota-vigencia';
            nota.maxLength = 200;
            nota.rows = 2;
            nota.placeholder = ' ';
            if (modulo.nota) {
                nota.value = modulo.nota;
            }
            var etiquetaNota = crearElemento('label', null, 'Nota (opcional)');
            campoNota.appendChild(nota);
            campoNota.appendChild(etiquetaNota);
            panel.appendChild(campoNota);
            var contador = crearElemento('div', 'contador-nota-vigencia', (modulo.nota ? modulo.nota.length : 0) + '/200');
            panel.appendChild(contador);

            // Botones.
            var acciones = crearElemento('div', 'acciones-vigencia');

            if (activo) {
                var btnRenovar = crearElemento('button', 'btn-guardar-moderno btn-renovar-modulo');
                btnRenovar.type = 'button';
                btnRenovar.setAttribute('data-clave', modulo.clave);
                btnRenovar.appendChild(crearElemento('i', 'bi bi-check2 icono-guardar'));
                btnRenovar.appendChild(crearElemento('span', null, 'Guardar vigencia'));
                acciones.appendChild(btnRenovar);
            } else {
                var btnActivar = crearElemento('button', 'btn-guardar-moderno btn-activar-modulo');
                btnActivar.type = 'button';
                btnActivar.setAttribute('data-clave', modulo.clave);
                btnActivar.appendChild(crearElemento('i', 'bi bi-power icono-guardar'));
                btnActivar.appendChild(crearElemento('span', null, 'Activar'));
                var btnCancelar = crearElemento('button', 'btn btn-secondary btn-cancelar-activar');
                btnCancelar.type = 'button';
                btnCancelar.setAttribute('data-clave', modulo.clave);
                btnCancelar.textContent = 'Cancelar';
                acciones.appendChild(btnActivar);
                acciones.appendChild(btnCancelar);
            }

            panel.appendChild(acciones);

            return panel;
        }

        function pintarModalModulos(modulos) {
            var lista = document.getElementById('lista-modulos-negocio');

            while (lista.firstChild) {
                lista.removeChild(lista.firstChild);
            }

            if (!modulos || modulos.length === 0) {
                lista.appendChild(crearElemento('div', 'sin-modulos-fila', 'La plataforma todavía no tiene módulos en su catálogo.'));

                return;
            }

            modulos.forEach(function (modulo) {
                var activo = modulo.activo == 1;

                var fila = crearElemento('div', 'fila-modulo-toggle');
                fila.setAttribute('data-clave-fila', modulo.clave);

                var izquierda = crearElemento('div');
                izquierda.appendChild(crearElemento('div', 'nombre-modulo-toggle', modulo.nombre));
                if (modulo.descripcion) {
                    izquierda.appendChild(crearElemento('div', 'descripcion-modulo-toggle', modulo.descripcion));
                }
                izquierda.appendChild(insigniaEstadoModulo(modulo));

                var label = crearElemento('label', 'interruptor-moderno');
                var checkbox = crearElemento('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'chk-modulo-toggle';
                // El estado se envía explícito al pulsar un botón; el checkbox no
                // lleva name, así que nada lo recolecta por su cuenta.
                checkbox.setAttribute('data-clave', modulo.clave);
                checkbox.checked = activo;
                label.appendChild(checkbox);
                label.appendChild(crearElemento('span', 'pista-interruptor'));

                fila.appendChild(izquierda);
                fila.appendChild(label);
                lista.appendChild(fila);

                // Panel de vigencia: visible para renovar (activo) u oculto hasta
                // encender (inactivo).
                lista.appendChild(construirPanelVigencia(modulo));

                // Chatbot activo: debajo va el uso del mes y el límite.
                if (modulo.clave === 'chatbot' && activo) {
                    var bloqueChatbot = crearElemento('div', 'bloque-chatbot-uso');
                    bloqueChatbot.id = 'bloque-chatbot-uso';
                    bloqueChatbot.appendChild(crearElemento('div', 'panel-campana-cargando', 'Cargando...'));
                    lista.appendChild(bloqueChatbot);
                }
            });

            if (document.getElementById('bloque-chatbot-uso')) {
                cargarBloqueChatbot();
            }
        }
        /* === FIN JS VIGENCIA MODULOS === */

        /* ---------- Chatbot: uso del mes y límite mensual ---------- */

        function formatoNumero(valor) {
            return Number(valor || 0).toLocaleString('es-CO');
        }

        function crearElemento(etiqueta, clase, texto) {
            var elemento = document.createElement(etiqueta);

            if (clase) {
                elemento.className = clase;
            }

            if (texto !== undefined) {
                elemento.textContent = texto;
            }

            return elemento;
        }

        function cargarBloqueChatbot() {
            var idPedido = idNegocioEnModalModulos;

            // Con silenciarError el callback no corre si falla; se usa el valor devuelto.
            axiosSipleInterno('GET', 'request/superadmin/chatbot/uso', { id_negocio: idPedido }, {}, false, undefined, { silenciarError: true }).then(function (respuesta) {
                // Si mientras tanto se abrió otro negocio, esta respuesta ya no aplica.
                if (idPedido !== idNegocioEnModalModulos || !document.getElementById('bloque-chatbot-uso')) {
                    return;
                }

                if (!respuesta || respuesta.error != 0 || !respuesta.data || !respuesta.data.negocios || respuesta.data.negocios.length === 0) {
                    pintarBloqueChatbot(null, null);

                    return;
                }

                pintarBloqueChatbot(respuesta.data.negocios[0], respuesta.data.limite_por_defecto);
            });
        }

        // Todo texto con textContent: nunca como HTML.
        function pintarBloqueChatbot(fila, limitePorDefecto) {
            var bloque = document.getElementById('bloque-chatbot-uso');

            while (bloque.firstChild) {
                bloque.removeChild(bloque.firstChild);
            }

            if (fila === null) {
                bloque.appendChild(crearElemento('div', 'sin-modulos-fila', 'No pudimos leer el uso del chatbot. Cierra y vuelve a abrir esta ventana.'));

                return;
            }

            var uso = crearElemento('div', 'uso-chatbot-texto',
                'Uso este mes: ' + formatoNumero(fila.mensajes) + ' mensajes de ' + formatoNumero(fila.limite_efectivo) +
                ' (' + formatoNumero(fila.tokens_entrada) + ' tokens de entrada / ' + formatoNumero(fila.tokens_salida) + ' de salida)');

            if (fila.limite_efectivo === 0) {
                uso.appendChild(crearElemento('span', 'etiqueta-chatbot-bloqueado', 'Bloqueado'));
            }

            bloque.appendChild(uso);

            var campo = crearElemento('div', 'campo-flotante sin-icono mt-3');
            var entrada = crearElemento('input');
            entrada.type = 'number';
            entrada.id = 'limite-chatbot';
            entrada.min = '0';
            entrada.max = '10000';
            entrada.step = '1';
            entrada.placeholder = ' ';
            entrada.setAttribute('inputmode', 'numeric');
            entrada.value = fila.limite_propio === null ? '' : String(fila.limite_propio);

            var etiqueta = crearElemento('label', null, 'Límite mensual');
            etiqueta.setAttribute('for', 'limite-chatbot');

            campo.appendChild(entrada);
            campo.appendChild(etiqueta);
            bloque.appendChild(campo);

            bloque.appendChild(crearElemento('div', 'ayuda-chatbot',
                'Vacío = valor por defecto (' + formatoNumero(limitePorDefecto) + ' al mes). 0 = el chatbot queda bloqueado para este negocio. Máximo 10.000.'));

            var boton = crearElemento('button', 'btn-guardar-moderno mt-3');
            boton.type = 'button';
            boton.id = 'btn-guardar-limite-chatbot';
            boton.appendChild(crearElemento('i', 'bi bi-check2 icono-guardar'));
            boton.appendChild(crearElemento('span', null, 'Guardar'));
            bloque.appendChild(boton);
        }

        jQuery('#lista-modulos-negocio').on('click', '#btn-guardar-limite-chatbot', function () {
            var boton = jQuery(this);
            var valor = document.getElementById('limite-chatbot').value.trim();

            // Vacío = sin límite propio (null). Lo demás lo valida el servidor.
            boton.addClass('ocupado').prop('disabled', true);

            axiosSipleInterno('POST', 'request/superadmin/chatbot/limite', {}, {
                id_negocio: idNegocioEnModalModulos,
                limite: valor === '' ? null : valor
            }, false, function (respuesta) {
                boton.removeClass('ocupado').prop('disabled', false);

                if (respuesta && respuesta.error == 0) {
                    notificarUsuario('Límite del chatbot guardado', 'success');
                    // Se refresca el bloque sin cerrar el modal.
                    cargarBloqueChatbot();
                } else if (respuesta) {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        function cargarModulosDelNegocio(idNegocio) {
            jQuery('#lista-modulos-negocio').html('<div class="panel-campana-cargando">Cargando...</div>');

            axiosSipleInterno('GET', 'request/superadmin/modulos-de-negocio', { id_negocio: idNegocio }, {}, false, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarModalModulos(respuesta.data.modulos);
                } else {
                    jQuery('#lista-modulos-negocio').html('<div class="sin-modulos-fila">' + escaparTexto(respuesta.mensaje) + '</div>');
                }
            });
        }

        jQuery('#tabla-negocios').on('click', '.btn-modulos-negocio', function () {
            idNegocioEnModalModulos = jQuery(this).data('id_negocio');
            // Mismo motivo que en el aviso de Suspender: .attr(), no .data().
            var nombreNegocio = jQuery(this).attr('data-nombre');
            nombreNegocioEnModal = nombreNegocio;

            // .text() y no .html(): el nombre del negocio es texto del cliente.
            jQuery('#modal-modulos-subtitulo').text(nombreNegocio);

            cargarModulosDelNegocio(idNegocioEnModalModulos);

            var modal = new bootstrap.Modal(document.getElementById('modal-modulos-negocio'));
            modal.show();
        });

        /* === INICIO JS VIGENCIA MODULOS (handlers) === */

        function panelDeClave(clave) {
            var paneles = document.querySelectorAll('.panel-vigencia-modulo');
            for (var i = 0; i < paneles.length; i++) {
                if (paneles[i].getAttribute('data-clave') === clave) {
                    return paneles[i];
                }
            }
            return null;
        }

        function datosVigenciaDelPanel(panel) {
            return {
                vigente_hasta: panel.querySelector('.vigente-hasta-vigencia').value || '',
                origen: panel.querySelector('.origen-vigencia').value || '',
                nota: panel.querySelector('.nota-vigencia').value || ''
            };
        }

        // Encender un módulo inactivo NO lo activa de inmediato: despliega el
        // formulario de vigencia y solo se activa al pulsar "Activar". Apagar
        // uno activo pide confirmación. Si el servidor rechaza, el interruptor
        // vuelve a su posición.
        jQuery('#lista-modulos-negocio').on('change', '.chk-modulo-toggle', function () {
            var checkbox = jQuery(this);
            var clave = checkbox.data('clave');
            var panel = panelDeClave(clave);

            if (checkbox.prop('checked')) {
                // Mostrar el formulario; el alta ocurre al pulsar "Activar".
                if (panel) {
                    panel.classList.remove('oculto');
                    var fecha = panel.querySelector('.vigente-hasta-vigencia');
                    if (fecha) {
                        fecha.focus();
                    }
                }
                return;
            }

            // Apagar: confirmar nombrando negocio y módulo (texto, no HTML).
            var nombreModulo = jQuery(this).closest('.fila-modulo-toggle').find('.nombre-modulo-toggle').text();

            Swal.fire({
                title: '¿Desactivar el módulo?',
                text: 'El spa "' + nombreNegocioEnModal + '" perderá el acceso a "' + nombreModulo + '" de inmediato. Sus datos no se borran.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar'
            }).then(function (resultado) {
                if (!resultado.isConfirmed) {
                    checkbox.prop('checked', true);
                    return;
                }

                checkbox.prop('disabled', true);

                axiosSipleInterno('POST', 'request/superadmin/desactivar-modulo', {}, {
                    id_negocio: idNegocioEnModalModulos,
                    clave_modulo: clave
                }, false, function (respuesta) {
                    checkbox.prop('disabled', false);

                    if (respuesta.error == 0) {
                        notificarUsuario('Módulo desactivado', 'success');
                        cargarNegocios();
                        cargarModulosDelNegocio(idNegocioEnModalModulos);
                    } else {
                        checkbox.prop('checked', true);
                        notificarUsuario(respuesta.mensaje, 'error');
                    }
                });
            });
        });

        // Contador de la nota.
        jQuery('#lista-modulos-negocio').on('input', '.nota-vigencia', function () {
            var panel = jQuery(this).closest('.panel-vigencia-modulo');
            panel.find('.contador-nota-vigencia').text(this.value.length + '/200');
        });

        // Cancelar el alta: ocultar el formulario y volver a apagar el interruptor.
        jQuery('#lista-modulos-negocio').on('click', '.btn-cancelar-activar', function () {
            var clave = jQuery(this).data('clave');
            var panel = panelDeClave(clave);
            if (panel) {
                panel.classList.add('oculto');
            }
            var checkbox = jQuery('.chk-modulo-toggle[data-clave="' + clave + '"]');
            checkbox.prop('checked', false);
        });

        // Activar (módulo inactivo) o renovar (módulo activo): misma llamada.
        function enviarVigencia(boton, clave) {
            var panel = panelDeClave(clave);
            if (!panel) {
                return;
            }

            var datos = datosVigenciaDelPanel(panel);
            datos.id_negocio = idNegocioEnModalModulos;
            datos.clave_modulo = clave;

            boton.addClass('ocupado').prop('disabled', true);

            axiosSipleInterno('POST', 'request/superadmin/activar-modulo', {}, datos, false, function (respuesta) {
                boton.removeClass('ocupado').prop('disabled', false);

                if (respuesta.error == 0) {
                    notificarUsuario('Módulo guardado', 'success');
                    cargarNegocios();
                    cargarModulosDelNegocio(idNegocioEnModalModulos);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        jQuery('#lista-modulos-negocio').on('click', '.btn-activar-modulo, .btn-renovar-modulo', function () {
            enviarVigencia(jQuery(this), jQuery(this).data('clave'));
        });
        /* === FIN JS VIGENCIA MODULOS (handlers) === */

        jQuery(document).ready(function () {
            cargarNegocios();
        });
    </script>
@endsection
