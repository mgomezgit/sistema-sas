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

        #modal-usuario .modal-title i {
            color: var(--accent);
        }
    </style>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="titulo-pagina">Usuarios</h2>
            <p class="subtitulo-pagina">Administra las cuentas que pueden acceder a la plataforma y el rol que tiene cada una dentro del negocio.</p>
        </div>
        <button type="button" id="btn-nuevo-usuario" class="btn-primario-accento" data-bs-toggle="modal" data-bs-target="#modal-usuario">
            <i class="bi bi-plus-lg"></i> Nuevo usuario
        </button>
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <table id="tabla-usuarios" class="table table-striped align-middle w-100 fila-tabla-hover">
                <thead>
                    <tr>
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

    <div class="modal fade" id="modal-usuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="bi bi-person-badge"></i>
                        <span id="modal-usuario-titulo-texto">Nuevo usuario</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-usuario">
                        <input type="hidden" id="id_usuario" name="id_usuario">

                        <div class="mb-3">
                            <label class="form-label">Usuario</label>
                            <input type="text" id="usuario" name="usuario" maxlength="50" class="form-control system_validador_vacio">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" id="nombre" name="nombre" maxlength="100" class="form-control system_validador_vacio">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" id="email" name="email" maxlength="150" class="form-control system_validador_vacio system_validador_email">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Clave</label>
                            <input type="password" id="clave" name="clave" maxlength="255" class="form-control">
                            <small id="hint-clave" class="text-secondary" style="display: none;">Dejar vacío para no cambiar</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-flex align-items-center gap-2">
                                Negocio
                                @if (! $esSuperAdmin)
                                    <i class="bi bi-lock-fill text-muted" data-bs-toggle="tooltip" title="Bloqueado a tu negocio actual"></i>
                                @endif
                            </label>
                            <select id="tenant_id" name="tenant_id" class="form-select system_validador_vacio" @if (! $esSuperAdmin) disabled @endif>
                                @if ($esSuperAdmin)
                                    <option value="">Seleccione...</option>
                                @endif
                                @foreach ($negocios as $negocio)
                                    <option value="{{ $negocio['id_negocio'] }}">{{ $negocio['nombre_negocio'] }}</option>
                                @endforeach
                            </select>
                            @if (! $esSuperAdmin)
                                <input type="hidden" id="tenant_id_real" name="tenant_id" value="{{ session('tenant_id') }}">
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rol</label>
                            <select id="id_rol" name="id_rol" class="form-select system_validador_vacio">
                                <option value="">Seleccione...</option>
                                @foreach ($roles as $rol)
                                    <option value="{{ $rol['id_rol'] }}">{{ $rol['nombre_rol'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-usuario" class="btn-primario-accento">
                        <i class="bi bi-check2"></i> Guardar
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
            axiosSipleInterno('GET', 'request/usuario/listar', {}, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarTablaUsuarios(respuesta.data.usuarios);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        function pintarTablaUsuarios(usuarios) {
            if (tablaUsuarios) {
                tablaUsuarios.destroy();
                jQuery('#tabla-usuarios tbody').empty();
            }

            tablaUsuarios = jQuery('#tabla-usuarios').DataTable({
                data: usuarios,
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.11/i18n/es-ES.json' },
                columns: [
                    { data: 'usuario' },
                    { data: 'nombre' },
                    { data: 'email' },
                    { data: 'nombre_rol' },
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
        }

        jQuery('#btn-nuevo-usuario').on('click', function () {
            modoFormularioUsuario = 'crear';
            jQuery('#modal-usuario-titulo-texto').text('Nuevo usuario');
            limpiarFormularioUsuario();
            jQuery('#hint-clave').hide();
        });

        jQuery('#tabla-usuarios').on('click', '.btn-editar-usuario', function () {
            var fila = tablaUsuarios.row(jQuery(this).closest('tr')).data();

            modoFormularioUsuario = 'editar';
            jQuery('#modal-usuario-titulo-texto').text('Editar usuario');
            limpiarFormularioUsuario();
            jQuery('#hint-clave').show();

            jQuery('#id_usuario').val(fila.id_usuario);
            jQuery('#usuario').val(fila.usuario);
            jQuery('#nombre').val(fila.nombre);
            jQuery('#email').val(fila.email);

            jQuery('#tenant_id option[value="' + fila.tenant_id + '"]').prop('selected', true);
            jQuery('#id_rol option[value="' + fila.id_rol + '"]').prop('selected', true);

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

        jQuery('#btn-guardar-usuario').on('click', function () {
            if (!system_validarcampos('contenedor-form-usuario', 1)) {
                return;
            }

            var datos = getDataJson('contenedor-form-usuario');

            if (modoFormularioUsuario === 'crear' && (!datos.clave || datos.clave === '')) {
                notificarUsuario('La clave es obligatoria', 'error');
                return;
            }

            var url = modoFormularioUsuario === 'crear' ? 'request/usuario/crear' : 'request/usuario/editar';

            axiosSipleInterno('POST', url, {}, datos, true, function (respuesta) {
                if (respuesta.error == 0) {
                    var modalUsuario = bootstrap.Modal.getInstance(document.getElementById('modal-usuario'));
                    if (modalUsuario) {
                        modalUsuario.hide();
                    }
                    notificarUsuario(modoFormularioUsuario === 'crear' ? 'Usuario creado correctamente' : 'Usuario actualizado correctamente', 'success');
                    cargarUsuarios();
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        jQuery(document).ready(function () {
            cargarUsuarios();
        });
    </script>
@endsection
