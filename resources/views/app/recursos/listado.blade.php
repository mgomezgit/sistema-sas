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

        #modal-recurso .modal-title i {
            color: var(--accent);
        }
    </style>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="titulo-pagina">Recursos Reservables</h2>
            <p class="subtitulo-pagina">Administra los servicios que tu negocio ofrece para reservar.</p>
        </div>
        <button type="button" id="btn-nuevo-recurso" class="btn-primario-accento" data-bs-toggle="modal" data-bs-target="#modal-recurso">
            <i class="bi bi-plus-lg"></i> Nuevo recurso
        </button>
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <table id="tabla-recursos" class="table table-striped align-middle w-100 fila-tabla-hover">
                <thead>
                    <tr>
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

    <div class="modal fade" id="modal-recurso" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="bi bi-collection"></i>
                        <span id="modal-recurso-titulo-texto">Nuevo recurso</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-recurso">
                        <input type="hidden" id="id_recurso" name="id_recurso">

                        <div class="mb-3">
                            <label class="form-label">Categoría</label>
                            <input type="text" id="categoria" name="categoria" maxlength="100" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" id="nombre" name="nombre" maxlength="150" class="form-control system_validador_vacio">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea id="descripcion" name="descripcion" rows="3" class="form-control"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Duración (minutos)</label>
                            <input type="number" id="duracion_minutos" name="duracion_minutos" min="1" class="form-control system_validador_vacio system_validador_numerico">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Precio</label>
                            <input type="number" id="precio" name="precio" step="0.01" min="0" class="form-control system_validador_vacio system_validador_numerico">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Capacidad</label>
                            <input type="number" id="capacidad" name="capacidad" min="1" class="form-control">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-recurso" class="btn-primario-accento">
                        <i class="bi bi-check2"></i> Guardar
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
            axiosSipleInterno('GET', 'request/recurso/listar', {}, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarTablaRecursos(respuesta.data.recursos);
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        function pintarTablaRecursos(recursos) {
            if (tablaRecursos) {
                tablaRecursos.destroy();
                jQuery('#tabla-recursos tbody').empty();
            }

            tablaRecursos = jQuery('#tabla-recursos').DataTable({
                data: recursos,
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.11/i18n/es-ES.json' },
                columns: [
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

        function limpiarFormularioRecurso() {
            jQuery('#id_recurso').val('');
            jQuery('#categoria').val('');
            jQuery('#nombre').val('');
            jQuery('#descripcion').val('');
            jQuery('#duracion_minutos').val('');
            jQuery('#precio').val('');
            jQuery('#capacidad').val('');
            jQuery('#contenedor-form-recurso .input_vacio').removeClass('input_vacio');
            jQuery('#contenedor-form-recurso #system_validador').remove();
        }

        jQuery('#btn-nuevo-recurso').on('click', function () {
            modoFormularioRecurso = 'crear';
            jQuery('#modal-recurso-titulo-texto').text('Nuevo recurso');
            limpiarFormularioRecurso();
        });

        jQuery('#tabla-recursos').on('click', '.btn-editar-recurso', function () {
            var fila = tablaRecursos.row(jQuery(this).closest('tr')).data();

            modoFormularioRecurso = 'editar';
            jQuery('#modal-recurso-titulo-texto').text('Editar recurso');
            limpiarFormularioRecurso();

            jQuery('#id_recurso').val(fila.id_recurso);
            jQuery('#categoria').val(fila.categoria);
            jQuery('#nombre').val(fila.nombre);
            jQuery('#descripcion').val(fila.descripcion);
            jQuery('#duracion_minutos').val(fila.duracion_minutos);
            jQuery('#precio').val(fila.precio);
            jQuery('#capacidad').val(fila.capacidad);

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

        jQuery('#btn-guardar-recurso').on('click', function () {
            if (!system_validarcampos('contenedor-form-recurso', 1)) {
                return;
            }

            var datos = getDataJson('contenedor-form-recurso');

            var url = modoFormularioRecurso === 'crear' ? 'request/recurso/crear' : 'request/recurso/editar';

            axiosSipleInterno('POST', url, {}, datos, true, function (respuesta) {
                if (respuesta.error == 0) {
                    var modalRecurso = bootstrap.Modal.getInstance(document.getElementById('modal-recurso'));
                    if (modalRecurso) {
                        modalRecurso.hide();
                    }
                    cargarRecursos();
                    avisarGuardado(modoFormularioRecurso === 'crear' ? 'Recurso creado correctamente' : 'Recurso actualizado correctamente');
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        jQuery(document).ready(function () {
            cargarRecursos();
        });
    </script>
@endsection
