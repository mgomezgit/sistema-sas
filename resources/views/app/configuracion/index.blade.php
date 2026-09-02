@extends('layout.backoffice')

@section('title', 'Configuración')

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
            max-width: 560px;
        }

        .titulo-bloque {
            color: var(--text-primary);
            font-size: 1.05rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        .ayuda-bloque {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
        }

        .dias-atencion {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-bottom: 1.25rem;
        }

        .dia-chip {
            position: relative;
        }

        .dia-chip input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .dia-chip label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 92px;
            padding: 0.55rem 0.9rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background-color: var(--bg-input);
            color: var(--text-secondary);
            font-size: 0.88rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition-base);
            margin-bottom: 0;
        }

        .dia-chip label:hover {
            border-color: var(--border-color-strong);
        }

        .dia-chip input[type="checkbox"]:checked + label {
            background-color: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
            font-weight: 600;
        }

        .texto-ayuda-campo {
            color: var(--text-muted);
            font-size: 0.8rem;
        }
    </style>
@endsection

@section('content')
    <div class="mb-4">
        <h2 class="titulo-pagina">Configuración del negocio</h2>
        <p class="subtitulo-pagina">Datos generales y horario de atención.</p>
    </div>

    <form id="contenedor-form-configuracion">
        <div class="card-elevada mb-4">
            <div class="titulo-bloque">Datos generales</div>
            <div class="ayuda-bloque">Cómo se identifica tu negocio dentro de la plataforma.</div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre del negocio</label>
                    <input type="text" id="nombre_negocio" name="nombre_negocio" maxlength="150" class="form-control system_validador_vacio">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Teléfono de contacto</label>
                    <input type="text" id="telefono_contacto" name="telefono_contacto" maxlength="30" class="form-control">
                </div>
            </div>
        </div>

        <div class="card-elevada mb-4">
            <div class="titulo-bloque">Horario de atención</div>
            <div class="ayuda-bloque">Días y horas en que tu negocio recibe clientes.</div>

            <label class="form-label">Días de atención</label>
            <div class="dias-atencion">
                @php
                    $diasSemana = [
                        1 => 'Lunes',
                        2 => 'Martes',
                        3 => 'Miércoles',
                        4 => 'Jueves',
                        5 => 'Viernes',
                        6 => 'Sábado',
                        7 => 'Domingo',
                    ];
                @endphp
                @foreach ($diasSemana as $numeroDia => $nombreDia)
                    <span class="dia-chip">
                        <input type="checkbox" id="dia-{{ $numeroDia }}" class="check-dia" value="{{ $numeroDia }}">
                        <label for="dia-{{ $numeroDia }}">{{ $nombreDia }}</label>
                    </span>
                @endforeach
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Hora de apertura</label>
                    <input type="time" id="hora_apertura" name="hora_apertura" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hora de cierre</label>
                    <input type="time" id="hora_cierre" name="hora_cierre" class="form-control">
                </div>
            </div>

            <div class="mt-3">
                <small class="texto-ayuda-campo">
                    <i class="bi bi-info-circle"></i>
                    Este horario se usará para mostrar las franjas disponibles en el calendario de reservas.
                </small>
            </div>
        </div>
    </form>

    <div class="card-elevada">
        <button type="button" id="btn-guardar-configuracion" class="btn-primario-accento">
            <i class="bi bi-check2"></i> Guardar cambios
        </button>
    </div>
@endsection

@section('scripts')
    <script>
        function cargarConfiguracion() {
            axiosSipleInterno('GET', 'request/negocio/configuracion', {}, {}, true, function (respuesta) {
                if (respuesta.error != 0) {
                    notificarUsuario(respuesta.mensaje, 'error');
                    return;
                }

                var negocio = respuesta.data.negocio;

                if (!negocio || jQuery.isEmptyObject(negocio)) {
                    return;
                }

                jQuery('#nombre_negocio').val(negocio.nombre_negocio);
                jQuery('#telefono_contacto').val(negocio.telefono_contacto);
                jQuery('#hora_apertura').val(negocio.hora_apertura);
                jQuery('#hora_cierre').val(negocio.hora_cierre);

                // dias_atencion viene como "1,2,3,4,5"
                jQuery('.check-dia').prop('checked', false);

                if (negocio.dias_atencion) {
                    negocio.dias_atencion.split(',').forEach(function (dia) {
                        jQuery('.check-dia[value="' + jQuery.trim(dia) + '"]').prop('checked', true);
                    });
                }
            });
        }

        jQuery('#btn-guardar-configuracion').on('click', function () {
            if (!system_validarcampos('contenedor-form-configuracion', 1)) {
                return;
            }

            // Los checkboxes se recolectan aparte: no forman parte del serializado.
            var diasSeleccionados = [];
            jQuery('.check-dia:checked').each(function () {
                diasSeleccionados.push(jQuery(this).val());
            });

            var datos = {
                nombre_negocio: jQuery('#nombre_negocio').val(),
                telefono_contacto: jQuery('#telefono_contacto').val(),
                dias_atencion: diasSeleccionados,
                hora_apertura: jQuery('#hora_apertura').val(),
                hora_cierre: jQuery('#hora_cierre').val()
            };

            axiosSipleInterno('POST', 'request/negocio/actualizar-configuracion', {}, datos, true, function (respuesta) {
                if (respuesta.error == 0) {
                    // Se refresca antes del aviso para que el check aparezca al instante.
                    if (window.refrescarOnboarding) { window.refrescarOnboarding(); }
                    notificarUsuario('Configuración guardada correctamente', 'success', 'reload');
                } else {
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        jQuery(document).ready(function () {
            cargarConfiguracion();
        });
    </script>
@endsection
