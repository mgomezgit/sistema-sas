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

        #modal-empleado .modal-title i,
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
    </style>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="titulo-pagina">Empleados</h2>
            <p class="subtitulo-pagina">Gestiona el personal de tu negocio y sus accesos al sistema.</p>
        </div>
        <button type="button" id="btn-nuevo-empleado" class="btn-primario-accento" data-bs-toggle="modal" data-bs-target="#modal-empleado">
            <i class="bi bi-person-plus"></i> Nuevo empleado
        </button>
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <table id="tabla-empleados" class="table table-striped align-middle w-100 fila-tabla-hover">
                <thead>
                    <tr>
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

    <div class="modal fade" id="modal-empleado" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="bi bi-person-badge"></i>
                        <span id="modal-empleado-titulo-texto">Nuevo empleado</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-empleado">
                        <input type="hidden" id="id_empleado" name="id_empleado">

                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" id="nombre" name="nombre" maxlength="150" class="form-control system_validador_vacio">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" id="telefono" name="telefono" maxlength="30" class="form-control system_validador_vacio">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" id="email" name="email" maxlength="150" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cargo</label>
                            <input type="text" id="cargo" name="cargo" maxlength="100" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Porcentaje de comisión</label>
                            <input type="number" id="porcentaje_comision" name="porcentaje_comision" step="0.01" min="0" max="100" class="form-control">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-empleado" class="btn-primario-accento">
                        <i class="bi bi-check2"></i> Guardar
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
            axiosSipleInterno('GET', 'request/empleado/listar', {}, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarTablaEmpleados(respuesta.data.empleados);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        function pintarTablaEmpleados(empleados) {
            if (tablaEmpleados) {
                tablaEmpleados.destroy();
                jQuery('#tabla-empleados tbody').empty();
            }

            tablaEmpleados = jQuery('#tabla-empleados').DataTable({
                data: empleados,
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.11/i18n/es-ES.json' },
                columns: [
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

        function limpiarFormularioEmpleado() {
            jQuery('#id_empleado').val('');
            jQuery('#nombre').val('');
            jQuery('#telefono').val('');
            jQuery('#email').val('');
            jQuery('#cargo').val('');
            jQuery('#porcentaje_comision').val('');
            jQuery('#contenedor-form-empleado .input_vacio').removeClass('input_vacio');
            jQuery('#contenedor-form-empleado #system_validador').remove();
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
            limpiarFormularioEmpleado();
        });

        jQuery('#tabla-empleados').on('click', '.btn-editar-empleado', function () {
            var fila = tablaEmpleados.row(jQuery(this).closest('tr')).data();

            modoFormularioEmpleado = 'editar';
            jQuery('#modal-empleado-titulo-texto').text('Editar empleado');
            limpiarFormularioEmpleado();

            jQuery('#id_empleado').val(fila.id_empleado);
            jQuery('#nombre').val(fila.nombre);
            jQuery('#telefono').val(fila.telefono);
            jQuery('#email').val(fila.email);
            jQuery('#cargo').val(fila.cargo);
            jQuery('#porcentaje_comision').val(fila.porcentaje_comision);

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
            var idEmpleado = jQuery(this).data('id_empleado');

            Swal.fire({
                title: '¿Eliminar empleado?',
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

            var url = modoFormularioEmpleado === 'crear' ? 'request/empleado/crear' : 'request/empleado/editar';

            axiosSipleInterno('POST', url, {}, datos, true, function (respuesta) {
                if (respuesta.error == 0) {
                    var modalEmpleado = bootstrap.Modal.getInstance(document.getElementById('modal-empleado'));
                    if (modalEmpleado) {
                        modalEmpleado.hide();
                    }
                    cargarEmpleados();
                    avisarGuardado(modoFormularioEmpleado === 'crear' ? 'Empleado creado correctamente' : 'Empleado actualizado correctamente');
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

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
