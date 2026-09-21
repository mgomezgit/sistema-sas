@extends('layout.backoffice')

@section('title', 'Recursos Reservables')

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

        .card-tabla {
            padding: 0;
            overflow: hidden;
        }

        .card-tabla .card-tabla-body {
            padding: 1.25rem 1.5rem;
        }

        /* El contador no lleva label flotante, así que el nombre del campo va
           encima, con el mismo peso que las etiquetas de sección. */
        .rotulo-stepper {
            color: var(--text-secondary);
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.4rem;
            text-align: center;
        }

        .ayuda-campo {
            color: var(--text-muted);
            font-size: 0.78rem;
            margin-top: 0.3rem;
        }
    </style>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="titulo-pagina">Recursos Reservables</h2>
            <p class="subtitulo-pagina">Administra los servicios que tu negocio ofrece para reservar.</p>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <label class="interruptor-moderno" id="filtro-mostrar-inactivos">
                <input type="checkbox" id="chk-mostrar-inactivos">
                <span class="pista-interruptor"></span>
                <span class="texto-interruptor">Mostrar inactivos</span>
            </label>
            <button type="button" id="btn-nuevo-recurso" class="btn-primario-accento" data-bs-toggle="modal" data-bs-target="#modal-recurso">
                <i class="bi bi-plus-lg"></i> Nuevo recurso
            </button>
        </div>
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <table id="tabla-recursos" class="table table-striped align-middle w-100 fila-tabla-hover fila-tabla-amplia">
                <thead>
                    <tr>
                        <th></th>
                        <th>Categoría</th>
                        <th>Nombre</th>
                        <th>Duración</th>
                        <th>Precio</th>
                        <th>Capacidad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade modal-moderno" id="modal-recurso" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header-moderno">
                    <span class="insignia-encabezado"><i class="bi bi-collection"></i></span>
                    <div>
                        <h5 class="titulo-modal-moderno" id="modal-recurso-titulo-texto">Nuevo recurso</h5>
                        <p class="subtitulo-modal-moderno" id="modal-recurso-subtitulo">Registra un servicio que tu negocio ofrece para reservar</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-recurso">
                        <input type="hidden" id="id_recurso" name="id_recurso">

                        <div class="tarjeta-seccion-form">
                            <div class="etiqueta-seccion-form">Información general</div>

                            <div class="campo-flotante">
                                <i class="bi bi-bookmark"></i>
                                <input type="text" id="categoria" name="categoria" maxlength="100" placeholder=" ">
                                <label for="categoria">Categoría</label>
                            </div>

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-tag"></i>
                                <input type="text" id="nombre" name="nombre" maxlength="150" placeholder=" " class="system_validador_vacio">
                                <label for="nombre">Nombre</label>
                            </div>

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-text-left"></i>
                                <textarea id="descripcion" name="descripcion" rows="2" placeholder=" "></textarea>
                                <label for="descripcion">Descripción</label>
                            </div>
                        </div>

                        <div class="tarjeta-seccion-form">
                            <div class="etiqueta-seccion-form">Configuración del servicio</div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="rotulo-stepper">Duración (minutos)</div>
                                    <div class="stepper-campo">
                                        <button type="button" class="btn-stepper" data-paso="-15" aria-label="Restar 15 minutos">
                                            <i class="bi bi-dash-lg"></i>
                                        </button>
                                        <input type="number" id="duracion_minutos" name="duracion_minutos" min="15" step="15" class="valor-stepper system_validador_vacio system_validador_numerico">
                                        <button type="button" class="btn-stepper" data-paso="15" aria-label="Sumar 15 minutos">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                    <div class="ayuda-campo mt-2 text-center">
                                        Se ajusta de 15 en 15 minutos, el tamaño habitual de un turno de reserva.
                                    </div>
                                </div>
                            </div>

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-cash-coin"></i>
                                <input type="number" id="precio" name="precio" step="0.01" min="0" placeholder=" " class="system_validador_vacio system_validador_numerico">
                                <label for="precio">Precio</label>
                            </div>

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-people"></i>
                                <input type="number" id="capacidad" name="capacidad" min="1" placeholder=" ">
                                <label for="capacidad">Capacidad</label>
                            </div>

                            <div class="ayuda-campo mt-2">
                                Deja la capacidad en blanco si este servicio no tiene un límite de personas.
                            </div>
                        </div>

                        {{-- Solo tiene sentido al editar: un recurso recién creado siempre
                             nace activo, así que aquí no se le pregunta nada al usuario. --}}
                        <div class="tarjeta-seccion-form" id="seccion-estado-recurso" hidden>
                            <div class="etiqueta-seccion-form">Estado</div>

                            <label class="interruptor-moderno">
                                <input type="checkbox" id="estado_recurso">
                                <span class="pista-interruptor"></span>
                                <span class="texto-interruptor" id="texto-estado-recurso">Recurso activo</span>
                            </label>

                            <div class="ayuda-campo mt-2">
                                Un recurso inactivo deja de aparecer en el listado y no puede reservarse, pero puede reactivarse en cualquier momento desde aquí.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-recurso" class="btn-guardar-moderno">
                        <i class="bi bi-check2 icono-guardar"></i>
                        <span id="texto-btn-guardar">Guardar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        var modoFormularioRecurso = 'crear';
        var tablaRecursos;

        function inicializarTooltips() {
            jQuery('[data-bs-toggle="tooltip"]').each(function () {
                var tooltipExistente = bootstrap.Tooltip.getInstance(this);
                if (tooltipExistente) {
                    tooltipExistente.dispose();
                }
                new bootstrap.Tooltip(this);
            });
        }

        function formatearPrecio(valor) {
            var numero = parseFloat(valor);
            if (isNaN(numero)) {
                return '<span class="text-muted">—</span>';
            }
            return '$' + numero.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        }

        function cargarRecursos() {
            var incluirInactivos = jQuery('#chk-mostrar-inactivos').is(':checked') ? 1 : 0;

            axiosSipleInterno('GET', 'request/recurso/listar', { incluir_inactivos: incluirInactivos }, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarTablaRecursos(respuesta.data.recursos);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        // "Mostrar inactivos": vuelve a pedir el listado con el filtro nuevo.
        jQuery('#chk-mostrar-inactivos').on('change', function () {
            cargarRecursos();
        });

        function pintarTablaRecursos(recursos) {
            if (tablaRecursos) {
                tablaRecursos.destroy();
                jQuery('#tabla-recursos tbody').empty();
            }

            tablaRecursos = jQuery('#tabla-recursos').DataTable({
                data: recursos,
                language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' },
                columns: [
                    {
                        data: null,
                        orderable: false,
                        render: function (fila) {
                            return generarAvatar(fila.nombre, 'bi-stars');
                        }
                    },
                    {
                        data: 'categoria',
                        render: function (data) {
                            return data ? data : '<span class="text-muted">—</span>';
                        }
                    },
                    { data: 'nombre' },
                    {
                        data: 'duracion_minutos',
                        render: function (data) {
                            return data + ' min';
                        }
                    },
                    {
                        data: 'precio',
                        render: function (data) {
                            return formatearPrecio(data);
                        }
                    },
                    {
                        data: 'capacidad',
                        render: function (data) {
                            return data ? data : '<span class="text-muted">—</span>';
                        }
                    },
                    {
                        data: 'estado',
                        render: function (data) {
                            return data == 1
                                ? '<span class="badge-estado-activo"><i class="bi bi-check-circle-fill"></i> Activo</span>'
                                : '<span class="badge-estado-inactivo"><i class="bi bi-dash-circle-fill"></i> Inactivo</span>';
                        }
                    },
                    {
                        data: 'id_recurso',
                        orderable: false,
                        render: function (data) {
                            return '<button type="button" class="btn-accion-icono btn-editar-recurso" data-bs-toggle="tooltip" title="Editar" data-id_recurso="' + data + '"><i class="bi bi-pencil-square"></i></button>' +
                                   '<button type="button" class="btn-accion-icono btn-accion-eliminar btn-eliminar-recurso" data-bs-toggle="tooltip" title="Eliminar" data-id_recurso="' + data + '"><i class="bi bi-trash3"></i></button>';
                        }
                    }
                ]
            });

            tablaRecursos.on('draw', function () {
                inicializarTooltips();
            });
        }

        /**
         * Los tres estados del botón de guardar que define el kit: normal,
         * "ocupado" (con su spinner) y "exito" (con su check).
         */
        function estadoBotonGuardar(estado) {
            var boton = jQuery('#btn-guardar-recurso');

            boton.removeClass('ocupado exito');

            if (estado === 'ocupado') {
                boton.addClass('ocupado');
                jQuery('#texto-btn-guardar').text('Guardando');
            } else if (estado === 'exito') {
                boton.addClass('exito');
                jQuery('#texto-btn-guardar').text('Guardado');
            } else {
                jQuery('#texto-btn-guardar').text('Guardar');
            }
        }

        /** Refleja el estado en el interruptor y en su propio texto. */
        function establecerEstadoRecurso(activo) {
            jQuery('#estado_recurso').prop('checked', activo);
            jQuery('#texto-estado-recurso').text(activo ? 'Recurso activo' : 'Recurso inactivo');
        }

        jQuery('#estado_recurso').on('change', function () {
            establecerEstadoRecurso(jQuery(this).is(':checked'));
        });

        function limpiarFormularioRecurso() {
            jQuery('#id_recurso').val('');
            jQuery('#categoria').val('');
            jQuery('#nombre').val('');
            jQuery('#descripcion').val('');
            // La duración arranca en su propio mínimo (15) y no en 0: un servicio
            // de 0 minutos no significa nada, así que no tiene sentido ofrecerlo
            // como punto de partida del contador.
            jQuery('#duracion_minutos').val(15);
            jQuery('#precio').val('');
            jQuery('#capacidad').val('');
            jQuery('#contenedor-form-recurso .input_vacio').removeClass('input_vacio');
            jQuery('#contenedor-form-recurso #system_validador').remove();
            estadoBotonGuardar('normal');
            establecerEstadoRecurso(true);
            jQuery('#seccion-estado-recurso').prop('hidden', true);
        }

        jQuery('#btn-nuevo-recurso').on('click', function () {
            modoFormularioRecurso = 'crear';
            jQuery('#modal-recurso-titulo-texto').text('Nuevo recurso');
            jQuery('#modal-recurso-subtitulo').text('Registra un servicio que tu negocio ofrece para reservar');
            limpiarFormularioRecurso();
        });

        jQuery('#tabla-recursos').on('click', '.btn-editar-recurso', function () {
            var fila = tablaRecursos.row(jQuery(this).closest('tr')).data();

            modoFormularioRecurso = 'editar';
            jQuery('#modal-recurso-titulo-texto').text('Editar recurso');
            jQuery('#modal-recurso-subtitulo').text('Actualiza los datos de "' + fila.nombre + '"');
            limpiarFormularioRecurso();

            jQuery('#id_recurso').val(fila.id_recurso);
            jQuery('#categoria').val(fila.categoria);
            jQuery('#nombre').val(fila.nombre);
            jQuery('#descripcion').val(fila.descripcion);
            jQuery('#duracion_minutos').val(fila.duracion_minutos);
            jQuery('#precio').val(fila.precio);
            jQuery('#capacidad').val(fila.capacidad);

            // El interruptor solo aparece al editar: un recurso nuevo siempre
            // nace activo, así que no hay nada que preguntar en ese momento.
            jQuery('#seccion-estado-recurso').prop('hidden', false);
            establecerEstadoRecurso(fila.estado == 1);

            var modalRecurso = new bootstrap.Modal(document.getElementById('modal-recurso'));
            modalRecurso.show();
        });

        jQuery('#tabla-recursos').on('click', '.btn-eliminar-recurso', function () {
            var idRecurso = jQuery(this).data('id_recurso');

            Swal.fire({
                title: '¿Eliminar recurso?',
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
                    axiosSipleInterno('POST', 'request/recurso/eliminar', {}, { id_recurso: idRecurso }, true, function (respuesta) {
                        if (respuesta.error == 0) {
                            notificarUsuario('Recurso eliminado correctamente', 'success');
                            cargarRecursos();
                        } else {
                            notificarUsuario(respuesta.mensaje, 'error');
                        }
                    });
                }
            });
        });

        /** Cuánto se deja ver el check de "Guardado" antes de cerrar, en ms. */
        var ESPERA_CONFIRMACION_GUARDADO = 700;

        jQuery('#btn-guardar-recurso').on('click', function () {
            if (!system_validarcampos('contenedor-form-recurso', 1)) {
                return;
            }

            var datos = getDataJson('contenedor-form-recurso');

            // El checkbox del interruptor no lleva "name" a propósito, para que
            // serializeObject no lo confunda con un checkbox nativo (que solo se
            // envía si está marcado). Se agrega aquí siempre como 0/1 explícito;
            // crear() lo ignora porque un recurso nuevo siempre nace activo.
            datos.estado = jQuery('#estado_recurso').is(':checked') ? 1 : 0;

            var url = modoFormularioRecurso === 'crear' ? 'request/recurso/crear' : 'request/recurso/editar';

            // El propio botón hace de indicador, así que no se levanta el loader
            // que tapa la pantalla: el formulario sigue a la vista.
            estadoBotonGuardar('ocupado');

            axiosSipleInterno('POST', url, {}, datos, false, function (respuesta) {
                if (!respuesta || respuesta.error != 0) {
                    estadoBotonGuardar('normal');

                    if (respuesta) {
                        notificarUsuario(respuesta.mensaje, 'error');
                    }

                    return;
                }

                estadoBotonGuardar('exito');

                // Un respiro para que se vea el check antes de que el panel se
                // cierre; sin esto el estado de éxito pasaría inadvertido.
                setTimeout(function () {
                    var modalRecurso = bootstrap.Modal.getInstance(document.getElementById('modal-recurso'));

                    if (modalRecurso) {
                        modalRecurso.hide();
                    }

                    estadoBotonGuardar('normal');
                    cargarRecursos();
                    avisarGuardado(modoFormularioRecurso === 'crear' ? 'Recurso creado correctamente' : 'Recurso actualizado correctamente');
                }, ESPERA_CONFIRMACION_GUARDADO);
            });
        });

        jQuery(document).ready(function () {
            cargarRecursos();

            iniciarGuiaSiCorresponde('recurso', function () {
                iniciarTourContextual('recurso', [
                    {
                        attachTo: { element: '#btn-nuevo-recurso', on: 'bottom' },
                        title: 'Tus servicios',
                        text: 'Aquí creas los servicios que ofreces, como masajes o tratamientos.'
                    },
                    {
                        attachTo: { element: '#nombre', on: 'bottom' },
                        title: 'Nombre del servicio',
                        text: 'Ponle un nombre claro a tu servicio, para que tú y tus clientes lo reconozcan fácil.',
                        beforeShowMe: function () {
                            return new Promise(function (resolver) {
                                var elementoModal = document.getElementById('modal-recurso');

                                jQuery(elementoModal).one('shown.bs.modal', function () {
                                    resolver();
                                });

                                document.getElementById('btn-nuevo-recurso').click();
                            });
                        }
                    }
                ]);
            });
        });
    </script>
@endsection
