@extends('layout.backoffice')

@section('title', 'Usuarios')

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

        /* Avisos sobre el empleado vinculado: el de la baja pesa más (--danger)
           que el meramente informativo de la reactivación. Mismos nombres y
           mismo peso visual que los del módulo de Empleados, porque son las dos
           caras de la misma cascada. */
        .aviso-vinculo {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            border-radius: var(--radius-sm);
            padding: 0.65rem 0.8rem;
            margin-top: 0.75rem;
            font-size: 0.8rem;
            line-height: 1.45;
        }

        .aviso-vinculo i {
            flex-shrink: 0;
            font-size: 0.95rem;
            margin-top: 0.05rem;
        }

        .aviso-baja-empleado {
            background-color: var(--danger-soft);
            color: var(--danger);
        }

        .aviso-alta-empleado {
            background-color: var(--bg-input);
            color: var(--text-secondary);
        }
    </style>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="titulo-pagina">Usuarios</h2>
            <p class="subtitulo-pagina">Administra las cuentas que pueden acceder a la plataforma y el rol que tiene cada una dentro del negocio.</p>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <label class="interruptor-moderno" id="filtro-mostrar-inactivos">
                <input type="checkbox" id="chk-mostrar-inactivos">
                <span class="pista-interruptor"></span>
                <span class="texto-interruptor">Mostrar inactivos</span>
            </label>
            <button type="button" id="btn-nuevo-usuario" class="btn-primario-accento" data-bs-toggle="modal" data-bs-target="#modal-usuario">
                <i class="bi bi-plus-lg"></i> Nuevo usuario
            </button>
        </div>
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <table id="tabla-usuarios" class="table table-striped align-middle w-100 fila-tabla-hover fila-tabla-amplia">
                <thead>
                    <tr>
                        <th></th>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Negocio</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade modal-moderno" id="modal-usuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header-moderno">
                    <span class="insignia-encabezado"><i class="bi bi-person-badge"></i></span>
                    <div>
                        <h5 class="titulo-modal-moderno" id="modal-usuario-titulo-texto">Nuevo usuario</h5>
                        <p class="subtitulo-modal-moderno" id="modal-usuario-subtitulo">Crea una cuenta para acceder a la plataforma</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-usuario">
                        <input type="hidden" id="id_usuario" name="id_usuario">

                        <div class="tarjeta-seccion-form">
                            <div class="etiqueta-seccion-form">Datos de la cuenta</div>

                            <div class="campo-flotante">
                                <i class="bi bi-person"></i>
                                <input type="text" id="usuario" name="usuario" maxlength="50" placeholder=" " class="system_validador_vacio">
                                <label for="usuario">Usuario</label>
                            </div>

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-card-text"></i>
                                <input type="text" id="nombre" name="nombre" maxlength="100" placeholder=" " class="system_validador_vacio">
                                <label for="nombre">Nombre</label>
                            </div>

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-envelope"></i>
                                <input type="email" id="email" name="email" maxlength="150" placeholder=" " class="system_validador_vacio system_validador_email">
                                <label for="email">Email</label>
                            </div>

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-key"></i>
                                <input type="password" id="clave" name="clave" maxlength="255" placeholder=" ">
                                <label for="clave">Clave</label>
                            </div>
                            <div class="ayuda-campo" id="hint-clave" hidden>Dejar vacío para no cambiar la clave actual.</div>
                        </div>

                        <div class="tarjeta-seccion-form">
                            <div class="etiqueta-seccion-form">Negocio y permisos</div>

                            <div class="campo-flotante">
                                <i class="bi bi-building"></i>
                                <select id="tenant_id" name="tenant_id" class="system_validador_vacio" @if (! $esSuperAdmin) disabled @endif>
                                    @if ($esSuperAdmin)
                                        <option value="">Seleccione...</option>
                                    @endif
                                    @foreach ($negocios as $negocio)
                                        <option value="{{ $negocio['id_negocio'] }}">{{ $negocio['nombre_negocio'] }}</option>
                                    @endforeach
                                </select>
                                <label for="tenant_id">Negocio</label>
                            </div>
                            @if (! $esSuperAdmin)
                                <input type="hidden" id="tenant_id_real" name="tenant_id" value="{{ session('tenant_id') }}">
                                <div class="ayuda-campo">Bloqueado a tu negocio actual.</div>
                            @endif

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-shield-check"></i>
                                <select id="id_rol" name="id_rol" class="system_validador_vacio">
                                    <option value="">Seleccione...</option>
                                    @foreach ($roles as $rol)
                                        <option value="{{ $rol['id_rol'] }}">{{ $rol['nombre_rol'] }}</option>
                                    @endforeach
                                </select>
                                <label for="id_rol">Rol</label>
                            </div>
                        </div>

                        {{-- Solo tiene sentido al editar: una cuenta recién creada siempre
                             nace activa, así que aquí no se le pregunta nada al usuario. --}}
                        <div class="tarjeta-seccion-form" id="seccion-estado-usuario" hidden>
                            <div class="etiqueta-seccion-form">Estado</div>

                            <label class="interruptor-moderno">
                                <input type="checkbox" id="estado_usuario">
                                <span class="pista-interruptor"></span>
                                <span class="texto-interruptor" id="texto-estado-usuario">Cuenta activa</span>
                            </label>

                            <div class="ayuda-campo mt-2">
                                Una cuenta inactiva no puede iniciar sesión y deja de aparecer en el listado, pero puede reactivarse en cualquier momento desde aquí.
                            </div>

                            {{-- Solo para cuentas de un empleado: se avisa en el momento,
                                 antes de guardar, de que la baja se lo lleva a él también. --}}
                            <div class="aviso-vinculo aviso-baja-empleado" id="aviso-baja-empleado" hidden>
                                <i class="bi bi-person-dash"></i>
                                <span>Esta cuenta es del empleado <b id="nombre-empleado-baja"></b>. Al desactivarla, ese empleado también quedará inactivo: además de perder el acceso, <b>dejará de poder asignarse a nuevas reservas</b>.</span>
                            </div>

                            <div class="aviso-vinculo aviso-alta-empleado" id="aviso-alta-empleado" hidden>
                                <i class="bi bi-info-circle"></i>
                                <span>Reactivar la cuenta no reactiva al empleado <b id="nombre-empleado-alta"></b>. Si tiene que volver a atender reservas, dalo de alta aparte desde el módulo de Empleados.</span>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-usuario" class="btn-guardar-moderno">
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
        var modoFormularioUsuario = 'crear';
        var tablaUsuarios;

        // Empleado de la cuenta que se está editando, si lo tiene. Es lo que
        // decide si dar de baja esta cuenta arrastra a alguien más.
        var empleadoVinculadoEnEdicion = null;

        function inicializarTooltips() {
            jQuery('[data-bs-toggle="tooltip"]').each(function () {
                var tooltipExistente = bootstrap.Tooltip.getInstance(this);
                if (tooltipExistente) {
                    tooltipExistente.dispose();
                }
                new bootstrap.Tooltip(this);
            });
        }

        function cargarUsuarios() {
            var incluirInactivos = jQuery('#chk-mostrar-inactivos').is(':checked') ? 1 : 0;

            axiosSipleInterno('GET', 'request/usuario/listar', { incluir_inactivos: incluirInactivos }, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarTablaUsuarios(respuesta.data.usuarios);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        // "Mostrar inactivos": vuelve a pedir el listado con el filtro nuevo.
        jQuery('#chk-mostrar-inactivos').on('change', function () {
            cargarUsuarios();
        });

        function pintarTablaUsuarios(usuarios) {
            if (tablaUsuarios) {
                tablaUsuarios.destroy();
                jQuery('#tabla-usuarios tbody').empty();
            }

            tablaUsuarios = jQuery('#tabla-usuarios').DataTable({
                data: usuarios,
                language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' },
                columns: [
                    {
                        data: null,
                        orderable: false,
                        render: function (fila) {
                            return generarAvatar(fila.nombre);
                        }
                    },
                    { data: 'usuario' },
                    { data: 'nombre' },
                    { data: 'email' },
                    {
                        data: 'nombre_rol',
                        render: function (data) {
                            return '<span class="badge-rol"><i class="bi bi-shield-check"></i> ' + data + '</span>';
                        }
                    },
                    {
                        data: 'nombre_negocio',
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
                        data: 'id_usuario',
                        orderable: false,
                        render: function (data) {
                            return '<button type="button" class="btn-accion-icono btn-editar-usuario" data-bs-toggle="tooltip" title="Editar" data-id_usuario="' + data + '"><i class="bi bi-pencil-square"></i></button>' +
                                   '<button type="button" class="btn-accion-icono btn-accion-eliminar btn-eliminar-usuario" data-bs-toggle="tooltip" title="Eliminar" data-id_usuario="' + data + '"><i class="bi bi-trash3"></i></button>';
                        }
                    }
                ]
            });

            tablaUsuarios.on('draw', function () {
                inicializarTooltips();
            });
        }

        /**
         * Los tres estados del botón de guardar que define el kit: normal,
         * "ocupado" (con su spinner) y "exito" (con su check).
         */
        function estadoBotonGuardar(estado) {
            var boton = jQuery('#btn-guardar-usuario');

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
        function establecerEstadoUsuario(activa) {
            jQuery('#estado_usuario').prop('checked', activa);
            jQuery('#texto-estado-usuario').text(activa ? 'Cuenta activa' : 'Cuenta inactiva');

            // Los avisos solo tienen sentido si hay alguien al otro lado del
            // vínculo: el de la baja mientras el interruptor está apagado, el
            // informativo mientras está encendido.
            var tieneEmpleado = empleadoVinculadoEnEdicion !== null;

            jQuery('#aviso-baja-empleado').prop('hidden', !(tieneEmpleado && !activa));
            jQuery('#aviso-alta-empleado').prop('hidden', !(tieneEmpleado && activa));
        }

        jQuery('#estado_usuario').on('change', function () {
            establecerEstadoUsuario(jQuery(this).is(':checked'));
        });

        function limpiarFormularioUsuario() {
            jQuery('#id_usuario').val('');
            jQuery('#usuario').val('');
            jQuery('#nombre').val('');
            jQuery('#email').val('');
            jQuery('#clave').val('');
            jQuery('#tenant_id').val('');
            jQuery('#id_rol').val('');
            jQuery('#contenedor-form-usuario .input_vacio').removeClass('input_vacio');
            jQuery('#contenedor-form-usuario #system_validador').remove();
            estadoBotonGuardar('normal');
            empleadoVinculadoEnEdicion = null;
            establecerEstadoUsuario(true);
            jQuery('#seccion-estado-usuario').prop('hidden', true);
        }

        jQuery('#btn-nuevo-usuario').on('click', function () {
            modoFormularioUsuario = 'crear';
            jQuery('#modal-usuario-titulo-texto').text('Nuevo usuario');
            jQuery('#modal-usuario-subtitulo').text('Crea una cuenta para acceder a la plataforma');
            limpiarFormularioUsuario();
            jQuery('#hint-clave').prop('hidden', true);
        });

        jQuery('#tabla-usuarios').on('click', '.btn-editar-usuario', function () {
            var fila = tablaUsuarios.row(jQuery(this).closest('tr')).data();

            modoFormularioUsuario = 'editar';
            jQuery('#modal-usuario-titulo-texto').text('Editar usuario');
            jQuery('#modal-usuario-subtitulo').text('Actualiza la cuenta de "' + fila.nombre + '"');
            limpiarFormularioUsuario();
            jQuery('#hint-clave').prop('hidden', false);

            jQuery('#id_usuario').val(fila.id_usuario);
            jQuery('#usuario').val(fila.usuario);
            jQuery('#nombre').val(fila.nombre);
            jQuery('#email').val(fila.email);

            jQuery('#tenant_id option[value="' + fila.tenant_id + '"]').prop('selected', true);
            jQuery('#id_rol option[value="' + fila.id_rol + '"]').prop('selected', true);

            // El vínculo se fija ANTES de pintar el interruptor: de él dependen
            // los avisos que establecerEstadoUsuario muestra o esconde.
            empleadoVinculadoEnEdicion = fila.id_empleado_vinculado
                ? { id: fila.id_empleado_vinculado, nombre: fila.nombre_empleado_vinculado || fila.nombre }
                : null;

            if (empleadoVinculadoEnEdicion) {
                jQuery('#nombre-empleado-baja, #nombre-empleado-alta').text(empleadoVinculadoEnEdicion.nombre);
            }

            // El interruptor solo aparece al editar: una cuenta nueva siempre
            // nace activa, así que no hay nada que preguntar en ese momento.
            jQuery('#seccion-estado-usuario').prop('hidden', false);
            establecerEstadoUsuario(fila.estado == 1);

            var modalUsuario = new bootstrap.Modal(document.getElementById('modal-usuario'));
            modalUsuario.show();
        });

        jQuery('#tabla-usuarios').on('click', '.btn-eliminar-usuario', function () {
            var fila = tablaUsuarios.row(jQuery(this).closest('tr')).data();
            var idUsuario = jQuery(this).data('id_usuario');

            // La papelera arrastra al empleado igual que el interruptor, así que
            // tiene que avisarlo igual: de lo contrario sería el mismo efecto
            // sin la misma advertencia.
            var textoEliminar = fila.id_empleado_vinculado
                ? 'Esta cuenta es del empleado <b>' + (fila.nombre_empleado_vinculado || fila.nombre) + '</b>.<br>'
                  + 'Al eliminarla, ese empleado también quedará inactivo y <b>dejará de poder asignarse a nuevas reservas</b>.'
                : 'Esta acción no se puede deshacer';

            Swal.fire({
                title: '¿Eliminar usuario?',
                html: textoEliminar,
                icon: 'warning',
                background: 'var(--bg-card)',
                color: 'var(--text-primary)',
                confirmButtonColor: colorVariable('--accent'),
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(function (resultado) {
                if (resultado.isConfirmed) {
                    axiosSipleInterno('POST', 'request/usuario/eliminar', {}, { id_usuario: idUsuario }, true, function (respuesta) {
                        if (respuesta.error == 0) {
                            notificarUsuario('Usuario eliminado correctamente', 'success');
                            cargarUsuarios();
                        } else {
                            notificarUsuario(respuesta.mensaje, 'error');
                        }
                    });
                }
            });
        });

        /** Cuánto se deja ver el check de "Guardado" antes de cerrar, en ms. */
        var ESPERA_CONFIRMACION_GUARDADO = 700;

        jQuery('#btn-guardar-usuario').on('click', function () {
            if (!system_validarcampos('contenedor-form-usuario', 1)) {
                return;
            }

            var datos = getDataJson('contenedor-form-usuario');

            if (modoFormularioUsuario === 'crear' && (!datos.clave || datos.clave === '')) {
                notificarUsuario('La clave es obligatoria', 'error');
                return;
            }

            // El checkbox del interruptor no lleva "name" a propósito, para que
            // serializeObject no lo confunda con un checkbox nativo (que solo se
            // envía si está marcado). Se agrega aquí siempre como 0/1 explícito;
            // crear() lo ignora porque una cuenta nueva siempre nace activa.
            datos.estado = jQuery('#estado_usuario').is(':checked') ? 1 : 0;

            // Dar de baja la cuenta de un empleado se lo lleva a él también: se
            // confirma antes, porque no se deshace sola y le quita la
            // asignabilidad a reservas, no solo el acceso.
            var arrastraEmpleado = modoFormularioUsuario === 'editar'
                && empleadoVinculadoEnEdicion !== null
                && datos.estado === 0;

            if (! arrastraEmpleado) {
                enviarFormularioUsuario(datos);

                return;
            }

            Swal.fire({
                title: '¿Desactivar también al empleado?',
                html: 'Esta cuenta es del empleado <b>' + empleadoVinculadoEnEdicion.nombre + '</b>.<br>'
                    + 'Al desactivarla, ese empleado quedará inactivo: perderá el acceso y <b>dejará de poder asignarse a nuevas reservas</b>.<br><br>'
                    + 'Para que vuelva a atender tendrás que darlo de alta aparte desde el módulo de Empleados.',
                icon: 'warning',
                background: colorVariable('--bg-card'),
                color: colorVariable('--text-primary'),
                confirmButtonColor: colorVariable('--danger'),
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar cuenta y empleado',
                cancelButtonText: 'Cancelar'
            }).then(function (resultado) {
                if (resultado.isConfirmed) {
                    enviarFormularioUsuario(datos);
                }
            });
        });

        /** Envío del formulario ya validado y confirmado. */
        function enviarFormularioUsuario(datos) {
            var url = modoFormularioUsuario === 'crear' ? 'request/usuario/crear' : 'request/usuario/editar';

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
                    var modalUsuario = bootstrap.Modal.getInstance(document.getElementById('modal-usuario'));

                    if (modalUsuario) {
                        modalUsuario.hide();
                    }

                    estadoBotonGuardar('normal');
                    notificarUsuario(modoFormularioUsuario === 'crear' ? 'Usuario creado correctamente' : 'Usuario actualizado correctamente', 'success');
                    cargarUsuarios();
                }, ESPERA_CONFIRMACION_GUARDADO);
            });
        }

        jQuery(document).ready(function () {
            cargarUsuarios();
        });
    </script>
@endsection
