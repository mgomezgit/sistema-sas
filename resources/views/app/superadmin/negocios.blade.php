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
            var nombreNegocio = boton.data('nombre');
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

        function pintarModalModulos(modulos) {
            var lista = jQuery('#lista-modulos-negocio');

            if (!modulos || modulos.length === 0) {
                lista.html('<div class="sin-modulos-fila">La plataforma todavía no tiene módulos en su catálogo.</div>');

                return;
            }

            var html = '';

            modulos.forEach(function (modulo) {
                var activo = modulo.activo == 1;

                html += '<div class="fila-modulo-toggle">' +
                    '<div>' +
                        '<div class="nombre-modulo-toggle">' + escaparTexto(modulo.nombre) + '</div>' +
                        (modulo.descripcion ? '<div class="descripcion-modulo-toggle">' + escaparTexto(modulo.descripcion) + '</div>' : '') +
                    '</div>' +
                    '<label class="interruptor-moderno">' +
                        '<input type="checkbox" class="chk-modulo-toggle" data-clave="' + escaparTexto(modulo.clave) + '" ' + (activo ? 'checked' : '') + '>' +
                        '<span class="pista-interruptor"></span>' +
                    '</label>' +
                '</div>';
            });

            lista.html(html);
        }

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
            var nombreNegocio = jQuery(this).data('nombre');

            // .text() y no .html(): el nombre del negocio es texto del cliente.
            jQuery('#modal-modulos-subtitulo').text(nombreNegocio);

            cargarModulosDelNegocio(idNegocioEnModalModulos);

            var modal = new bootstrap.Modal(document.getElementById('modal-modulos-negocio'));
            modal.show();
        });

        // Al cambiar un interruptor se llama de inmediato al endpoint
        // correspondiente. Si el servidor rechaza, el interruptor vuelve a su
        // posición anterior (nunca queda mostrando algo que no se guardó) y se
        // muestra el mensaje real del backend, no uno genérico.
        jQuery('#lista-modulos-negocio').on('change', '.chk-modulo-toggle', function () {
            var checkbox = jQuery(this);
            var clave = checkbox.data('clave');
            // El estado se lee del propio checkbox (checked) y se manda
            // explícito como 0/1: esto no pasa por serializeObject ni tiene
            // name, así que nada lo recolectaría solo.
            var activar = checkbox.prop('checked');
            var url = activar ? 'request/superadmin/activar-modulo' : 'request/superadmin/desactivar-modulo';

            checkbox.prop('disabled', true);

            axiosSipleInterno('POST', url, {}, {
                id_negocio: idNegocioEnModalModulos,
                clave_modulo: clave
            }, false, function (respuesta) {
                checkbox.prop('disabled', false);

                if (respuesta.error == 0) {
                    notificarUsuario(activar ? 'Módulo activado' : 'Módulo desactivado', 'success');
                    // La tabla de fondo puede haber cambiado su columna de
                    // módulos activos: se refresca sin cerrar el modal.
                    cargarNegocios();
                } else {
                    // El servidor lo rechazó: el interruptor vuelve a su
                    // posición anterior en vez de quedarse mostrando un
                    // cambio que nunca se guardó.
                    checkbox.prop('checked', !activar);
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        jQuery(document).ready(function () {
            cargarNegocios();
        });
    </script>
@endsection
