@extends('layout.backoffice')

@section('title', 'Carga Masiva')

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

        .bloque-carga {
            margin-bottom: 1.25rem;
        }

        .encabezado-bloque {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.35rem;
            color: var(--text-primary);
            font-size: 1.05rem;
            font-weight: 600;
        }

        .encabezado-bloque i {
            color: var(--accent);
        }

        .ayuda-bloque {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .acciones-carga {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 0.75rem;
        }

        .acciones-carga .campo-archivo {
            flex: 1;
            min-width: 240px;
        }

        .btn-plantilla {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: var(--radius-sm);
            padding: 0.55rem 0.95rem;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: var(--transition-base);
        }

        .btn-plantilla:hover {
            background-color: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
        }

        /* ---------- Resultados de la importación ---------- */
        .resultados-carga {
            display: none;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .resultados-carga.visible {
            display: block;
        }

        .resumen-carga {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-bottom: 0.9rem;
        }

        .pastilla-resumen {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .pastilla-resumen.exito {
            background-color: var(--success-soft);
            color: var(--success);
        }

        .pastilla-resumen.error {
            background-color: var(--danger-soft);
            color: var(--danger);
        }

        .tabla-resultados {
            width: 100%;
            font-size: 0.85rem;
        }

        .tabla-resultados th {
            color: var(--text-secondary);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding-bottom: 0.5rem;
            text-align: left;
        }

        .tabla-resultados td {
            padding: 0.45rem 0.4rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .tabla-resultados td.celda-estado {
            width: 130px;
        }

        .tabla-resultados .fila-exito i { color: var(--success); }
        .tabla-resultados .fila-error i { color: var(--danger); }
        .tabla-resultados .fila-error td { color: var(--text-secondary); }
    </style>
@endsection

@section('content')
    <div class="mb-4">
        <h2 class="titulo-pagina">Carga Masiva</h2>
        <p class="subtitulo-pagina">
            Crea muchos registros de una sola vez desde un archivo de Excel. Descarga la plantilla,
            llénala con tus datos y súbela: si alguna fila tiene un problema, se te indica cuál y las
            demás se cargan igual.
        </p>
    </div>

    @foreach ([
        ['tipo' => 'empleados', 'titulo' => 'Empleados', 'icono' => 'bi-person-badge', 'ayuda' => 'Columnas: Nombre, Telefono, Email, Cargo, Porcentaje Comision. Nombre y teléfono son obligatorios.'],
        ['tipo' => 'recursos', 'titulo' => 'Servicios', 'icono' => 'bi-collection', 'ayuda' => 'Columnas: Categoria, Nombre, Descripcion, Duracion Minutos, Precio, Capacidad. Nombre, duración y precio son obligatorios.'],
        ['tipo' => 'usuarios', 'titulo' => 'Usuarios', 'icono' => 'bi-people', 'ayuda' => 'Columnas: Usuario, Nombre, Email, Clave Temporal, Rol. El rol debe decir "admin" o "empleado".'],
        ['tipo' => 'productos', 'titulo' => 'Productos', 'icono' => 'bi-box-seam', 'ayuda' => 'Columnas: SKU, Nombre, Descripcion, Cantidad Actual, Cantidad Minima. Nombre y cantidades son obligatorios. Si el SKU ya existe se actualiza ese producto (sin tocar su cantidad actual); si no, se crea uno nuevo.'],
    ] as $bloque)
        <div class="card-elevada bloque-carga" data-tipo="{{ $bloque['tipo'] }}">
            <div class="encabezado-bloque">
                <i class="bi {{ $bloque['icono'] }}"></i>
                {{ $bloque['titulo'] }}
            </div>
            <div class="ayuda-bloque">{{ $bloque['ayuda'] }}</div>

            <div class="acciones-carga">
                <button type="button" class="btn-plantilla btn-descargar-plantilla">
                    <i class="bi bi-download"></i> Descargar plantilla
                </button>

                <div class="campo-archivo">
                    <label class="form-label">Archivo lleno (.xlsx)</label>
                    <input type="file" class="form-control input-archivo" accept=".xlsx">
                </div>

                <button type="button" class="btn-primario-accento btn-importar">
                    <i class="bi bi-cloud-upload"></i> Importar
                </button>
            </div>

            <div class="resultados-carga">
                <div class="resumen-carga"></div>

                <table class="tabla-resultados">
                    <thead>
                        <tr>
                            <th>Fila</th>
                            <th>Resultado</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    @endforeach
@endsection

@section('scripts')
    <script>
        function escaparTexto(texto) {
            return jQuery('<div>').text(texto === null || texto === undefined ? '' : texto).html();
        }

        // La descarga es un archivo binario: se navega a la URL en vez de pedirla
        // por axios.
        jQuery('.btn-descargar-plantilla').on('click', function () {
            var tipo = jQuery(this).closest('.bloque-carga').data('tipo');

            window.location.href = UrlGlobal + 'request/carga-masiva/plantilla/' + tipo;
        });

        jQuery('.btn-importar').on('click', function () {
            var bloque = jQuery(this).closest('.bloque-carga');
            var tipo = bloque.data('tipo');
            var entrada = bloque.find('.input-archivo')[0];

            if (!entrada.files || entrada.files.length === 0) {
                notificarUsuario('Elige primero el archivo de Excel que quieres importar', 'info');
                return;
            }

            var formulario = new FormData();
            formulario.append('archivo', entrada.files[0]);

            // Va por axios directo y no por axiosSipleInterno porque necesita
            // enviarse como multipart/form-data.
            Mostrarloader();

            axios.post(UrlGlobal + 'request/carga-masiva/importar/' + tipo, formulario, {
                headers: { 'Content-Type': 'multipart/form-data' }
            }).then(function (respuesta) {
                Ocultarloader();

                var datos = respuesta.data;

                if (datos.error != 0) {
                    notificarUsuario(datos.mensaje, 'error');
                    return;
                }

                pintarResultados(bloque, datos.data.resultados);
            }).catch(function () {
                Ocultarloader();
                notificarUsuario('No pudimos subir el archivo. Revisa tu conexión e inténtalo de nuevo.', 'error');
            });
        });

        function pintarResultados(bloque, resultados) {
            var cuerpo = bloque.find('.tabla-resultados tbody');
            var resumen = bloque.find('.resumen-carga');

            cuerpo.empty();
            resumen.empty();

            if (!resultados || resultados.length === 0) {
                notificarUsuario('El archivo no tenía filas con datos para procesar', 'info');
                bloque.find('.resultados-carga').removeClass('visible');
                return;
            }

            var creados = 0;
            var conErrores = 0;

            resultados.forEach(function (resultado) {
                if (resultado.exito) {
                    creados++;
                } else {
                    conErrores++;
                }

                cuerpo.append(
                    '<tr class="' + (resultado.exito ? 'fila-exito' : 'fila-error') + '">' +
                    '<td>' + resultado.fila + '</td>' +
                    '<td class="celda-estado">' +
                    '<i class="bi ' + (resultado.exito ? 'bi-check-circle-fill' : 'bi-x-circle-fill') + '"></i> ' +
                    (resultado.exito ? 'Creado' : 'Error') +
                    '</td>' +
                    '<td>' + escaparTexto(resultado.mensaje) + '</td>' +
                    '</tr>'
                );
            });

            resumen.append(
                '<span class="pastilla-resumen exito">' +
                '<i class="bi bi-check-circle-fill"></i> ' + creados +
                (creados === 1 ? ' creado correctamente' : ' creados correctamente') +
                '</span>'
            );

            if (conErrores > 0) {
                resumen.append(
                    '<span class="pastilla-resumen error">' +
                    '<i class="bi bi-exclamation-circle-fill"></i> ' + conErrores +
                    (conErrores === 1 ? ' con error' : ' con errores') +
                    '</span>'
                );
            }

            bloque.find('.resultados-carga').addClass('visible');

            // El drawer de primeros pasos puede haberse completado con esta carga.
            if (window.avisarGuardado && creados > 0) {
                avisarGuardado(creados + (creados === 1 ? ' registro creado' : ' registros creados'));
            }
        }
    </script>
@endsection
