@extends('layout.backoffice')

@section('title', 'Productos')

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

        #modal-producto .modal-title i,
        #modal-ingresar-stock .modal-title i {
            color: var(--accent);
        }

        .nombre-producto-stock {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.6rem 0.85rem;
            color: var(--text-primary);
            font-weight: 600;
        }

        /* Los dos niveles de alerta comparten forma y se diferencian por color:
           quedarse sin existencias (--danger) pesa más que tocar el mínimo
           (--warning), que todavía deja margen para reponer. */
        .badge-agotado,
        .badge-bajo-minimo {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.78rem;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-weight: 600;
        }

        .badge-agotado {
            background-color: var(--danger-soft);
            color: var(--danger);
        }

        .badge-bajo-minimo {
            background-color: var(--warning-soft);
            color: var(--warning);
        }

        .celda-sku {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.82rem;
            color: var(--text-secondary);
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.1rem 0.45rem;
        }

        .etiqueta-opcional {
            color: var(--text-muted);
            font-weight: 400;
            font-size: 0.82rem;
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
            <h2 class="titulo-pagina">Productos</h2>
            <p class="subtitulo-pagina">Gestiona el inventario de tu negocio y controla el stock disponible.</p>
        </div>
        <button type="button" id="btn-nuevo-producto" class="btn-primario-accento" data-bs-toggle="modal" data-bs-target="#modal-producto">
            <i class="bi bi-plus-lg"></i> Nuevo producto
        </button>
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <table id="tabla-productos" class="table table-striped align-middle w-100 fila-tabla-hover fila-tabla-amplia">
                <thead>
                    <tr>
                        <th></th>
                        <th>Nombre</th>
                        <th>SKU</th>
                        <th>Descripción</th>
                        <th>Cantidad actual</th>
                        <th>Cantidad mínima</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modal-producto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="bi bi-box-seam"></i>
                        <span id="modal-producto-titulo-texto">Nuevo producto</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-producto">
                        <input type="hidden" id="id_producto" name="id_producto">

                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" id="nombre" name="nombre" maxlength="150" class="form-control system_validador_vacio">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">SKU <span class="etiqueta-opcional">(opcional)</span></label>
                            <input type="text" id="sku" name="sku" maxlength="60" class="form-control">
                            <div class="ayuda-campo">Tu código interno para identificar el producto. No puede repetirse dentro de tu negocio.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea id="descripcion" name="descripcion" rows="2" class="form-control"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Cantidad actual</label>
                                <input type="number" id="cantidad_actual" name="cantidad_actual" min="0" step="1" class="form-control system_validador_vacio">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Cantidad mínima</label>
                                <input type="number" id="cantidad_minima" name="cantidad_minima" min="0" step="1" class="form-control system_validador_vacio">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-producto" class="btn-primario-accento">
                        <i class="bi bi-check2"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-ingresar-stock" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-in-down"></i>
                        <span>Ingresar stock</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-ingresar-stock">
                        <input type="hidden" id="id_producto_stock" name="id_producto">

                        <div class="mb-3">
                            <label class="form-label">Producto</label>
                            <div class="nombre-producto-stock" id="nombre-producto-stock">—</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cantidad a agregar</label>
                            <input type="number" id="cantidad_agregar" name="cantidad_agregar" min="1" step="1" class="form-control system_validador_vacio">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-ingreso-stock" class="btn-primario-accento">
                        <i class="bi bi-box-arrow-in-down"></i> Ingresar
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        var modoFormularioProducto = 'crear';
        var tablaProductos;

        function inicializarTooltips() {
            jQuery('[data-bs-toggle="tooltip"]').each(function () {
                var tooltipExistente = bootstrap.Tooltip.getInstance(this);
                if (tooltipExistente) {
                    tooltipExistente.dispose();
                }
                new bootstrap.Tooltip(this);
            });
        }

        /**
         * Misma regla que SvcProducto: "agotado" si no queda ninguna unidad,
         * "bajo" si llegó al mínimo o lo pasó, cadena vacía si está surtido.
         *
         * Se calcula aquí porque el listado completo no trae la etiqueta: solo
         * la traen las consultas de stock bajo, que alimentan la campana. Si la
         * regla del Service cambia, este es el otro sitio que hay que tocar.
         */
        function urgenciaDeProducto(fila) {
            var actual = parseInt(fila.cantidad_actual, 10);
            var minima = parseInt(fila.cantidad_minima, 10);

            if (actual === 0) {
                return 'agotado';
            }

            return actual <= minima ? 'bajo' : '';
        }

        function cargarProductos(alTerminar) {
            axiosSipleInterno('GET', 'request/producto/listar', {}, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarTablaProductos(respuesta.data.productos);
                    if (alTerminar) {
                        alTerminar(respuesta.data.productos);
                    }
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        function pintarTablaProductos(productos) {
            if (tablaProductos) {
                tablaProductos.destroy();
                jQuery('#tabla-productos tbody').empty();
            }

            tablaProductos = jQuery('#tabla-productos').DataTable({
                data: productos,
                language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' },
                columns: [
                    {
                        data: null,
                        orderable: false,
                        render: function (fila) {
                            return generarAvatar(fila.nombre, 'bi-box-seam');
                        }
                    },
                    { data: 'nombre' },
                    {
                        data: 'sku',
                        render: function (data) {
                            return data ? '<span class="celda-sku">' + data + '</span>' : '<span class="text-muted">—</span>';
                        }
                    },
                    {
                        data: 'descripcion',
                        render: function (data) {
                            return data ? data : '<span class="text-muted">—</span>';
                        }
                    },
                    {
                        data: null,
                        render: function (fila) {
                            var urgencia = urgenciaDeProducto(fila);
                            var texto = fila.cantidad_actual;

                            if (urgencia === 'agotado') {
                                return texto + ' <span class="badge-agotado"><i class="bi bi-x-octagon-fill"></i> Agotado</span>';
                            }

                            if (urgencia === 'bajo') {
                                return texto + ' <span class="badge-bajo-minimo"><i class="bi bi-exclamation-triangle-fill"></i> Bajo mínimo</span>';
                            }

                            return texto;
                        }
                    },
                    { data: 'cantidad_minima' },
                    {
                        data: 'estado',
                        render: function (data) {
                            return data == 1
                                ? '<span class="badge-estado-activo"><i class="bi bi-check-circle-fill"></i> Activo</span>'
                                : '<span class="badge-estado-inactivo"><i class="bi bi-dash-circle-fill"></i> Inactivo</span>';
                        }
                    },
                    {
                        data: 'id_producto',
                        orderable: false,
                        render: function (data) {
                            return '<button type="button" class="btn-accion-icono btn-ingresar-stock" data-bs-toggle="tooltip" title="Ingresar stock" data-id_producto="' + data + '"><i class="bi bi-box-arrow-in-down"></i></button>' +
                                   '<button type="button" class="btn-accion-icono btn-editar-producto" data-bs-toggle="tooltip" title="Editar" data-id_producto="' + data + '"><i class="bi bi-pencil-square"></i></button>' +
                                   '<button type="button" class="btn-accion-icono btn-accion-eliminar btn-eliminar-producto" data-bs-toggle="tooltip" title="Eliminar" data-id_producto="' + data + '"><i class="bi bi-trash3"></i></button>';
                        }
                    }
                ]
            });

            tablaProductos.on('draw', function () {
                inicializarTooltips();
            });
        }

        function limpiarFormularioProducto() {
            jQuery('#id_producto').val('');
            jQuery('#nombre').val('');
            jQuery('#sku').val('');
            jQuery('#descripcion').val('');
            jQuery('#cantidad_actual').val('');
            jQuery('#cantidad_minima').val('');
            jQuery('#contenedor-form-producto .input_vacio').removeClass('input_vacio');
            jQuery('#contenedor-form-producto #system_validador').remove();
        }

        function limpiarFormularioIngresoStock() {
            jQuery('#id_producto_stock').val('');
            jQuery('#cantidad_agregar').val('');
            jQuery('#nombre-producto-stock').text('—');
            jQuery('#contenedor-form-ingresar-stock .input_vacio').removeClass('input_vacio');
            jQuery('#contenedor-form-ingresar-stock #system_validador').remove();
        }

        function abrirModalIngresoStock(fila) {
            limpiarFormularioIngresoStock();

            jQuery('#id_producto_stock').val(fila.id_producto);
            jQuery('#nombre-producto-stock').text(fila.nombre);

            var modalStock = new bootstrap.Modal(document.getElementById('modal-ingresar-stock'));
            modalStock.show();
        }

        jQuery('#btn-nuevo-producto').on('click', function () {
            modoFormularioProducto = 'crear';
            jQuery('#modal-producto-titulo-texto').text('Nuevo producto');
            limpiarFormularioProducto();
        });

        jQuery('#tabla-productos').on('click', '.btn-editar-producto', function () {
            var fila = tablaProductos.row(jQuery(this).closest('tr')).data();

            modoFormularioProducto = 'editar';
            jQuery('#modal-producto-titulo-texto').text('Editar producto');
            limpiarFormularioProducto();

            jQuery('#id_producto').val(fila.id_producto);
            jQuery('#nombre').val(fila.nombre);
            jQuery('#sku').val(fila.sku);
            jQuery('#descripcion').val(fila.descripcion);
            jQuery('#cantidad_actual').val(fila.cantidad_actual);
            jQuery('#cantidad_minima').val(fila.cantidad_minima);

            var modalProducto = new bootstrap.Modal(document.getElementById('modal-producto'));
            modalProducto.show();
        });

        jQuery('#tabla-productos').on('click', '.btn-ingresar-stock', function () {
            var fila = tablaProductos.row(jQuery(this).closest('tr')).data();
            abrirModalIngresoStock(fila);
        });

        jQuery('#tabla-productos').on('click', '.btn-eliminar-producto', function () {
            var idProducto = jQuery(this).data('id_producto');

            Swal.fire({
                title: '¿Eliminar producto?',
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
                    axiosSipleInterno('POST', 'request/producto/eliminar', {}, { id_producto: idProducto }, true, function (respuesta) {
                        if (respuesta.error == 0) {
                            notificarUsuario('Producto eliminado correctamente', 'success');
                            cargarProductos();
                        } else {
                            notificarUsuario(respuesta.mensaje, 'error');
                        }
                    });
                }
            });
        });

        jQuery('#btn-guardar-producto').on('click', function () {
            if (!system_validarcampos('contenedor-form-producto', 1)) {
                return;
            }

            var datos = getDataJson('contenedor-form-producto');
            var url = modoFormularioProducto === 'crear' ? 'request/producto/crear' : 'request/producto/editar';

            axiosSipleInterno('POST', url, {}, datos, true, function (respuesta) {
                if (respuesta.error == 0) {
                    var modalProducto = bootstrap.Modal.getInstance(document.getElementById('modal-producto'));
                    if (modalProducto) {
                        modalProducto.hide();
                    }
                    cargarProductos();
                    if (typeof cargarStockBajoCampana === 'function') {
                        cargarStockBajoCampana();
                    }
                    avisarGuardado(modoFormularioProducto === 'crear' ? 'Producto creado correctamente' : 'Producto actualizado correctamente');
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        jQuery('#btn-guardar-ingreso-stock').on('click', function () {
            if (!system_validarcampos('contenedor-form-ingresar-stock', 1)) {
                return;
            }

            var datos = getDataJson('contenedor-form-ingresar-stock');

            axiosSipleInterno('POST', 'request/producto/ingresar-stock', {}, datos, true, function (respuesta) {
                if (respuesta.error == 0) {
                    var modalStock = bootstrap.Modal.getInstance(document.getElementById('modal-ingresar-stock'));
                    if (modalStock) {
                        modalStock.hide();
                    }
                    cargarProductos();
                    if (typeof cargarStockBajoCampana === 'function') {
                        cargarStockBajoCampana();
                    }
                    notificarUsuario('Stock ingresado correctamente', 'success');
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        jQuery(document).ready(function () {
            // "?producto=ID" llega desde la campana de stock bajo: abre de una
            // vez el modal de ingreso de stock para ese producto puntual.
            var parametros = new URLSearchParams(window.location.search);
            var idProductoDestacado = parametros.get('producto');

            cargarProductos(function (productos) {
                if (!idProductoDestacado) {
                    return;
                }

                var producto = productos.filter(function (p) {
                    return String(p.id_producto) === String(idProductoDestacado);
                })[0];

                if (producto) {
                    abrirModalIngresoStock(producto);
                }
            });

            iniciarGuiaSiCorresponde('inventario', function () {
                iniciarTourContextual('inventario', [
                    {
                        attachTo: { element: '#btn-nuevo-producto', on: 'bottom' },
                        title: 'Tu inventario',
                        text: 'Aquí registras los productos que usas o vendes, como shampoo, cremas o esmaltes.'
                    },
                    {
                        attachTo: { element: '#nombre', on: 'bottom' },
                        title: 'Nombre del producto',
                        text: 'Ponle un nombre claro, para que lo reconozcas rápido en la lista.',
                        beforeShowMe: function () {
                            return new Promise(function (resolver) {
                                var elementoModal = document.getElementById('modal-producto');

                                jQuery(elementoModal).one('shown.bs.modal', function () {
                                    resolver();
                                });

                                document.getElementById('btn-nuevo-producto').click();
                            });
                        }
                    },
                    {
                        attachTo: { element: '#cantidad_actual', on: 'bottom' },
                        title: 'Cuánto tienes hoy',
                        text: 'La cantidad con la que arrancas. Después puedes sumar más con el botón de ingresar stock.'
                    },
                    {
                        attachTo: { element: '#cantidad_minima', on: 'bottom' },
                        title: 'Tu punto de alerta',
                        text: 'Cuando la cantidad actual llegue a este número, el producto aparecerá en la campana de avisos para que repongas a tiempo.'
                    }
                ]);
            });
        });
    </script>
@endsection
