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

        #modal-cliente .modal-title i {
            color: var(--accent);
        }
    </style>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="titulo-pagina">Clientes</h2>
            <p class="subtitulo-pagina">Gestiona la base de clientes de tu negocio.</p>
        </div>
        <button type="button" id="btn-nuevo-cliente" class="btn-primario-accento" data-bs-toggle="modal" data-bs-target="#modal-cliente">
            <i class="bi bi-person-plus"></i> Nuevo cliente
        </button>
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <table id="tabla-clientes" class="table table-striped align-middle w-100 fila-tabla-hover">
                <thead>
                    <tr>
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

    <div class="modal fade" id="modal-cliente" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="bi bi-person-vcard"></i>
                        <span id="modal-cliente-titulo-texto">Nuevo cliente</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-cliente">
                        <input type="hidden" id="id_cliente" name="id_cliente">

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
                            <label class="form-label">Documento de identidad</label>
                            <input type="text" id="documento_identidad" name="documento_identidad" maxlength="50" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Fecha de nacimiento</label>
                            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notas</label>
                            <textarea id="notas" name="notas" rows="3" class="form-control"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-cliente" class="btn-primario-accento">
                        <i class="bi bi-check2"></i> Guardar
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
            axiosSipleInterno('GET', 'request/cliente/listar', {}, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarTablaClientes(respuesta.data.clientes);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        function pintarTablaClientes(clientes) {
            if (tablaClientes) {
                tablaClientes.destroy();
                jQuery('#tabla-clientes tbody').empty();
            }

            tablaClientes = jQuery('#tabla-clientes').DataTable({
                data: clientes,
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.11/i18n/es-ES.json' },
                columns: [
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
        }

        jQuery('#btn-nuevo-cliente').on('click', function () {
            modoFormularioCliente = 'crear';
            jQuery('#modal-cliente-titulo-texto').text('Nuevo cliente');
            limpiarFormularioCliente();
        });

        jQuery('#tabla-clientes').on('click', '.btn-editar-cliente', function () {
            var fila = tablaClientes.row(jQuery(this).closest('tr')).data();

            modoFormularioCliente = 'editar';
            jQuery('#modal-cliente-titulo-texto').text('Editar cliente');
            limpiarFormularioCliente();

            jQuery('#id_cliente').val(fila.id_cliente);
            jQuery('#nombre').val(fila.nombre);
            jQuery('#telefono').val(fila.telefono);
            jQuery('#email').val(fila.email);
            jQuery('#documento_identidad').val(fila.documento_identidad);
            jQuery('#fecha_nacimiento').val(fila.fecha_nacimiento);
            jQuery('#notas').val(fila.notas);

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

            axiosSipleInterno('POST', url, {}, datos, true, function (respuesta) {
                if (respuesta.error == 0) {
                    var modalCliente = bootstrap.Modal.getInstance(document.getElementById('modal-cliente'));
                    if (modalCliente) {
                        modalCliente.hide();
                    }
                    notificarUsuario(modoFormularioCliente === 'crear' ? 'Cliente creado correctamente' : 'Cliente actualizado correctamente', 'success');
                    // Puede completar un paso de los primeros pasos.
                    if (window.refrescarOnboarding) { window.refrescarOnboarding(); }
                    cargarClientes();
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        jQuery(document).ready(function () {
            cargarClientes();
        });
    </script>
@endsection
