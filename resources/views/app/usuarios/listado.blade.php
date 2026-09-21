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

            // El interruptor solo aparece al editar: una cuenta nueva siempre
            // nace activa, así que no hay nada que preguntar en ese momento.
            jQuery('#seccion-estado-usuario').prop('hidden', false);
            establecerEstadoUsuario(fila.estado == 1);

            var modalUsuario = new bootstrap.Modal(document.getElementById('modal-usuario'));
            modalUsuario.show();
        });

        jQuery('#tabla-usuarios').on('click', '.btn-eliminar-usuario', function () {
            var idUsuario = jQuery(this).data('id_usuario');

            Swal.fire({
                title: '¿Eliminar usuario?',
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
        });

        jQuery(document).ready(function () {
            cargarUsuarios();
        });
    </script>
@endsection
