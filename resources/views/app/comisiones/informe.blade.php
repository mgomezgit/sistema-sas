@extends('layout.backoffice')

@section('title', 'Comisiones')

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
            max-width: 620px;
        }

        .card-tabla {
            padding: 0;
            overflow: hidden;
        }

        .card-tabla .card-tabla-body {
            padding: 1.25rem 1.5rem;
        }

        /* ---------- Pestañas ---------- */

        .nav-comisiones {
            border-bottom: 1px solid var(--border-color);
            gap: 0.25rem;
            margin-bottom: 1.5rem;
        }

        .nav-comisiones .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            padding: 0.7rem 1.1rem;
            color: var(--text-secondary);
            background-color: transparent;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.45rem;
            transition: var(--transition-base);
        }

        .nav-comisiones .nav-link:hover {
            color: var(--text-primary);
            background-color: var(--bg-card-hover);
        }

        .nav-comisiones .nav-link.active {
            color: var(--accent);
            background-color: transparent;
            border-bottom-color: var(--accent);
        }

        /* ---------- Filtros ---------- */

        .acciones-filtros {
            display: flex;
            align-items: flex-end;
            height: 100%;
            gap: 0.5rem;
        }

        .chips-rango {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .chip-rango {
            background-color: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            border-radius: 999px;
            padding: 0.25rem 0.8rem;
            font-size: 0.78rem;
            font-weight: 600;
            transition: var(--transition-base);
        }

        .chip-rango:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* ---------- Gráfico ---------- */

        .caja-grafico {
            position: relative;
            height: 280px;
        }

        .titulo-bloque {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        /* ---------- Resumen por empleado ---------- */

        .grid-resumen {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1rem;
        }

        .tarjeta-empleado {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 1rem 1.1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .tarjeta-empleado .nombre-empleado {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .tarjeta-empleado .detalle-empleado {
            color: var(--text-secondary);
            font-size: 0.78rem;
        }

        .tarjeta-empleado .total-empleado {
            color: var(--accent);
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .estado-vacio {
            text-align: center;
            color: var(--text-secondary);
            padding: 2.5rem 1rem;
        }

        .estado-vacio i {
            font-size: 2rem;
            display: block;
            margin-bottom: 0.6rem;
            color: var(--text-secondary);
        }

        .badge-porcentaje {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background-color: var(--accent-soft);
            color: var(--accent);
            border-radius: 999px;
            padding: 0.18rem 0.6rem;
            font-size: 0.78rem;
            font-weight: 600;
        }

        #modal-tarifa .modal-title i {
            color: var(--accent);
        }
    </style>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="titulo-pagina">Comisiones</h2>
            <p class="subtitulo-pagina">
                Calcula lo que le corresponde a cada empleado por las citas completadas, liquida periodos y
                administra las tarifas por servicio.
            </p>
        </div>
    </div>

    <ul class="nav nav-tabs nav-comisiones" id="pestanas-comisiones" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link active" id="tab-informe" data-bs-toggle="tab"
                data-bs-target="#panel-informe" role="tab" aria-controls="panel-informe" aria-selected="true">
                <i class="bi bi-calculator"></i> Informe
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" id="tab-tarifas" data-bs-toggle="tab"
                data-bs-target="#panel-tarifas" role="tab" aria-controls="panel-tarifas" aria-selected="false">
                <i class="bi bi-percent"></i> Tarifas específicas
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" id="tab-historial" data-bs-toggle="tab"
                data-bs-target="#panel-historial" role="tab" aria-controls="panel-historial" aria-selected="false">
                <i class="bi bi-clock-history"></i> Historial de pagos
            </button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- ================= PESTAÑA 1: INFORME ================= --}}
        <div class="tab-pane fade show active" id="panel-informe" role="tabpanel" aria-labelledby="tab-informe">
            <div class="card-elevada mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Empleado</label>
                        <select id="filtro-empleado" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($empleados as $empleado)
                                <option value="{{ $empleado['id_empleado'] }}">{{ $empleado['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Desde</label>
                        <input type="date" id="filtro-desde" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" id="filtro-hasta" class="form-control">
                        <div class="chips-rango">
                            <button type="button" class="chip-rango" id="chip-mes-actual">Mes actual</button>
                            <button type="button" class="chip-rango" id="chip-mes-anterior">Mes anterior</button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="acciones-filtros">
                            <button type="button" id="btn-generar-informe" class="btn-primario-accento">
                                <i class="bi bi-calculator"></i> Generar informe
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-elevada mb-4" id="caja-grafico-comisiones" hidden>
                <div class="titulo-bloque">Total a pagar por empleado</div>
                <div class="caja-grafico">
                    <canvas id="grafico-comisiones"></canvas>
                </div>
            </div>

            <div class="mb-4" id="contenedor-resumen" hidden>
                <div class="titulo-bloque">Liquidación del periodo</div>
                <div class="grid-resumen" id="resumen-empleados"></div>
            </div>

            <div class="card-elevada card-tabla">
                <div class="card-tabla-body">
                    <div id="mensaje-sin-comisiones" class="estado-vacio">
                        <i class="bi bi-calculator"></i>
                        Elige un periodo y genera el informe para ver las comisiones pendientes.
                    </div>

                    <table id="tabla-informe" class="table table-striped align-middle w-100 fila-tabla-hover fila-tabla-amplia" hidden>
                        <thead>
                            <tr>
                                <th></th>
                                <th>Empleado</th>
                                <th>Servicio</th>
                                <th>Cantidad de citas</th>
                                <th>Porcentaje aplicado</th>
                                <th>Monto</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ================= PESTAÑA 2: TARIFAS ESPECÍFICAS ================= --}}
        <div class="tab-pane fade" id="panel-tarifas" role="tabpanel" aria-labelledby="tab-tarifas">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <p class="subtitulo-pagina">
                    Una tarifa específica manda sobre el porcentaje general del empleado, pero solo para ese servicio.
                </p>
                <button type="button" id="btn-nueva-tarifa" class="btn-primario-accento">
                    <i class="bi bi-plus-lg"></i> Nueva tarifa
                </button>
            </div>

            <div class="card-elevada card-tabla">
                <div class="card-tabla-body">
                    <table id="tabla-tarifas" class="table table-striped align-middle w-100 fila-tabla-hover fila-tabla-amplia">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Empleado</th>
                                <th>Servicio</th>
                                <th>Porcentaje</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ================= PESTAÑA 3: HISTORIAL DE PAGOS ================= --}}
        <div class="tab-pane fade" id="panel-historial" role="tabpanel" aria-labelledby="tab-historial">
            <p class="subtitulo-pagina mb-3">
                Registro de los periodos ya liquidados. Es solo consulta: un pago marcado no se deshace desde aquí.
            </p>

            <div class="card-elevada card-tabla">
                <div class="card-tabla-body">
                    <table id="tabla-historial" class="table table-striped align-middle w-100 fila-tabla-hover fila-tabla-amplia">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Empleado</th>
                                <th>Rango de fechas</th>
                                <th>Monto total</th>
                                <th>Fecha de pago</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ================= MODAL: TARIFA ESPECÍFICA ================= --}}
    <div class="modal fade" id="modal-tarifa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="bi bi-percent"></i>
                        <span id="modal-tarifa-titulo-texto">Nueva tarifa</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-tarifa">
                        <div class="mb-3">
                            <label class="form-label">Empleado</label>
                            <select id="id_empleado" name="id_empleado" class="form-select system_validador_vacio">
                                <option value="">Selecciona un empleado</option>
                                @foreach ($empleados as $empleado)
                                    <option value="{{ $empleado['id_empleado'] }}">{{ $empleado['nombre'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Servicio</label>
                            <select id="id_recurso" name="id_recurso" class="form-select system_validador_vacio">
                                <option value="">Selecciona un servicio</option>
                                @foreach ($servicios as $servicio)
                                    @if ($servicio['estado'] == 1)
                                        <option value="{{ $servicio['id_recurso'] }}">{{ $servicio['nombre'] }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Porcentaje de comisión</label>
                            <input type="number" id="porcentaje_comision" name="porcentaje_comision" min="0" max="100"
                                step="0.01" class="form-control system_validador_vacio">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-tarifa" class="btn-primario-accento">
                        <i class="bi bi-check2"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        var tablaInforme;
        var tablaTarifas;
        var tablaHistorial;
        var graficoComisiones;

        // Las pestañas 2 y 3 se cargan la primera vez que se abren, no al entrar.
        var tarifasCargadas = false;
        var historialCargado = false;

        // Rango con el que se generó el informe que está en pantalla. Se guarda
        // aparte de los inputs porque el usuario puede cambiarlos después de
        // generar, y lo que se liquida debe ser lo que realmente se está viendo.
        var rangoAplicado = null;

        /* ================= UTILIDADES ================= */

        function formatearFechaISO(fecha) {
            var mes = String(fecha.getMonth() + 1).padStart(2, '0');
            var dia = String(fecha.getDate()).padStart(2, '0');

            return fecha.getFullYear() + '-' + mes + '-' + dia;
        }

        function formatearPrecio(valor) {
            var numero = parseFloat(valor);

            if (isNaN(numero)) {
                return '—';
            }

            return '$' + numero.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }

        function formatearPorcentaje(valor) {
            var numero = parseFloat(valor);

            if (isNaN(numero)) {
                return '—';
            }

            // Sin decimales cuando es un número redondo: "10 %" y no "10,00 %".
            return numero.toLocaleString('es-CO', { maximumFractionDigits: 2 }) + ' %';
        }

        function inicializarTooltips() {
            jQuery('[data-bs-toggle="tooltip"]').each(function () {
                var tooltipExistente = bootstrap.Tooltip.getInstance(this);
                if (tooltipExistente) {
                    tooltipExistente.dispose();
                }
                new bootstrap.Tooltip(this);
            });
        }

        /** Opciones comunes de SweetAlert2 con los colores del tema activo. */
        function opcionesSwal(extra) {
            return jQuery.extend({
                background: colorVariable('--bg-card'),
                color: colorVariable('--text-primary'),
                confirmButtonColor: colorVariable('--accent')
            }, extra || {});
        }

        function aplicarRango(inicio, fin) {
            jQuery('#filtro-desde').val(formatearFechaISO(inicio));
            jQuery('#filtro-hasta').val(formatearFechaISO(fin));
        }

        /* ================= PESTAÑA 1: INFORME ================= */

        function cargarInforme() {
            var fechaInicio = jQuery('#filtro-desde').val();
            var fechaFin = jQuery('#filtro-hasta').val();

            if (!fechaInicio || !fechaFin) {
                notificarUsuario('Elige el rango de fechas antes de generar el informe', 'info');
                return;
            }

            if (fechaFin < fechaInicio) {
                notificarUsuario('La fecha final no puede ser anterior a la inicial', 'info');
                return;
            }

            var filtros = {
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin,
                id_empleado: jQuery('#filtro-empleado').val()
            };

            axiosSipleInterno('GET', 'request/comisiones/informe', filtros, {}, true, function (respuesta) {
                if (respuesta.error != 0) {
                    notificarUsuario(respuesta.mensaje, 'error');
                    return;
                }

                rangoAplicado = { fecha_inicio: fechaInicio, fecha_fin: fechaFin };

                pintarInforme(respuesta.data.comisiones);
            });
        }

        function pintarInforme(comisiones) {
            comisiones = comisiones || [];

            pintarTablaInforme(comisiones);
            pintarResumenEmpleados(comisiones);
            pintarGraficoComisiones(comisiones);
        }

        function pintarTablaInforme(comisiones) {
            // El backend devuelve un bloque por empleado con sus servicios dentro;
            // la tabla necesita una fila por cada par empleado+servicio.
            var filas = [];

            comisiones.forEach(function (empleado) {
                (empleado.servicios || []).forEach(function (servicio) {
                    filas.push({
                        nombre_empleado: empleado.nombre_empleado,
                        nombre_servicio: servicio.nombre_servicio,
                        cantidad_citas: servicio.cantidad_citas,
                        porcentaje_aplicado: servicio.porcentaje_aplicado,
                        monto_comision: servicio.monto_comision
                    });
                });
            });

            var hayDatos = filas.length > 0;

            jQuery('#mensaje-sin-comisiones').prop('hidden', hayDatos);
            jQuery('#tabla-informe').prop('hidden', !hayDatos);

            if (!hayDatos) {
                jQuery('#mensaje-sin-comisiones').html(
                    '<i class="bi bi-check2-circle"></i>' +
                    'No hay comisiones pendientes en el periodo seleccionado.'
                );
            }

            if (tablaInforme) {
                tablaInforme.destroy();
                jQuery('#tabla-informe tbody').empty();
            }

            tablaInforme = jQuery('#tabla-informe').DataTable({
                data: filas,
                language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' },
                order: [],
                columns: [
                    {
                        data: null,
                        orderable: false,
                        render: function (fila) {
                            return generarAvatar(fila.nombre_empleado, 'bi-person-badge');
                        }
                    },
                    { data: 'nombre_empleado' },
                    { data: 'nombre_servicio' },
                    { data: 'cantidad_citas' },
                    {
                        data: 'porcentaje_aplicado',
                        render: function (data) {
                            return '<span class="badge-porcentaje">' + formatearPorcentaje(data) + '</span>';
                        }
                    },
                    {
                        data: 'monto_comision',
                        render: function (data) {
                            return formatearPrecio(data);
                        }
                    }
                ]
            });
        }

        function pintarResumenEmpleados(comisiones) {
            var contenedor = jQuery('#resumen-empleados');
            contenedor.empty();

            jQuery('#contenedor-resumen').prop('hidden', comisiones.length === 0);

            comisiones.forEach(function (empleado) {
                var citas = (empleado.servicios || []).reduce(function (suma, servicio) {
                    return suma + parseInt(servicio.cantidad_citas, 10);
                }, 0);

                var tarjeta = jQuery('<div>').addClass('tarjeta-empleado');

                tarjeta.append(
                    jQuery('<div>').addClass('nombre-empleado').text(empleado.nombre_empleado)
                );
                tarjeta.append(
                    jQuery('<div>').addClass('detalle-empleado')
                        .text(citas === 1 ? '1 cita completada' : citas + ' citas completadas')
                );
                tarjeta.append(
                    jQuery('<div>').addClass('total-empleado').text(formatearPrecio(empleado.total_comision))
                );

                var boton = jQuery('<button>')
                    .attr('type', 'button')
                    .addClass('btn-primario-accento btn-marcar-pagado')
                    .attr('data-id_empleado', empleado.id_empleado)
                    .attr('data-nombre_empleado', empleado.nombre_empleado)
                    .attr('data-total', empleado.total_comision)
                    .html('<i class="bi bi-cash-coin"></i> Marcar como pagado');

                tarjeta.append(boton);
                contenedor.append(tarjeta);
            });
        }

        function pintarGraficoComisiones(comisiones) {
            var lienzo = document.getElementById('grafico-comisiones');

            jQuery('#caja-grafico-comisiones').prop('hidden', comisiones.length === 0);

            if (graficoComisiones) {
                graficoComisiones.destroy();
                graficoComisiones = null;
            }

            if (!lienzo || typeof Chart === 'undefined' || comisiones.length === 0) {
                return;
            }

            // Los colores salen del tema activo: Chart.js no resuelve var(--x).
            var colorBarra = colorVariable('--accent');
            var colorTexto = colorVariable('--text-secondary');
            var colorRejilla = colorVariable('--border-color');

            graficoComisiones = new Chart(lienzo, {
                type: 'bar',
                data: {
                    labels: comisiones.map(function (empleado) {
                        return empleado.nombre_empleado;
                    }),
                    datasets: [{
                        data: comisiones.map(function (empleado) {
                            return parseFloat(empleado.total_comision);
                        }),
                        backgroundColor: colorBarra,
                        borderRadius: 3,
                        borderSkipped: false,
                        maxBarThickness: 26
                    }]
                },
                options: {
                    // Barras horizontales: un empleado por fila.
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            displayColors: false,
                            callbacks: {
                                label: function (contexto) {
                                    return formatearPrecio(contexto.parsed.x);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: colorRejilla },
                            border: { color: colorRejilla },
                            ticks: {
                                color: colorTexto,
                                font: { size: 10 },
                                callback: function (valor) {
                                    return formatearPrecio(valor);
                                }
                            }
                        },
                        y: {
                            grid: { display: false },
                            border: { color: colorRejilla },
                            ticks: { color: colorTexto, font: { size: 11 } }
                        }
                    }
                }
            });
        }

        jQuery('#chip-mes-actual').on('click', function () {
            var hoy = new Date();
            aplicarRango(
                new Date(hoy.getFullYear(), hoy.getMonth(), 1),
                new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0)
            );
        });

        jQuery('#chip-mes-anterior').on('click', function () {
            var hoy = new Date();
            aplicarRango(
                new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1),
                new Date(hoy.getFullYear(), hoy.getMonth(), 0)
            );
        });

        jQuery('#btn-generar-informe').on('click', cargarInforme);

        jQuery('#resumen-empleados').on('click', '.btn-marcar-pagado', function () {
            var boton = jQuery(this);
            var idEmpleado = boton.data('id_empleado');
            var nombreEmpleado = boton.data('nombre_empleado');
            var total = boton.data('total');

            if (!rangoAplicado) {
                notificarUsuario('Genera primero el informe del periodo que quieres liquidar', 'info');
                return;
            }

            // El monto que se muestra aquí es informativo: el backend lo vuelve a
            // calcular desde la base de datos al confirmar.
            Swal.fire(opcionesSwal({
                title: '¿Marcar como pagado?',
                html: 'Se registrará el pago de <strong>' + jQuery('<div>').text(nombreEmpleado).html() + '</strong>' +
                    ' por <strong>' + formatearPrecio(total) + '</strong><br>' +
                    'del ' + rangoAplicado.fecha_inicio + ' al ' + rangoAplicado.fecha_fin + '.<br><br>' +
                    'Esas citas dejarán de aparecer como pendientes.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, marcar como pagado',
                cancelButtonText: 'Cancelar'
            })).then(function (resultado) {
                if (!resultado.isConfirmed) {
                    return;
                }

                var cuerpo = {
                    id_empleado: idEmpleado,
                    fecha_inicio: rangoAplicado.fecha_inicio,
                    fecha_fin: rangoAplicado.fecha_fin
                };

                axiosSipleInterno('POST', 'request/comisiones/marcar-pagado', {}, cuerpo, true, function (respuesta) {
                    if (respuesta.error != 0) {
                        notificarUsuario(respuesta.mensaje, 'error');
                        return;
                    }

                    notificarUsuario('Pago registrado correctamente', 'success');

                    // Se recarga solo el informe, no la página: así se ve de una
                    // vez que esas citas ya no están pendientes.
                    cargarInforme();

                    // El historial queda desactualizado: se refresca si ya se vio.
                    if (historialCargado) {
                        cargarHistorial();
                    }
                });
            });
        });

        /* ================= PESTAÑA 2: TARIFAS ESPECÍFICAS ================= */

        function cargarTarifas() {
            axiosSipleInterno('GET', 'request/comisiones/tarifas', {}, {}, true, function (respuesta) {
                if (respuesta.error != 0) {
                    notificarUsuario(respuesta.mensaje, 'error');
                    return;
                }

                tarifasCargadas = true;
                pintarTablaTarifas(respuesta.data.tarifas);
            });
        }

        function pintarTablaTarifas(tarifas) {
            if (tablaTarifas) {
                tablaTarifas.destroy();
                jQuery('#tabla-tarifas tbody').empty();
            }

            tablaTarifas = jQuery('#tabla-tarifas').DataTable({
                data: tarifas,
                language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' },
                order: [],
                columns: [
                    {
                        data: null,
                        orderable: false,
                        render: function (fila) {
                            return generarAvatar(fila.nombre_empleado, 'bi-person-badge');
                        }
                    },
                    { data: 'nombre_empleado' },
                    { data: 'nombre_servicio' },
                    {
                        data: 'porcentaje_comision',
                        render: function (data) {
                            return '<span class="badge-porcentaje">' + formatearPorcentaje(data) + '</span>';
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function (fila) {
                            return '<button type="button" class="btn-accion-icono btn-editar-tarifa" data-bs-toggle="tooltip" title="Editar"' +
                                   ' data-id_empleado="' + fila.id_empleado + '"' +
                                   ' data-id_recurso="' + fila.id_recurso + '"' +
                                   ' data-porcentaje="' + fila.porcentaje_comision + '"><i class="bi bi-pencil-square"></i></button>' +
                                   '<button type="button" class="btn-accion-icono btn-accion-eliminar btn-eliminar-tarifa" data-bs-toggle="tooltip" title="Eliminar"' +
                                   ' data-id_comision_tarifa="' + fila.id_comision_tarifa + '"><i class="bi bi-trash3"></i></button>';
                        }
                    }
                ]
            });

            tablaTarifas.on('draw', function () {
                inicializarTooltips();
            });

            inicializarTooltips();
        }

        function limpiarFormularioTarifa() {
            jQuery('#id_empleado').val('');
            jQuery('#id_recurso').val('');
            jQuery('#porcentaje_comision').val('');
            jQuery('#contenedor-form-tarifa .input_vacio').removeClass('input_vacio');
            jQuery('#contenedor-form-tarifa #system_validador').remove();
            // Al crear, la combinación empleado+servicio vuelve a ser elegible.
            jQuery('#id_empleado, #id_recurso').prop('disabled', false);
        }

        jQuery('#btn-nueva-tarifa').on('click', function () {
            limpiarFormularioTarifa();
            jQuery('#modal-tarifa-titulo-texto').text('Nueva tarifa');

            var modalTarifa = new bootstrap.Modal(document.getElementById('modal-tarifa'));
            modalTarifa.show();
        });

        jQuery('#tabla-tarifas').on('click', '.btn-editar-tarifa', function () {
            var boton = jQuery(this);

            limpiarFormularioTarifa();
            jQuery('#modal-tarifa-titulo-texto').text('Editar tarifa');

            jQuery('#id_empleado').val(boton.data('id_empleado'));
            jQuery('#id_recurso').val(boton.data('id_recurso'));
            jQuery('#porcentaje_comision').val(boton.data('porcentaje'));

            // Editando solo se cambia el porcentaje: mover la tarifa a otro
            // empleado o servicio crearía una tarifa distinta, no editaría esta.
            jQuery('#id_empleado, #id_recurso').prop('disabled', true);

            var modalTarifa = new bootstrap.Modal(document.getElementById('modal-tarifa'));
            modalTarifa.show();
        });

        jQuery('#btn-guardar-tarifa').on('click', function () {
            // Los select deshabilitados (modo edición) no se validan ni se
            // serializan, así que se leen aparte.
            var idEmpleado = jQuery('#id_empleado').val();
            var idRecurso = jQuery('#id_recurso').val();

            if (!system_validarcampos('contenedor-form-tarifa', 1) || !idEmpleado || !idRecurso) {
                return;
            }

            var cuerpo = {
                id_empleado: idEmpleado,
                id_recurso: idRecurso,
                porcentaje_comision: jQuery('#porcentaje_comision').val()
            };

            axiosSipleInterno('POST', 'request/comisiones/tarifas/guardar', {}, cuerpo, true, function (respuesta) {
                if (respuesta.error != 0) {
                    notificarUsuario(respuesta.mensaje, 'error');
                    return;
                }

                var modalTarifa = bootstrap.Modal.getInstance(document.getElementById('modal-tarifa'));
                if (modalTarifa) {
                    modalTarifa.hide();
                }

                cargarTarifas();
                avisarGuardado('Tarifa guardada correctamente');
            });
        });

        jQuery('#tabla-tarifas').on('click', '.btn-eliminar-tarifa', function () {
            var idTarifa = jQuery(this).data('id_comision_tarifa');

            Swal.fire(opcionesSwal({
                title: '¿Eliminar tarifa?',
                text: 'El empleado volverá a comisionar ese servicio con su porcentaje general',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            })).then(function (resultado) {
                if (!resultado.isConfirmed) {
                    return;
                }

                var cuerpo = { id_comision_tarifa: idTarifa };

                axiosSipleInterno('POST', 'request/comisiones/tarifas/eliminar', {}, cuerpo, true, function (respuesta) {
                    if (respuesta.error != 0) {
                        notificarUsuario(respuesta.mensaje, 'error');
                        return;
                    }

                    notificarUsuario('Tarifa eliminada correctamente', 'success');
                    cargarTarifas();
                });
            });
        });

        /* ================= PESTAÑA 3: HISTORIAL DE PAGOS ================= */

        function cargarHistorial() {
            axiosSipleInterno('GET', 'request/comisiones/historial-pagos', {}, {}, true, function (respuesta) {
                if (respuesta.error != 0) {
                    notificarUsuario(respuesta.mensaje, 'error');
                    return;
                }

                historialCargado = true;
                pintarTablaHistorial(respuesta.data.pagos);
            });
        }

        function pintarTablaHistorial(pagos) {
            if (tablaHistorial) {
                tablaHistorial.destroy();
                jQuery('#tabla-historial tbody').empty();
            }

            tablaHistorial = jQuery('#tabla-historial').DataTable({
                data: pagos,
                language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' },
                // Ya vienen del backend del pago más reciente al más antiguo.
                order: [],
                columns: [
                    {
                        data: null,
                        orderable: false,
                        render: function (fila) {
                            return generarAvatar(fila.nombre_empleado, 'bi-person-badge');
                        }
                    },
                    { data: 'nombre_empleado' },
                    {
                        data: null,
                        render: function (fila) {
                            return fila.fecha_inicio + ' &rarr; ' + fila.fecha_fin;
                        }
                    },
                    {
                        data: 'monto_total',
                        render: function (data) {
                            return formatearPrecio(data);
                        }
                    },
                    { data: 'fecha_pago' }
                ]
            });
        }

        /* ================= ARRANQUE ================= */

        // Las pestañas se cargan bajo demanda: entrar al módulo no dispara las
        // tres consultas de golpe.
        jQuery('#tab-tarifas').on('shown.bs.tab', function () {
            if (!tarifasCargadas) {
                cargarTarifas();
            }
        });

        jQuery('#tab-historial').on('shown.bs.tab', function () {
            if (!historialCargado) {
                cargarHistorial();
            }
        });

        jQuery(document).ready(function () {
            var hoy = new Date();

            aplicarRango(
                new Date(hoy.getFullYear(), hoy.getMonth(), 1),
                new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0)
            );

            cargarInforme();
        });
    </script>
@endsection
