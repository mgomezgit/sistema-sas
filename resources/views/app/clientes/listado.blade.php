@extends('layout.backoffice')

@section('title', 'Clientes')

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
            <h2 class="titulo-pagina">Clientes</h2>
            <p class="subtitulo-pagina">Gestiona la base de clientes de tu negocio.</p>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <label class="interruptor-moderno" id="filtro-mostrar-inactivos">
                <input type="checkbox" id="chk-mostrar-inactivos">
                <span class="pista-interruptor"></span>
                <span class="texto-interruptor">Mostrar inactivos</span>
            </label>
            <button type="button" id="btn-nuevo-cliente" class="btn-primario-accento" data-bs-toggle="modal" data-bs-target="#modal-cliente">
                <i class="bi bi-person-plus"></i> Nuevo cliente
            </button>
        </div>
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <table id="tabla-clientes" class="table table-striped align-middle w-100 fila-tabla-hover fila-tabla-amplia">
                <thead>
                    <tr>
                        <th></th>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade modal-moderno" id="modal-cliente" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header-moderno">
                    <span class="insignia-encabezado"><i class="bi bi-person-vcard"></i></span>
                    <div>
                        <h5 class="titulo-modal-moderno" id="modal-cliente-titulo-texto">Nuevo cliente</h5>
                        <p class="subtitulo-modal-moderno" id="modal-cliente-subtitulo">Registra a una persona en tu base de clientes</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-cliente">
                        <input type="hidden" id="id_cliente" name="id_cliente">

                        <div class="tarjeta-seccion-form">
                            <div class="etiqueta-seccion-form">Información de contacto</div>

                            <div class="campo-flotante">
                                <i class="bi bi-person"></i>
                                <input type="text" id="nombre" name="nombre" maxlength="150" placeholder=" " class="system_validador_vacio">
                                <label for="nombre">Nombre</label>
                            </div>

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-telephone"></i>
                                <input type="text" id="telefono" name="telefono" maxlength="30" placeholder=" " class="system_validador_vacio">
                                <label for="telefono">Teléfono</label>
                            </div>

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-envelope"></i>
                                <input type="email" id="email" name="email" maxlength="150" placeholder=" ">
                                <label for="email">Email</label>
                            </div>
                        </div>

                        <div class="tarjeta-seccion-form">
                            <div class="etiqueta-seccion-form">Información adicional</div>

                            <div class="campo-flotante">
                                <i class="bi bi-person-badge"></i>
                                <input type="text" id="documento_identidad" name="documento_identidad" maxlength="50" placeholder=" ">
                                <label for="documento_identidad">Documento de identidad</label>
                            </div>

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-calendar-heart"></i>
                                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" placeholder=" ">
                                <label for="fecha_nacimiento">Fecha de nacimiento</label>
                            </div>

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-journal-text"></i>
                                <textarea id="notas" name="notas" rows="3" placeholder=" "></textarea>
                                <label for="notas">Notas</label>
                            </div>
                        </div>

                        {{-- Solo tiene sentido al editar: un cliente recién creado siempre
                             nace activo, así que aquí no se le pregunta nada al usuario. --}}
                        <div class="tarjeta-seccion-form" id="seccion-estado-cliente" hidden>
                            <div class="etiqueta-seccion-form">Estado</div>

                            <label class="interruptor-moderno">
                                <input type="checkbox" id="estado_cliente">
                                <span class="pista-interruptor"></span>
                                <span class="texto-interruptor" id="texto-estado-cliente">Cliente activo</span>
                            </label>

                            <div class="ayuda-campo mt-2">
                                Un cliente inactivo deja de aparecer en el listado, pero puede reactivarse en cualquier momento desde aquí.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-cliente" class="btn-guardar-moderno">
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
        var modoFormularioCliente = 'crear';
        var tablaClientes;

        function inicializarTooltips() {
            jQuery('[data-bs-toggle="tooltip"]').each(function () {
                var tooltipExistente = bootstrap.Tooltip.getInstance(this);
                if (tooltipExistente) {
                    tooltipExistente.dispose();
                }
                new bootstrap.Tooltip(this);
            });
        }

        function cargarClientes() {
            var incluirInactivos = jQuery('#chk-mostrar-inactivos').is(':checked') ? 1 : 0;

            axiosSipleInterno('GET', 'request/cliente/listar', { incluir_inactivos: incluirInactivos }, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarTablaClientes(respuesta.data.clientes);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        // "Mostrar inactivos": vuelve a pedir el listado con el filtro nuevo.
        jQuery('#chk-mostrar-inactivos').on('change', function () {
            cargarClientes();
        });

        function pintarTablaClientes(clientes) {
            if (tablaClientes) {
                tablaClientes.destroy();
                jQuery('#tabla-clientes tbody').empty();
            }

            tablaClientes = jQuery('#tabla-clientes').DataTable({
                data: clientes,
                language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' },
                columns: [
                    {
                        data: null,
                        orderable: false,
                        render: function (fila) {
                            return generarAvatar(fila.nombre);
                        }
                    },
                    { data: 'nombre' },
                    { data: 'telefono' },
                    {
                        data: 'email',
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
                        data: 'id_cliente',
                        orderable: false,
                        render: function (data) {
                            return '<button type="button" class="btn-accion-icono btn-editar-cliente" data-bs-toggle="tooltip" title="Editar" data-id_cliente="' + data + '"><i class="bi bi-pencil-square"></i></button>' +
                                   '<button type="button" class="btn-accion-icono btn-accion-eliminar btn-eliminar-cliente" data-bs-toggle="tooltip" title="Eliminar" data-id_cliente="' + data + '"><i class="bi bi-trash3"></i></button>';
                        }
                    }
                ]
            });

            tablaClientes.on('draw', function () {
                inicializarTooltips();
            });
        }

        /**
         * Los tres estados del botón de guardar que define el kit: normal,
         * "ocupado" (con su spinner) y "exito" (con su check).
         */
        function estadoBotonGuardar(estado) {
            var boton = jQuery('#btn-guardar-cliente');

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
        function establecerEstadoCliente(activo) {
            jQuery('#estado_cliente').prop('checked', activo);
            jQuery('#texto-estado-cliente').text(activo ? 'Cliente activo' : 'Cliente inactivo');
        }

        jQuery('#estado_cliente').on('change', function () {
            establecerEstadoCliente(jQuery(this).is(':checked'));
        });

        function limpiarFormularioCliente() {
            jQuery('#id_cliente').val('');
            jQuery('#nombre').val('');
            jQuery('#telefono').val('');
            jQuery('#email').val('');
            jQuery('#documento_identidad').val('');
            jQuery('#fecha_nacimiento').val('');
            jQuery('#notas').val('');
            jQuery('#contenedor-form-cliente .input_vacio').removeClass('input_vacio');
            jQuery('#contenedor-form-cliente #system_validador').remove();
            estadoBotonGuardar('normal');
            establecerEstadoCliente(true);
            jQuery('#seccion-estado-cliente').prop('hidden', true);
        }

        jQuery('#btn-nuevo-cliente').on('click', function () {
            modoFormularioCliente = 'crear';
            jQuery('#modal-cliente-titulo-texto').text('Nuevo cliente');
            jQuery('#modal-cliente-subtitulo').text('Registra a una persona en tu base de clientes');
            limpiarFormularioCliente();
        });

        jQuery('#tabla-clientes').on('click', '.btn-editar-cliente', function () {
            var fila = tablaClientes.row(jQuery(this).closest('tr')).data();

            modoFormularioCliente = 'editar';
            jQuery('#modal-cliente-titulo-texto').text('Editar cliente');
            jQuery('#modal-cliente-subtitulo').text('Actualiza los datos de "' + fila.nombre + '"');
            limpiarFormularioCliente();

            jQuery('#id_cliente').val(fila.id_cliente);
            jQuery('#nombre').val(fila.nombre);
            jQuery('#telefono').val(fila.telefono);
            jQuery('#email').val(fila.email);
            jQuery('#documento_identidad').val(fila.documento_identidad);
            jQuery('#fecha_nacimiento').val(fila.fecha_nacimiento);
            jQuery('#notas').val(fila.notas);

            // El interruptor solo aparece al editar: un cliente nuevo siempre
            // nace activo, así que no hay nada que preguntar en ese momento.
            jQuery('#seccion-estado-cliente').prop('hidden', false);
            establecerEstadoCliente(fila.estado == 1);

            var modalCliente = new bootstrap.Modal(document.getElementById('modal-cliente'));
            modalCliente.show();
        });

        jQuery('#tabla-clientes').on('click', '.btn-eliminar-cliente', function () {
            var idCliente = jQuery(this).data('id_cliente');

            Swal.fire({
                title: '¿Eliminar cliente?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                background: 'var(--bg-card)',
                color: 'var(--text-primary)',
                confirmButtonColor: colorVariable('--accent'),
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(function (resultado) {
                if (resultado.isConfirmed) {
                    axiosSipleInterno('POST', 'request/cliente/eliminar', {}, { id_cliente: idCliente }, true, function (respuesta) {
                        if (respuesta.error == 0) {
                            notificarUsuario('Cliente eliminado correctamente', 'success');
                            cargarClientes();
                        } else {
                            notificarUsuario(respuesta.mensaje, 'error');
                        }
                    });
                }
            });
        });

        /** Cuánto se deja ver el check de "Guardado" antes de cerrar, en ms. */
        var ESPERA_CONFIRMACION_GUARDADO = 700;

        jQuery('#btn-guardar-cliente').on('click', function () {
            if (!system_validarcampos('contenedor-form-cliente', 1)) {
                return;
            }

            var datos = getDataJson('contenedor-form-cliente');

            if (datos.email && datos.email.trim() !== '') {
                var exprEmail = /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
                if (!exprEmail.test(datos.email)) {
                    notificarUsuario('El email no es válido', 'error');
                    return;
                }
            }

            var url = modoFormularioCliente === 'crear' ? 'request/cliente/crear' : 'request/cliente/editar';

            // El checkbox del interruptor no lleva "name" a propósito, para que
            // serializeObject no lo confunda con un checkbox nativo (que solo se
            // envía si está marcado). Se agrega aquí siempre como 0/1 explícito;
            // crear() lo ignora porque un cliente nuevo siempre nace activo.
            datos.estado = jQuery('#estado_cliente').is(':checked') ? 1 : 0;

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
                    var modalCliente = bootstrap.Modal.getInstance(document.getElementById('modal-cliente'));

                    if (modalCliente) {
                        modalCliente.hide();
                    }

                    estadoBotonGuardar('normal');
                    cargarClientes();
                    avisarGuardado(modoFormularioCliente === 'crear' ? 'Cliente creado correctamente' : 'Cliente actualizado correctamente');
                }, ESPERA_CONFIRMACION_GUARDADO);
            });
        });

        jQuery(document).ready(function () {
            cargarClientes();

            iniciarGuiaSiCorresponde('cliente', function () {
                iniciarTourContextual('cliente', [
                    {
                        attachTo: { element: '#btn-nuevo-cliente', on: 'bottom' },
                        title: 'Tus clientes',
                        text: 'Aquí registras a las personas que reservan contigo, para llevar su historial y agendar más rápido.'
                    },
                    {
                        attachTo: { element: '#nombre', on: 'bottom' },
                        title: 'Nombre del cliente',
                        text: 'Escribe el nombre de tu cliente para poder identificarlo en sus futuras reservas.',
                        beforeShowMe: function () {
                            return new Promise(function (resolver) {
                                var elementoModal = document.getElementById('modal-cliente');

                                jQuery(elementoModal).one('shown.bs.modal', function () {
                                    resolver();
                                });

                                document.getElementById('btn-nuevo-cliente').click();
                            });
                        }
                    }
                ]);
            });
        });
    </script>
@endsection
