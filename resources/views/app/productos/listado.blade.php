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

        #modal-ingresar-stock .modal-title i {
            color: var(--accent);
        }

        /* El SKU y su botón de sugerencia comparten renglón; en pantallas
           estrechas el botón baja debajo del campo. */
        .fila-sku {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
        }

        .fila-sku .campo-flotante {
            flex: 1;
            min-width: 0;
        }

        .btn-generar-sku {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background-color: var(--accent-soft);
            border: 1px solid transparent;
            color: var(--accent);
            border-radius: var(--radius-sm);
            padding: 0.85rem 0.9rem;
            font-size: 0.82rem;
            font-weight: 600;
            white-space: nowrap;
            transition: var(--transition-base);
        }

        .btn-generar-sku:hover:not(:disabled) {
            background-color: var(--accent);
            color: var(--text-sobre-accent);
        }

        .btn-generar-sku:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        @media (max-width: 575.98px) {
            .fila-sku {
                flex-direction: column;
                align-items: stretch;
            }
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
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <label class="interruptor-moderno" id="filtro-mostrar-inactivos">
                <input type="checkbox" id="chk-mostrar-inactivos">
                <span class="pista-interruptor"></span>
                <span class="texto-interruptor">Mostrar inactivos</span>
            </label>
            <button type="button" id="btn-nuevo-producto" class="btn-primario-accento" data-bs-toggle="modal" data-bs-target="#modal-producto">
                <i class="bi bi-plus-lg"></i> Nuevo producto
            </button>
        </div>
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

    <div class="modal fade modal-moderno" id="modal-producto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header-moderno">
                    <span class="insignia-encabezado"><i class="bi bi-box-seam"></i></span>
                    <div>
                        <h5 class="titulo-modal-moderno" id="modal-producto-titulo-texto">Nuevo producto</h5>
                        <p class="subtitulo-modal-moderno" id="modal-producto-subtitulo">Registra un artículo de tu inventario</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-producto">
                        <input type="hidden" id="id_producto" name="id_producto">

                        <div class="tarjeta-seccion-form">
                            <div class="etiqueta-seccion-form">Información general</div>

                            <div class="campo-flotante">
                                <i class="bi bi-tag"></i>
                                <input type="text" id="nombre" name="nombre" maxlength="150" placeholder=" " class="system_validador_vacio">
                                <label for="nombre">Nombre del producto</label>
                            </div>

                            <div class="fila-sku mt-3">
                                <div class="campo-flotante" id="campo-sku">
                                    <i class="bi bi-upc-scan"></i>
                                    <input type="text" id="sku" name="sku" maxlength="60" placeholder=" ">
                                    <label for="sku">SKU</label>
                                    <span class="mensaje-error-campo" id="error-sku"></span>
                                </div>
                                <button type="button" id="btn-generar-sku" class="btn-generar-sku">
                                    <i class="bi bi-stars"></i> Generar
                                </button>
                            </div>

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-text-left"></i>
                                <textarea id="descripcion" name="descripcion" rows="2" placeholder=" "></textarea>
                                <label for="descripcion">Descripción</label>
                            </div>
                        </div>

                        <div class="tarjeta-seccion-form">
                            <div class="etiqueta-seccion-form">Control de stock</div>

                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="rotulo-stepper">Cantidad actual</div>
                                    <div class="stepper-campo">
                                        <button type="button" class="btn-stepper" data-paso="-1" aria-label="Restar una unidad">
                                            <i class="bi bi-dash-lg"></i>
                                        </button>
                                        <input type="number" id="cantidad_actual" name="cantidad_actual" min="0" step="1" class="valor-stepper system_validador_vacio">
                                        <button type="button" class="btn-stepper" data-paso="1" aria-label="Sumar una unidad">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="rotulo-stepper">Cantidad mínima</div>
                                    <div class="stepper-campo">
                                        <button type="button" class="btn-stepper" data-paso="-1" aria-label="Bajar el mínimo">
                                            <i class="bi bi-dash-lg"></i>
                                        </button>
                                        <input type="number" id="cantidad_minima" name="cantidad_minima" min="0" step="1" class="valor-stepper system_validador_vacio">
                                        <button type="button" class="btn-stepper" data-paso="1" aria-label="Subir el mínimo">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="ayuda-campo mt-2">
                                Cuando la cantidad actual llegue al mínimo, el producto aparecerá en la campana de avisos.
                            </div>
                        </div>

                        {{-- Solo tiene sentido al editar: un producto recién creado siempre
                             nace activo, así que aquí no se le pregunta nada al usuario. --}}
                        <div class="tarjeta-seccion-form" id="seccion-estado-producto" hidden>
                            <div class="etiqueta-seccion-form">Estado</div>

                            <label class="interruptor-moderno">
                                <input type="checkbox" id="estado_producto">
                                <span class="pista-interruptor"></span>
                                <span class="texto-interruptor" id="texto-estado-producto">Producto activo</span>
                            </label>

                            <div class="ayuda-campo mt-2">
                                Un producto inactivo deja de aparecer en el listado y en la campana de stock bajo, pero puede reactivarse en cualquier momento desde aquí.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-producto" class="btn-guardar-moderno">
                        <i class="bi bi-check2 icono-guardar"></i>
                        <span id="texto-btn-guardar">Guardar</span>
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
            var incluirInactivos = jQuery('#chk-mostrar-inactivos').is(':checked') ? 1 : 0;

            axiosSipleInterno('GET', 'request/producto/listar', { incluir_inactivos: incluirInactivos }, {}, true, function (respuesta) {
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

        // "Mostrar inactivos": vuelve a pedir el listado con el filtro nuevo.
        jQuery('#chk-mostrar-inactivos').on('change', function () {
            cargarProductos();
        });

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

        /** Quita el resaltado de error del campo SKU y su mensaje. */
        function limpiarErrorSku() {
            jQuery('#campo-sku').removeClass('con-error');
            jQuery('#error-sku').text('');
        }

        /**
         * Pinta en el propio campo lo que respondió el servidor.
         *
         * El sobre de respuesta trae el mensaje como texto (errores de negocio,
         * "Ya existe un producto con ese SKU") o como arreglo (las reglas de
         * validación de Laravel). Se reparte: lo que habla del SKU va pegado a
         * su campo, y el resto sale por el aviso de siempre. Nunca se inventa un
         * texto: se muestra el que llegó.
         */
        function mostrarErrorDelServidor(respuesta) {
            var mensajes = Array.isArray(respuesta.mensaje) ? respuesta.mensaje : [respuesta.mensaje];

            var delSku = mensajes.filter(function (m) { return /sku/i.test(m); });
            var otros = mensajes.filter(function (m) { return !/sku/i.test(m); });

            if (delSku.length > 0) {
                jQuery('#campo-sku').addClass('con-error');
                jQuery('#error-sku').text(delSku.join(' '));
            }

            if (otros.length > 0) {
                notificarUsuario(otros.length === 1 ? otros[0] : otros, 'error');
            }
        }

        /**
         * Los tres estados del botón de guardar que define el kit: normal,
         * "ocupado" (con su spinner) y "exito" (con su check).
         */
        function estadoBotonGuardar(estado) {
            var boton = jQuery('#btn-guardar-producto');

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

        function limpiarFormularioProducto() {
            jQuery('#id_producto').val('');
            jQuery('#nombre').val('');
            jQuery('#sku').val('');
            jQuery('#descripcion').val('');
            // Los contadores arrancan en 0 y no vacíos: un stepper sin número se
            // ve roto, y 0 es el valor honesto para un producto que aún no tiene
            // existencias. El servidor acepta 0 en ambos campos.
            jQuery('#cantidad_actual').val(0);
            jQuery('#cantidad_minima').val(0);
            jQuery('#contenedor-form-producto .input_vacio').removeClass('input_vacio');
            jQuery('#contenedor-form-producto #system_validador').remove();
            limpiarErrorSku();
            estadoBotonGuardar('normal');
            establecerEstadoProducto(true);
            jQuery('#seccion-estado-producto').prop('hidden', true);
        }

        /** Refleja el estado en el interruptor y en su propio texto. */
        function establecerEstadoProducto(activo) {
            jQuery('#estado_producto').prop('checked', activo);
            jQuery('#texto-estado-producto').text(activo ? 'Producto activo' : 'Producto inactivo');
        }

        jQuery('#estado_producto').on('change', function () {
            establecerEstadoProducto(jQuery(this).is(':checked'));
        });

        /**
         * Pide al servidor un SKU libre para el nombre indicado y lo escribe en
         * el campo.
         *
         * @param {string}   nombre       Nombre desde el que se arma el código.
         * @param {boolean}  mostrarLoader Si la espera se ve o va en silencio.
         * @param {Function} [alTerminar] Se llama al acabar, haya salido o no.
         */
        function pedirSkuSugerido(nombre, mostrarLoader, alTerminar) {
            axiosSipleInterno('GET', 'request/producto/generar-sku', { nombre: nombre }, {}, mostrarLoader, function (respuesta) {
                if (respuesta && respuesta.error == 0) {
                    jQuery('#sku').val(respuesta.data.sku);
                    limpiarErrorSku();
                } else if (respuesta) {
                    notificarUsuario(respuesta.mensaje, 'error');
                }

                if (alTerminar) {
                    alTerminar();
                }
            });
        }

        // Al corregir el código, el error deja de tener sentido: se retira solo.
        jQuery('#sku').on('input', function () {
            limpiarErrorSku();
        });

        jQuery('#btn-generar-sku').on('click', function () {
            var nombre = jQuery.trim(jQuery('#nombre').val());

            // Sin nombre el código saldría genérico ("PROD-001") y no ayudaría a
            // reconocer el producto, así que se pide primero el nombre.
            if (nombre === '') {
                jQuery('#campo-sku').addClass('con-error');
                jQuery('#error-sku').text('Escribe primero el nombre del producto para sugerir un código.');
                jQuery('#nombre').trigger('focus');

                return;
            }

            var boton = jQuery(this);
            boton.prop('disabled', true);

            pedirSkuSugerido(nombre, false, function () {
                boton.prop('disabled', false);
            });
        });

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
            jQuery('#modal-producto-subtitulo').text('Registra un artículo de tu inventario');
            limpiarFormularioProducto();
        });

        jQuery('#tabla-productos').on('click', '.btn-editar-producto', function () {
            var fila = tablaProductos.row(jQuery(this).closest('tr')).data();

            modoFormularioProducto = 'editar';
            jQuery('#modal-producto-titulo-texto').text('Editar producto');
            jQuery('#modal-producto-subtitulo').text('Actualiza los datos de "' + fila.nombre + '"');
            limpiarFormularioProducto();

            jQuery('#id_producto').val(fila.id_producto);
            jQuery('#nombre').val(fila.nombre);
            jQuery('#sku').val(fila.sku);
            jQuery('#descripcion').val(fila.descripcion);
            jQuery('#cantidad_actual').val(fila.cantidad_actual);
            jQuery('#cantidad_minima').val(fila.cantidad_minima);

            // El interruptor solo aparece al editar: un producto nuevo siempre
            // nace activo, así que no hay nada que preguntar en ese momento.
            jQuery('#seccion-estado-producto').prop('hidden', false);
            establecerEstadoProducto(fila.estado == 1);

            var modalProducto = new bootstrap.Modal(document.getElementById('modal-producto'));

            // Red de seguridad: el SKU es obligatorio, pero un producto anterior
            // al cambio podría no tenerlo. En vez de dejar que el usuario se
            // choque con un "campo obligatorio" que no sabe de dónde salió, se
            // le pide uno al servidor y se abre el formulario ya completo.
            if (!fila.sku) {
                pedirSkuSugerido(fila.nombre, true, function () {
                    modalProducto.show();
                });

                return;
            }

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

        /** Cuánto se deja ver el check de "Guardado" antes de cerrar, en ms. */
        var ESPERA_CONFIRMACION_GUARDADO = 700;

        jQuery('#btn-guardar-producto').on('click', function () {
            limpiarErrorSku();

            if (!system_validarcampos('contenedor-form-producto', 1)) {
                return;
            }

            var datos = getDataJson('contenedor-form-producto');
            var url = modoFormularioProducto === 'crear' ? 'request/producto/crear' : 'request/producto/editar';

            // El checkbox del interruptor no lleva "name" a propósito, para que
            // serializeObject no lo confunda con un checkbox nativo (que solo se
            // envía si está marcado). Se agrega aquí siempre como 0/1 explícito;
            // crear() lo ignora porque un producto nuevo siempre nace activo.
            datos.estado = jQuery('#estado_producto').is(':checked') ? 1 : 0;

            // El propio botón hace de indicador, así que no se levanta el loader
            // que tapa la pantalla: el formulario sigue a la vista y, si el
            // servidor rechaza algo, el error aparece junto a su campo.
            estadoBotonGuardar('ocupado');

            axiosSipleInterno('POST', url, {}, datos, false, function (respuesta) {
                if (!respuesta || respuesta.error != 0) {
                    estadoBotonGuardar('normal');

                    if (respuesta) {
                        mostrarErrorDelServidor(respuesta);
                    }

                    return;
                }

                estadoBotonGuardar('exito');

                // Un respiro para que se vea el check antes de que el panel se
                // cierre; sin esto el estado de éxito pasaría inadvertido.
                setTimeout(function () {
                    var modalProducto = bootstrap.Modal.getInstance(document.getElementById('modal-producto'));

                    if (modalProducto) {
                        modalProducto.hide();
                    }

                    estadoBotonGuardar('normal');
                    cargarProductos();

                    if (typeof cargarStockBajoCampana === 'function') {
                        cargarStockBajoCampana();
                    }

                    avisarGuardado(modoFormularioProducto === 'crear' ? 'Producto creado correctamente' : 'Producto actualizado correctamente');
                }, ESPERA_CONFIRMACION_GUARDADO);
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
