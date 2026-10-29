@extends('layout.backoffice')

@section('title', 'Libro de pagos')

@section('estilos')
    <style>
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
            max-width: 620px;
        }

        .barra-filtros-pagos {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: flex-end;
            margin-bottom: 1rem;
        }

        .barra-filtros-pagos .campo-flotante {
            margin-bottom: 0;
            min-width: 180px;
        }

        .tarjeta-total-pagos {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }

        .tarjeta-total-pagos .valor-total {
            color: var(--accent);
            font-weight: 700;
            font-size: 1.4rem;
        }

        .tarjeta-total-pagos .etiqueta-total {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .tabla-pagos {
            width: 100%;
            border-collapse: collapse;
        }

        .tabla-pagos th,
        .tabla-pagos td {
            padding: 0.7rem 0.8rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
            color: var(--text-primary);
            vertical-align: middle;
        }

        .tabla-pagos th {
            color: var(--text-secondary);
            font-weight: 600;
            white-space: nowrap;
        }

        .fila-anulada td {
            color: var(--text-muted);
            text-decoration: line-through;
        }

        .insignia-estado-pago {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .insignia-estado-pago.vigente {
            color: var(--success);
            border: 1px solid var(--success);
            background: color-mix(in srgb, var(--success) 12%, transparent);
        }

        .insignia-estado-pago.anulado {
            color: var(--danger);
            border: 1px solid var(--danger);
            background: color-mix(in srgb, var(--danger) 14%, transparent);
        }

        .acciones-pago .btn-accion-icono {
            min-width: 44px;
            min-height: 44px;
        }

        .estado-vacio-pagos {
            text-align: center;
            color: var(--text-secondary);
            padding: 2rem 1rem;
        }
    </style>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="titulo-pagina">Libro de pagos</h2>
            <p class="subtitulo-pagina">Registro interno de los pagos que recibe la plataforma por fuera. El comprobante que recibe el negocio no reemplaza una factura de venta.</p>
        </div>
    </div>

    <div class="barra-filtros-pagos">
        <div class="campo-flotante sin-icono">
            <input type="month" id="filtro-mes" placeholder=" ">
            <label for="filtro-mes">Mes</label>
        </div>
        <div class="campo-flotante sin-icono">
            <select id="filtro-negocio">
                <option value="">Todos los negocios</option>
            </select>
            <label for="filtro-negocio">Negocio</label>
        </div>
        <button type="button" id="btn-filtrar-pagos" class="btn btn-primario-accento" style="min-height:44px;">
            <i class="bi bi-funnel"></i> Filtrar
        </button>
        <a id="btn-exportar-pagos" class="btn btn-secondary" style="min-height:44px;" href="#" rel="nofollow">
            <i class="bi bi-file-earmark-excel"></i> Exportar
        </a>
    </div>

    <div class="card-elevada tarjeta-total-pagos">
        <div>
            <div class="valor-total" id="total-pagos">$0</div>
            <div class="etiqueta-total">Total del mes (pagos vigentes)</div>
        </div>
        <div>
            <div class="valor-total" id="cantidad-pagos">0</div>
            <div class="etiqueta-total">Cantidad de pagos vigentes</div>
        </div>
    </div>

    <div class="card-elevada card-tabla">
        <div class="card-tabla-body">
            <div id="contenedor-tabla-pagos">
                <div class="estado-vacio-pagos">Cargando...</div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        /* === INICIO JS LIBRO PAGOS ===
           Render por createElement + textContent: el nombre del negocio, la
           referencia y el motivo se tratan como texto, nunca como HTML. */
        var metodosPago = @json($metodos);

        function nodoPago(etiqueta, clase, texto) {
            var el = document.createElement(etiqueta);
            if (clase) { el.className = clase; }
            if (texto !== undefined) { el.textContent = texto; }
            return el;
        }

        function filtrosActuales() {
            return {
                mes: jQuery('#filtro-mes').val() || '',
                id_negocio: jQuery('#filtro-negocio').val() || ''
            };
        }

        function pintarTablaPagos(pagos) {
            var contenedor = document.getElementById('contenedor-tabla-pagos');
            while (contenedor.firstChild) { contenedor.removeChild(contenedor.firstChild); }

            if (!pagos || pagos.length === 0) {
                contenedor.appendChild(nodoPago('div', 'estado-vacio-pagos', 'No hay pagos para este filtro.'));
                return;
            }

            var tabla = nodoPago('table', 'tabla-pagos');
            var thead = nodoPago('thead');
            var filaEnc = nodoPago('tr');
            ['Comprobante', 'Fecha', 'Negocio', 'Concepto', 'Periodo', 'Monto', 'Método', 'Referencia', 'Estado', 'Comprobante', 'Acciones'].forEach(function (t) {
                filaEnc.appendChild(nodoPago('th', null, t));
            });
            thead.appendChild(filaEnc);
            tabla.appendChild(thead);

            var tbody = nodoPago('tbody');
            pagos.forEach(function (pago) {
                var fila = nodoPago('tr', pago.anulado ? 'fila-anulada' : null);
                fila.appendChild(nodoPago('td', null, pago.consecutivo));
                fila.appendChild(nodoPago('td', null, pago.fecha));
                fila.appendChild(nodoPago('td', null, pago.nombre_negocio));
                fila.appendChild(nodoPago('td', null, pago.concepto));
                fila.appendChild(nodoPago('td', null, pago.periodo));
                fila.appendChild(nodoPago('td', null, pago.monto_formato));
                fila.appendChild(nodoPago('td', null, pago.metodo));
                fila.appendChild(nodoPago('td', null, pago.referencia || '—'));

                var tdEstado = nodoPago('td');
                tdEstado.appendChild(nodoPago('span', 'insignia-estado-pago ' + (pago.anulado ? 'anulado' : 'vigente'), pago.anulado ? 'Anulado' : 'Vigente'));
                fila.appendChild(tdEstado);

                fila.appendChild(nodoPago('td', null, pago.comprobante_enviado ? 'Enviado' : 'Sin enviar'));

                var tdAcc = nodoPago('td', 'acciones-pago');
                if (!pago.anulado) {
                    var btnReenviar = nodoPago('button', 'btn-accion-icono');
                    btnReenviar.type = 'button';
                    btnReenviar.title = 'Reenviar comprobante';
                    btnReenviar.setAttribute('data-id', pago.id_pago);
                    btnReenviar.classList.add('btn-reenviar-pago');
                    btnReenviar.appendChild(nodoPago('i', 'bi bi-send'));
                    tdAcc.appendChild(btnReenviar);

                    var btnAnular = nodoPago('button', 'btn-accion-icono btn-accion-eliminar btn-anular-pago');
                    btnAnular.type = 'button';
                    btnAnular.title = 'Anular';
                    btnAnular.setAttribute('data-id', pago.id_pago);
                    btnAnular.setAttribute('data-consecutivo', pago.consecutivo);
                    btnAnular.appendChild(nodoPago('i', 'bi bi-x-circle'));
                    tdAcc.appendChild(btnAnular);
                }
                fila.appendChild(tdAcc);

                tbody.appendChild(fila);
            });
            tabla.appendChild(tbody);
            contenedor.appendChild(tabla);
        }

        function cargarPagos() {
            var f = filtrosActuales();
            actualizarEnlaceExportar(f);

            axiosSipleInterno('GET', 'request/superadmin/pagos/listar', f, {}, true, function (respuesta) {
                if (respuesta.error == 0) {
                    pintarTablaPagos(respuesta.data.pagos);
                    document.getElementById('total-pagos').textContent = '$' + Number(respuesta.data.totales.total || 0).toLocaleString('es-CO');
                    document.getElementById('cantidad-pagos').textContent = respuesta.data.totales.cantidad;
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        }

        function actualizarEnlaceExportar(f) {
            var params = [];
            if (f.mes) { params.push('mes=' + encodeURIComponent(f.mes)); }
            if (f.id_negocio) { params.push('id_negocio=' + encodeURIComponent(f.id_negocio)); }
            document.getElementById('btn-exportar-pagos').setAttribute('href',
                "{{ url('request/superadmin/pagos/exportar') }}" + (params.length ? '?' + params.join('&') : ''));
        }

        function cargarNegociosEnFiltro() {
            axiosSipleInterno('GET', 'request/superadmin/negocios', {}, {}, false, function (respuesta) {
                if (respuesta.error != 0 || !respuesta.data || !respuesta.data.negocios) { return; }
                var select = document.getElementById('filtro-negocio');
                respuesta.data.negocios.forEach(function (negocio) {
                    var opcion = nodoPago('option', null, negocio.nombre_negocio);
                    opcion.value = negocio.id_negocio;
                    select.appendChild(opcion);
                });
            });
        }

        jQuery('#btn-filtrar-pagos').on('click', cargarPagos);

        jQuery('#contenedor-tabla-pagos').on('click', '.btn-reenviar-pago', function () {
            var id = jQuery(this).data('id');
            axiosSipleInterno('POST', 'request/superadmin/pagos/reenviar', {}, { id_pago: id }, true, function (respuesta) {
                notificarUsuario(respuesta.mensaje || (respuesta.error == 0 ? 'Comprobante reenviado.' : 'No se pudo reenviar.'), respuesta.error == 0 ? 'success' : 'error');
                if (respuesta.error == 0) { cargarPagos(); }
            });
        });

        jQuery('#contenedor-tabla-pagos').on('click', '.btn-anular-pago', function () {
            var id = jQuery(this).data('id');
            var consecutivo = jQuery(this).attr('data-consecutivo');

            Swal.fire({
                title: 'Anular ' + escaparTexto(consecutivo),
                input: 'textarea',
                inputLabel: 'Motivo de la anulación (5 a 200 caracteres)',
                inputAttributes: { maxlength: 200 },
                showCancelButton: true,
                confirmButtonText: 'Anular',
                cancelButtonText: 'Cancelar',
                inputValidator: function (valor) {
                    if (!valor || valor.trim().length < 5) { return 'El motivo debe tener al menos 5 caracteres.'; }
                }
            }).then(function (resultado) {
                if (!resultado.isConfirmed) { return; }

                axiosSipleInterno('POST', 'request/superadmin/pagos/anular', {}, { id_pago: id, motivo: resultado.value }, true, function (respuesta) {
                    notificarUsuario(respuesta.mensaje || (respuesta.error == 0 ? 'Pago anulado.' : 'No se pudo anular.'), respuesta.error == 0 ? 'success' : 'error');
                    if (respuesta.error == 0) { cargarPagos(); }
                });
            });
        });

        jQuery(document).ready(function () {
            cargarNegociosEnFiltro();
            cargarPagos();
        });
        /* === FIN JS LIBRO PAGOS === */
    </script>
@endsection
