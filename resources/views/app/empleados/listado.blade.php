@extends('layout.backoffice')

@section('title', 'Empleados')

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

        #modal-acceso .modal-title i {
            color: var(--accent);
        }

        .nombre-empleado-acceso {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.6rem 0.85rem;
            color: var(--text-primary);
            font-weight: 600;
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

        /* Avisos sobre el acceso al sistema: el de revocar pesa más (--danger)
           que el meramente informativo de la reactivación. */
        .aviso-acceso {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            border-radius: var(--radius-sm);
            padding: 0.65rem 0.8rem;
            margin-top: 0.75rem;
            font-size: 0.8rem;
            line-height: 1.45;
        }

        .aviso-acceso i {
            flex-shrink: 0;
            font-size: 0.95rem;
            margin-top: 0.05rem;
        }

        .aviso-revocar {
            background-color: var(--danger-soft);
            color: var(--danger);
        }

        .aviso-reactivar {
            background-color: var(--bg-input);
            color: var(--text-secondary);
        }
    </style>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="titulo-pagina">Empleados</h2>
            <p class="subtitulo-pagina">Gestiona el personal de tu negocio y sus accesos al sistema.</p>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <label class="interruptor-moderno" id="filtro-mostrar-inactivos">
                <input type="checkbox" id="chk-mostrar-inactivos">
                <span class="pista-interruptor"></span>
                <span class="texto-interruptor">Mostrar inactivos</span>
            </label>
            <button type="button" id="btn-nuevo-empleado" class="btn-primario-accento" data-bs-toggle="modal" data-bs-target="#modal-empleado">
                <i class="bi bi-person-plus"></i> Nuevo empleado
            </button>
        </div>
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <table id="tabla-empleados" class="table table-striped align-middle w-100 fila-tabla-hover fila-tabla-amplia">
                <thead>
                    <tr>
                        <th></th>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Cargo</th>
                        <th>Comisión</th>
                        <th>Acceso</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade modal-moderno" id="modal-empleado" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header-moderno">
                    <span class="insignia-encabezado"><i class="bi bi-person-badge"></i></span>
                    <div>
                        <h5 class="titulo-modal-moderno" id="modal-empleado-titulo-texto">Nuevo empleado</h5>
                        <p class="subtitulo-modal-moderno" id="modal-empleado-subtitulo">Suma a alguien de tu equipo</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-empleado">
                        <input type="hidden" id="id_empleado" name="id_empleado">

                        <div class="tarjeta-seccion-form">
                            <div class="etiqueta-seccion-form">Información personal</div>

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

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-briefcase"></i>
                                <input type="text" id="cargo" name="cargo" maxlength="100" placeholder=" ">
                                <label for="cargo">Cargo</label>
                            </div>
                        </div>

                        <div class="tarjeta-seccion-form">
                            <div class="etiqueta-seccion-form">Comisión</div>

                            <div class="rotulo-stepper">Porcentaje de comisión</div>
                            <div class="stepper-campo">
                                <button type="button" class="btn-stepper" data-paso="-0.5" aria-label="Restar medio punto">
                                    <i class="bi bi-dash-lg"></i>
                                </button>
                                <input type="number" id="porcentaje_comision" name="porcentaje_comision" min="0" max="100" step="0.5" class="valor-stepper">
                                <button type="button" class="btn-stepper" data-paso="0.5" aria-label="Sumar medio punto">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                            <div class="ayuda-campo mt-2 text-center">
                                Se ajusta de medio en medio punto porcentual, de 0% a 100%.
                            </div>
                        </div>

                        {{-- Solo tiene sentido al editar: un empleado recién creado siempre
                             nace activo, así que aquí no se le pregunta nada al usuario. --}}
                        <div class="tarjeta-seccion-form" id="seccion-estado-empleado" hidden>
                            <div class="etiqueta-seccion-form">Estado</div>

                            <label class="interruptor-moderno">
                                <input type="checkbox" id="estado_empleado">
                                <span class="pista-interruptor"></span>
                                <span class="texto-interruptor" id="texto-estado-empleado">Empleado activo</span>
                            </label>

                            <div class="ayuda-campo mt-2">
                                Un empleado inactivo deja de aparecer en el listado, pero puede reactivarse en cualquier momento desde aquí.
                            </div>

                            {{-- Solo para empleados con acceso al sistema: se avisa en el
                                 momento, antes de guardar, de que la baja también le quita
                                 la cuenta. --}}
                            <div class="aviso-acceso aviso-revocar" id="aviso-revocar-acceso" hidden>
                                <i class="bi bi-shield-exclamation"></i>
                                <span>Este empleado tiene acceso al sistema. Al desactivarlo también se desactivará su usuario y no podrá volver a iniciar sesión.</span>
                            </div>

                            <div class="aviso-acceso aviso-reactivar" id="aviso-reactivar-acceso" hidden>
                                <i class="bi bi-info-circle"></i>
                                <span>Reactivar al empleado no le devuelve el acceso al sistema. Si necesita volver a entrar, reactiva su usuario desde el módulo de Usuarios.</span>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-empleado" class="btn-guardar-moderno">
                        <i class="bi bi-check2 icono-guardar"></i>
                        <span id="texto-btn-guardar">Guardar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-acceso" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="bi bi-key-fill"></i>
                        <span>Crear acceso al sistema</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-acceso">
                        <input type="hidden" id="id_empleado_acceso" name="id_empleado">

                        <div class="mb-3">
                            <label class="form-label">Empleado</label>
                            <div class="nombre-empleado-acceso" id="nombre-empleado-acceso">—</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Usuario</label>
                            <input type="text" id="usuario_acceso" name="usuario" maxlength="50" class="form-control system_validador_vacio">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" id="email_acceso" name="email" maxlength="150" class="form-control system_validador_vacio system_validador_email">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Clave</label>
                            <input type="password" id="clave_acceso" name="clave" maxlength="255" class="form-control system_validador_vacio">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-acceso" class="btn-primario-accento">
                        <i class="bi bi-key-fill"></i> Crear acceso
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        var modoFormularioEmpleado = 'crear';
        var tablaEmpleados;

        function inicializarTooltips() {
            jQuery('[data-bs-toggle="tooltip"]').each(function () {
                var tooltipExistente = bootstrap.Tooltip.getInstance(this);
                if (tooltipExistente) {
                    tooltipExistente.dispose();
                }
                new bootstrap.Tooltip(this);
            });
        }

        function cargarEmpleados() {
            var incluirInactivos = jQuery('#chk-mostrar-inactivos').is(':checked') ? 1 : 0;

            axiosSipleInterno('GET', 'request/empleado/listar', { incluir_inactivos: incluirInactivos }, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarTablaEmpleados(respuesta.data.empleados);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        // "Mostrar inactivos": vuelve a pedir el listado con el filtro nuevo.
        jQuery('#chk-mostrar-inactivos').on('change', function () {
            cargarEmpleados();
        });

        function pintarTablaEmpleados(empleados) {
            if (tablaEmpleados) {
                tablaEmpleados.destroy();
                jQuery('#tabla-empleados tbody').empty();
            }

            tablaEmpleados = jQuery('#tabla-empleados').DataTable({
                data: empleados,
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
                        data: 'cargo',
                        render: function (data) {
                            return data ? data : '<span class="text-muted">—</span>';
                        }
                    },
                    {
                        data: 'porcentaje_comision',
                        render: function (data) {
                            return (data !== null && data !== '') ? (parseFloat(data) + '%') : '<span class="text-muted">—</span>';
                        }
                    },
                    {
                        data: 'id_usuario',
                        render: function (data) {
                            return data
                                ? '<span class="badge-estado-activo"><i class="bi bi-check-circle-fill"></i> Con acceso</span>'
                                : '<span class="badge-estado-inactivo"><i class="bi bi-dash-circle"></i> Sin acceso</span>';
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
                        data: 'id_empleado',
                        orderable: false,
                        render: function (data, tipo, fila) {
                            var botones = '<button type="button" class="btn-accion-icono btn-editar-empleado" data-bs-toggle="tooltip" title="Editar" data-id_empleado="' + data + '"><i class="bi bi-pencil-square"></i></button>' +
                                          '<button type="button" class="btn-accion-icono btn-accion-eliminar btn-eliminar-empleado" data-bs-toggle="tooltip" title="Eliminar" data-id_empleado="' + data + '"><i class="bi bi-trash3"></i></button>';

                            if (!fila.id_usuario) {
                                botones += '<button type="button" class="btn-accion-icono btn-crear-acceso" data-bs-toggle="tooltip" title="Dar acceso al sistema" data-id_empleado="' + data + '"><i class="bi bi-key-fill"></i></button>';
                            }

                            return botones;
                        }
                    }
                ]
            });

            tablaEmpleados.on('draw', function () {
                inicializarTooltips();
            });
        }

        /**
         * Los tres estados del botón de guardar que define el kit: normal,
         * "ocupado" (con su spinner) y "exito" (con su check).
         */
        function estadoBotonGuardar(estado) {
            var boton = jQuery('#btn-guardar-empleado');

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

        /**
         * Si el empleado que se está editando tiene acceso al sistema, el
         * interruptor deja de ser inocuo: apagarlo también le quita la cuenta.
         * Se guarda aquí para decidir qué avisos mostrar y si hay que confirmar.
         */
        var empleadoEnEdicionTieneAcceso = false;

        /** Refleja el estado en el interruptor, su texto y los avisos de acceso. */
        function establecerEstadoEmpleado(activo) {
            jQuery('#estado_empleado').prop('checked', activo);
            jQuery('#texto-estado-empleado').text(activo ? 'Empleado activo' : 'Empleado inactivo');

            // Sin acceso vinculado no hay nada que advertir: el interruptor solo
            // afecta al listado.
            jQuery('#aviso-revocar-acceso').prop('hidden', !(empleadoEnEdicionTieneAcceso && !activo));
            jQuery('#aviso-reactivar-acceso').prop('hidden', !(empleadoEnEdicionTieneAcceso && activo));
        }

        jQuery('#estado_empleado').on('change', function () {
            establecerEstadoEmpleado(jQuery(this).is(':checked'));
        });

        function limpiarFormularioEmpleado() {
            jQuery('#id_empleado').val('');
            jQuery('#nombre').val('');
            jQuery('#telefono').val('');
            jQuery('#email').val('');
            jQuery('#cargo').val('');
            // El contador arranca en 0 y no vacío: un stepper sin número se ve
            // roto. 0% de comisión es además el valor que ya se usa cuando el
            // campo está en NULL (Comisiones lo trata igual con COALESCE a 0),
            // así que no cambia ningún cálculo real.
            jQuery('#porcentaje_comision').val(0);
            jQuery('#contenedor-form-empleado .input_vacio').removeClass('input_vacio');
            jQuery('#contenedor-form-empleado #system_validador').remove();
            estadoBotonGuardar('normal');
            // Se limpia antes de pintar el interruptor: si quedara el valor del
            // empleado anterior, se mostraría un aviso que no corresponde.
            empleadoEnEdicionTieneAcceso = false;
            establecerEstadoEmpleado(true);
            jQuery('#seccion-estado-empleado').prop('hidden', true);
        }

        function limpiarFormularioAcceso() {
            jQuery('#id_empleado_acceso').val('');
            jQuery('#usuario_acceso').val('');
            jQuery('#email_acceso').val('');
            jQuery('#clave_acceso').val('');
            jQuery('#nombre-empleado-acceso').text('—');
            jQuery('#contenedor-form-acceso .input_vacio').removeClass('input_vacio');
            jQuery('#contenedor-form-acceso #system_validador').remove();
        }

        jQuery('#btn-nuevo-empleado').on('click', function () {
            modoFormularioEmpleado = 'crear';
            jQuery('#modal-empleado-titulo-texto').text('Nuevo empleado');
            jQuery('#modal-empleado-subtitulo').text('Suma a alguien de tu equipo');
            limpiarFormularioEmpleado();
        });

        jQuery('#tabla-empleados').on('click', '.btn-editar-empleado', function () {
            var fila = tablaEmpleados.row(jQuery(this).closest('tr')).data();

            modoFormularioEmpleado = 'editar';
            jQuery('#modal-empleado-titulo-texto').text('Editar empleado');
            jQuery('#modal-empleado-subtitulo').text('Actualiza los datos de "' + fila.nombre + '"');
            limpiarFormularioEmpleado();

            jQuery('#id_empleado').val(fila.id_empleado);
            jQuery('#nombre').val(fila.nombre);
            jQuery('#telefono').val(fila.telefono);
            jQuery('#email').val(fila.email);
            jQuery('#cargo').val(fila.cargo);
            // NULL y 0 valen lo mismo para Comisiones (COALESCE de por medio), así
            // que un empleado sin comisión asignada precarga el contador en 0.
            jQuery('#porcentaje_comision').val(fila.porcentaje_comision !== null ? fila.porcentaje_comision : 0);

            // El interruptor solo aparece al editar: un empleado nuevo siempre
            // nace activo, así que no hay nada que preguntar en ese momento.
            jQuery('#seccion-estado-empleado').prop('hidden', false);
            empleadoEnEdicionTieneAcceso = !!fila.id_usuario;
            establecerEstadoEmpleado(fila.estado == 1);

            var modalEmpleado = new bootstrap.Modal(document.getElementById('modal-empleado'));
            modalEmpleado.show();
        });

        jQuery('#tabla-empleados').on('click', '.btn-crear-acceso', function () {
            var fila = tablaEmpleados.row(jQuery(this).closest('tr')).data();

            limpiarFormularioAcceso();

            jQuery('#id_empleado_acceso').val(fila.id_empleado);
            jQuery('#nombre-empleado-acceso').text(fila.nombre);

            if (fila.email) {
                jQuery('#email_acceso').val(fila.email);
            }

            var modalAcceso = new bootstrap.Modal(document.getElementById('modal-acceso'));
            modalAcceso.show();
        });

        jQuery('#tabla-empleados').on('click', '.btn-eliminar-empleado', function () {
            var fila = tablaEmpleados.row(jQuery(this).closest('tr')).data();
            var idEmpleado = fila.id_empleado;

            // La papelera también da de baja, así que arrastra la misma cascada
            // que el interruptor: si hay acceso vinculado, hay que decirlo.
            var textoEliminar = fila.id_usuario
                ? 'Este empleado tiene acceso al sistema.<br>Su usuario también quedará inactivo y <b>no podrá volver a iniciar sesión</b>.'
                : 'El empleado dejará de aparecer en el listado.';

            Swal.fire({
                title: '¿Eliminar empleado?',
                html: textoEliminar,
                icon: 'warning',
                background: colorVariable('--bg-card'),
                color: colorVariable('--text-primary'),
                confirmButtonColor: colorVariable('--accent'),
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(function (resultado) {
                if (resultado.isConfirmed) {
                    axiosSipleInterno('POST', 'request/empleado/eliminar', {}, { id_empleado: idEmpleado }, true, function (respuesta) {
                        if (respuesta.error == 0) {
                            notificarUsuario('Empleado eliminado correctamente', 'success');
                            cargarEmpleados();
                        } else {
                            notificarUsuario(respuesta.mensaje, 'error');
                        }
                    });
                }
            });
        });

        /** Cuánto se deja ver el check de "Guardado" antes de cerrar, en ms. */
        var ESPERA_CONFIRMACION_GUARDADO = 700;

        jQuery('#btn-guardar-empleado').on('click', function () {
            if (!system_validarcampos('contenedor-form-empleado', 1)) {
                return;
            }

            var datos = getDataJson('contenedor-form-empleado');

            if (datos.email && datos.email.trim() !== '') {
                var exprEmail = /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
                if (!exprEmail.test(datos.email)) {
                    notificarUsuario('El email no es válido', 'error');
                    return;
                }
            }

            // El checkbox del interruptor no lleva "name" a propósito, para que
            // serializeObject no lo confunda con un checkbox nativo (que solo se
            // envía si está marcado). Se agrega aquí siempre como 0/1 explícito;
            // crear() lo ignora porque un empleado nuevo siempre nace activo.
            datos.estado = jQuery('#estado_empleado').is(':checked') ? 1 : 0;

            // Dar de baja a alguien con acceso le quita la cuenta: se confirma
            // antes, porque es una consecuencia que no se deshace sola.
            var revocaAcceso = modoFormularioEmpleado === 'editar'
                && empleadoEnEdicionTieneAcceso
                && datos.estado === 0;

            if (! revocaAcceso) {
                enviarFormularioEmpleado(datos);

                return;
            }

            Swal.fire({
                title: '¿Desactivar y quitar el acceso?',
                html: 'Este empleado tiene acceso al sistema.<br>Al desactivarlo, su usuario también quedará inactivo y <b>no podrá volver a iniciar sesión</b>.<br><br>Para devolverle el acceso más adelante tendrás que reactivar su usuario desde el módulo de Usuarios.',
                icon: 'warning',
                background: colorVariable('--bg-card'),
                color: colorVariable('--text-primary'),
                confirmButtonColor: colorVariable('--danger'),
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar y quitar el acceso',
                cancelButtonText: 'Cancelar'
            }).then(function (resultado) {
                if (resultado.isConfirmed) {
                    enviarFormularioEmpleado(datos);
                }
            });
        });

        /** Envío del formulario ya validado y confirmado. */
        function enviarFormularioEmpleado(datos) {
            var url = modoFormularioEmpleado === 'crear' ? 'request/empleado/crear' : 'request/empleado/editar';

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
                    var modalEmpleado = bootstrap.Modal.getInstance(document.getElementById('modal-empleado'));

                    if (modalEmpleado) {
                        modalEmpleado.hide();
                    }

                    estadoBotonGuardar('normal');
                    cargarEmpleados();
                    avisarGuardado(modoFormularioEmpleado === 'crear' ? 'Empleado creado correctamente' : 'Empleado actualizado correctamente');
                }, ESPERA_CONFIRMACION_GUARDADO);
            });
        }

        jQuery('#btn-guardar-acceso').on('click', function () {
            if (!system_validarcampos('contenedor-form-acceso', 1)) {
                return;
            }

            var datos = getDataJson('contenedor-form-acceso');

            axiosSipleInterno('POST', 'request/empleado/crear-acceso', {}, datos, true, function (respuesta) {
                if (respuesta.error == 0) {
                    var modalAcceso = bootstrap.Modal.getInstance(document.getElementById('modal-acceso'));
                    if (modalAcceso) {
                        modalAcceso.hide();
                    }
                    notificarUsuario('Acceso creado correctamente', 'success', 'reload');
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        jQuery(document).ready(function () {
            cargarEmpleados();

            iniciarGuiaSiCorresponde('empleado', function () {
                iniciarTourContextual('empleado', [
                    {
                        attachTo: { element: '#btn-nuevo-empleado', on: 'bottom' },
                        title: 'Tu equipo',
                        text: 'Aquí sumas a las personas que trabajan en tu negocio y atienden a tus clientes.'
                    },
                    {
                        attachTo: { element: '#nombre', on: 'bottom' },
                        title: 'Nombre del empleado',
                        text: 'Escribe el nombre completo de la persona que estás agregando a tu equipo.',
                        beforeShowMe: function () {
                            return new Promise(function (resolver) {
                                var elementoModal = document.getElementById('modal-empleado');

                                jQuery(elementoModal).one('shown.bs.modal', function () {
                                    resolver();
                                });

                                document.getElementById('btn-nuevo-empleado').click();
                            });
                        }
                    }
                ]);
            });
        });
    </script>
@endsection
