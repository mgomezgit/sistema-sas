@extends('layout.backoffice')

@section('title', 'Personalizar')

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

        /* ---------- Selector de modo ---------- */
        .opciones-modo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            max-width: 560px;
        }

        .opcion-modo {
            background-color: var(--bg-input);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 1.5rem 1.25rem;
            cursor: pointer;
            text-align: center;
            transition: var(--transition-base);
            position: relative;
        }

        .opcion-modo:hover {
            border-color: var(--border-color-strong);
            transform: translateY(-2px);
        }

        .opcion-modo.seleccionada {
            border-color: var(--accent);
            background-color: var(--accent-soft);
        }

        .opcion-modo i.icono-modo {
            font-size: 1.8rem;
            color: var(--accent);
            display: block;
            margin-bottom: 0.6rem;
        }

        .opcion-modo .nombre-modo {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .opcion-modo .check-modo {
            position: absolute;
            top: 0.6rem;
            right: 0.7rem;
            color: var(--accent);
            font-size: 1.1rem;
            opacity: 0;
            transition: var(--transition-base);
        }

        .opcion-modo.seleccionada .check-modo {
            opacity: 1;
        }

        /* ---------- Selector de acento ---------- */
        .opciones-acento {
            display: flex;
            flex-wrap: wrap;
            gap: 1.4rem;
        }

        .opcion-acento {
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            text-align: center;
            width: 84px;
        }

        .circulo-acento {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            margin: 0 auto 0.55rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 1.15rem;
            border: 3px solid transparent;
            box-shadow: 0 0 0 2px transparent;
            transition: var(--transition-base);
        }

        .opcion-acento:hover .circulo-acento {
            transform: scale(1.08);
        }

        .opcion-acento.seleccionada .circulo-acento {
            border-color: var(--bg-card);
            box-shadow: 0 0 0 2px var(--text-primary);
        }

        .circulo-acento i {
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .opcion-acento.seleccionada .circulo-acento i {
            opacity: 1;
        }

        .nombre-acento {
            color: var(--text-secondary);
            font-size: 0.78rem;
            font-weight: 500;
            display: block;
        }

        .opcion-acento.seleccionada .nombre-acento {
            color: var(--text-primary);
            font-weight: 600;
        }

        .acciones-personalizar {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .aviso-vivo {
            color: var(--text-secondary);
            font-size: 0.83rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 1rem;
        }

        .aviso-vivo i {
            color: var(--accent);
        }
    </style>
@endsection

@section('content')
    <div class="mb-4">
        <h2 class="titulo-pagina">Personaliza tu panel</h2>
        <p class="subtitulo-pagina">Elige cómo se ve tu espacio de trabajo. Los cambios se aplican a todo tu equipo.</p>
    </div>

    <div class="card-elevada mb-4">
        <div class="titulo-bloque">Modo</div>
        <div class="ayuda-bloque">Define el fondo general del panel.</div>

        <div class="opciones-modo">
            <div class="opcion-modo" data-modo="claro">
                <i class="bi bi-check-circle-fill check-modo"></i>
                <i class="bi bi-sun icono-modo"></i>
                <span class="nombre-modo">Claro</span>
            </div>
            <div class="opcion-modo" data-modo="oscuro">
                <i class="bi bi-check-circle-fill check-modo"></i>
                <i class="bi bi-moon-stars icono-modo"></i>
                <span class="nombre-modo">Oscuro</span>
            </div>
        </div>
    </div>

    <div class="card-elevada mb-4">
        <div class="titulo-bloque">Color de acento</div>
        <div class="ayuda-bloque">Es el color de marca: botones, enlaces y elementos destacados.</div>

        <div class="opciones-acento">
            {{-- Los hex van fijos porque son las opciones a elegir, no el tema aplicado. --}}
            <button type="button" class="opcion-acento" data-acento="oro_rosa">
                <span class="circulo-acento" style="background-color: #b76e79;"><i class="bi bi-check-lg"></i></span>
                <span class="nombre-acento">Oro rosa</span>
            </button>
            <button type="button" class="opcion-acento" data-acento="dorado">
                <span class="circulo-acento" style="background-color: #c9a227;"><i class="bi bi-check-lg"></i></span>
                <span class="nombre-acento">Dorado</span>
            </button>
            <button type="button" class="opcion-acento" data-acento="amarillo">
                <span class="circulo-acento" style="background-color: #eab308;"><i class="bi bi-check-lg"></i></span>
                <span class="nombre-acento">Amarillo</span>
            </button>
            <button type="button" class="opcion-acento" data-acento="naranja">
                <span class="circulo-acento" style="background-color: #ea580c;"><i class="bi bi-check-lg"></i></span>
                <span class="nombre-acento">Naranja</span>
            </button>
            <button type="button" class="opcion-acento" data-acento="rojo">
                <span class="circulo-acento" style="background-color: #e11d2e;"><i class="bi bi-check-lg"></i></span>
                <span class="nombre-acento">Rojo</span>
            </button>
            <button type="button" class="opcion-acento" data-acento="azul">
                <span class="circulo-acento" style="background-color: #2563eb;"><i class="bi bi-check-lg"></i></span>
                <span class="nombre-acento">Azul</span>
            </button>
            <button type="button" class="opcion-acento" data-acento="verde">
                <span class="circulo-acento" style="background-color: #16a34a;"><i class="bi bi-check-lg"></i></span>
                <span class="nombre-acento">Verde</span>
            </button>
        </div>

        <div class="aviso-vivo">
            <i class="bi bi-eye"></i>
            Lo que ves es una vista previa en vivo. Guarda para aplicarlo a todo tu equipo.
        </div>
    </div>

    <div class="card-elevada">
        <div class="acciones-personalizar" style="border-top: none; padding-top: 0;">
            <button type="button" id="btn-guardar-tema" class="btn-primario-accento">
                <i class="bi bi-check2"></i> Guardar personalización
            </button>
            <button type="button" id="btn-cancelar-tema" class="btn btn-secondary">
                <i class="bi bi-arrow-counterclockwise"></i> Cancelar
            </button>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Selección en curso (puede diferir de lo guardado hasta pulsar Guardar).
        var modoSeleccionado = @json(session('modo_tema') ?? 'oscuro');
        var acentoSeleccionado = @json(session('color_acento') ?? 'rojo');

        // La clase CSS usa guiones; en base de datos el valor lleva guion bajo.
        function claseAcento(valor) {
            return 'acento-' + valor.replace(/_/g, '-');
        }

        // Aplica el tema al documento completo al instante, sin recargar.
        function aplicarTemaEnVivo() {
            document.body.className = 'modo-' + modoSeleccionado + ' ' + claseAcento(acentoSeleccionado);
            marcarSeleccion();
        }

        function marcarSeleccion() {
            jQuery('.opcion-modo').removeClass('seleccionada');
            jQuery('.opcion-modo[data-modo="' + modoSeleccionado + '"]').addClass('seleccionada');

            jQuery('.opcion-acento').removeClass('seleccionada');
            jQuery('.opcion-acento[data-acento="' + acentoSeleccionado + '"]').addClass('seleccionada');
        }

        jQuery('.opcion-modo').on('click', function () {
            modoSeleccionado = jQuery(this).data('modo');
            aplicarTemaEnVivo();
        });

        jQuery('.opcion-acento').on('click', function () {
            acentoSeleccionado = jQuery(this).data('acento');
            aplicarTemaEnVivo();
        });

        jQuery('#btn-guardar-tema').on('click', function () {
            axiosSipleInterno('POST', 'request/negocio/actualizar-tema', {}, {
                modo_tema: modoSeleccionado,
                color_acento: acentoSeleccionado
            }, true, function (respuesta) {
                if (respuesta.error == 0) {
                    notificarUsuario('Personalización guardada. Se aplicará a todo tu equipo.', 'success', 'reload');
                } else {
                    // No se revierte la vista previa: el usuario puede seguir probando.
                    notificarUsuario(respuesta.mensaje, 'error');
                }
            });
        });

        jQuery('#btn-cancelar-tema').on('click', function () {
            window.location.reload();
        });

        jQuery(document).ready(function () {
            marcarSeleccion();
        });
    </script>
@endsection
