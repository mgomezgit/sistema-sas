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

        .ayuda-bloque {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
        }

        .ayuda-campo {
            color: var(--text-muted);
            font-size: 0.78rem;
            margin-top: 0.3rem;
        }

        /* El rótulo de los días no puede ser un label flotante: no cuelga de un
           campo, sino de un grupo de siete casillas. */
        .rotulo-grupo {
            color: var(--text-secondary);
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        /* La página no es un modal, así que el botón necesita su propia franja
           al pie de la tarjeta en vez del modal-footer. */
        .pie-formulario {
            display: flex;
            justify-content: flex-end;
            margin-top: 1.1rem;
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

        /* ---------- Pestañas (mismo patrón que Comisiones) ---------- */
        .nav-configuracion {
            border-bottom: 1px solid var(--border-color);
            gap: 0.25rem;
            margin-bottom: 1.5rem;
        }

        .nav-configuracion .nav-link {
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

        .nav-configuracion .nav-link:hover {
            color: var(--text-primary);
            background-color: var(--bg-card-hover);
        }

        .nav-configuracion .nav-link.active {
            color: var(--accent);
            background-color: transparent;
            border-bottom-color: var(--accent);
        }

        /* ---------- Rejilla de banners ---------- */
        .rejilla-banners {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .tarjeta-banner {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: var(--transition-base);
        }

        .tarjeta-banner:hover {
            border-color: var(--border-color-strong);
        }

        /* La miniatura mantiene proporción apaisada pase lo que pase con la
           imagen subida: así la rejilla no se descuadra con una vertical. */
        .miniatura-banner {
            aspect-ratio: 16 / 7;
            background-color: var(--bg-input);
            overflow: hidden;
        }

        .miniatura-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .tarjeta-banner.inactivo .miniatura-banner img {
            filter: grayscale(1);
            opacity: 0.55;
        }

        .cuerpo-banner {
            padding: 0.85rem 0.95rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            flex: 1;
        }

        .titulo-banner {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .titulo-banner.sin-titulo {
            color: var(--text-muted);
            font-style: italic;
            font-weight: 500;
        }

        .texto-banner {
            color: var(--text-secondary);
            font-size: 0.82rem;
            line-height: 1.4;
        }

        .vigencia-banner {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: var(--text-muted);
            font-size: 0.78rem;
        }

        .pie-banner {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: auto;
            padding-top: 0.6rem;
            border-top: 1px solid var(--border-color);
        }

        .pie-banner .acciones-banner {
            margin-left: auto;
        }

        .insignia-orden {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background-color: var(--bg-input);
            color: var(--text-secondary);
            border-radius: 999px;
            padding: 0.18rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .banners-vacio {
            grid-column: 1 / -1;
            color: var(--text-secondary);
            text-align: center;
            padding: 2.5rem 1rem;
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-card);
        }

        .banners-vacio i {
            font-size: 2rem;
            color: var(--text-muted);
            display: block;
            margin-bottom: 0.75rem;
        }

        /* ---------- Selector de imagen del modal ---------- */
        .zona-imagen {
            display: flex;
            align-items: center;
            justify-content: center;
            aspect-ratio: 16 / 7;
            width: 100%;
            border: 1px dashed var(--border-color-strong);
            border-radius: var(--radius-sm);
            background-color: var(--bg-input);
            cursor: pointer;
            overflow: hidden;
            transition: var(--transition-base);
            margin-bottom: 0;
        }

        .zona-imagen:hover {
            border-color: var(--accent);
        }

        .zona-imagen img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .marcador-zona-imagen {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.35rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .marcador-zona-imagen i {
            font-size: 1.6rem;
        }

        .mensaje-imagen {
            color: var(--danger);
            font-size: 0.78rem;
            font-weight: 500;
            margin-top: 0.4rem;
            text-align: center;
        }
    </style>
@endsection

@section('content')
    <div class="mb-4">
        <h2 class="titulo-pagina">Configuración del negocio</h2>
        <p class="subtitulo-pagina">Datos generales, horario de atención y banners de tu página pública.</p>
    </div>

    <ul class="nav nav-tabs nav-configuracion" id="pestanas-configuracion" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link active" id="tab-datos" data-bs-toggle="tab"
                data-bs-target="#panel-datos" role="tab" aria-controls="panel-datos" aria-selected="true">
                <i class="bi bi-shop"></i> Datos del negocio
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" id="tab-banners" data-bs-toggle="tab"
                data-bs-target="#panel-banners" role="tab" aria-controls="panel-banners" aria-selected="false">
                <i class="bi bi-images"></i> Banners
            </button>
        </li>
    </ul>

    <div class="tab-content">
    {{-- ================= PESTAÑA 1: DATOS DEL NEGOCIO =================
         Se conserva tal cual estaba. Va como pestaña ACTIVA de entrada, y eso
         importa: el tour de onboarding se engancha a #tour-dias-atencion,
         #hora_apertura y #hora_cierre, y Shepherd no sabe posicionarse sobre
         elementos que están dentro de un panel oculto. --}}
    <div class="tab-pane fade show active" id="panel-datos" role="tabpanel" aria-labelledby="tab-datos">
    <div class="card-elevada">
        <form id="contenedor-form-configuracion">
            <div class="tarjeta-seccion-form">
                <div class="etiqueta-seccion-form">Datos generales</div>
                <div class="ayuda-bloque">Cómo se identifica tu negocio dentro de la plataforma.</div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="campo-flotante">
                            <i class="bi bi-shop"></i>
                            <input type="text" id="nombre_negocio" name="nombre_negocio" maxlength="150" placeholder=" " class="system_validador_vacio">
                            <label for="nombre_negocio">Nombre del negocio</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="campo-flotante">
                            <i class="bi bi-telephone"></i>
                            <input type="text" id="telefono_contacto" name="telefono_contacto" maxlength="30" placeholder=" ">
                            <label for="telefono_contacto">Teléfono de contacto</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="campo-flotante">
                            <i class="bi bi-link-45deg"></i>
                            <input type="text" id="slug" name="slug" maxlength="100" placeholder=" ">
                            <label for="slug">Dirección de tu página pública</label>
                        </div>
                        <div class="ayuda-campo">
                            Tus clientes te encontrarán en <b id="vista-previa-slug">{{ url('reservar') }}/…</b>.
                            Si la cambias, los enlaces que ya compartiste dejarán de funcionar.
                        </div>
                        {{-- Estas dos acciones trabajan sobre el slug YA GUARDADO, no
                             sobre lo que se esté escribiendo: mientras el admin edita sin
                             guardar, la página que existe de verdad sigue siendo la
                             anterior, y copiar/abrir el texto a medio escribir daría un
                             enlace roto. Quedan ocultas mientras el negocio no tenga slug. --}}
                        <div class="d-flex gap-2 flex-wrap mt-2" id="acciones-enlace-publico" style="display: none;">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-copiar-enlace-publico">
                                <i class="bi bi-clipboard"></i>
                                <span id="texto-btn-copiar-enlace">Copiar enlace</span>
                            </button>
                            <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" id="btn-ver-pagina-publica">
                                <i class="bi bi-box-arrow-up-right"></i>
                                Ver mi página
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tarjeta-seccion-form">
                <div class="etiqueta-seccion-form">Horario de atención</div>
                <div class="ayuda-bloque">Días y horas en que tu negocio recibe clientes.</div>

                {{-- Los días son una selección múltiple, no un sí/no: se quedan
                     como casillas y NO se convierten en interruptores. --}}
                <div class="rotulo-grupo">Días de atención</div>
                <div class="dias-atencion" id="tour-dias-atencion">
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
                        <div class="campo-flotante">
                            <i class="bi bi-sunrise"></i>
                            <input type="time" id="hora_apertura" name="hora_apertura" placeholder=" ">
                            <label for="hora_apertura">Hora de apertura</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="campo-flotante">
                            <i class="bi bi-sunset"></i>
                            <input type="time" id="hora_cierre" name="hora_cierre" placeholder=" ">
                            <label for="hora_cierre">Hora de cierre</label>
                        </div>
                    </div>
                </div>

                <div class="ayuda-campo mt-3">
                    <i class="bi bi-info-circle"></i>
                    Este horario se usará para mostrar las franjas disponibles en el calendario de reservas.
                </div>
            </div>
        </form>

        <div class="pie-formulario">
            <button type="button" id="btn-guardar-configuracion" class="btn-guardar-moderno">
                <i class="bi bi-check2 icono-guardar"></i>
                <span id="texto-btn-guardar-configuracion">Guardar cambios</span>
            </button>
        </div>
    </div>
    </div>

    {{-- ================= PESTAÑA 2: BANNERS ================= --}}
    <div class="tab-pane fade" id="panel-banners" role="tabpanel" aria-labelledby="tab-banners">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <p class="subtitulo-pagina">
                Imágenes que rotan en la cabecera de tu página pública. Puedes darles una vigencia para que aparezcan y se retiren solas.
            </p>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <label class="interruptor-moderno" id="filtro-mostrar-inactivos">
                    <input type="checkbox" id="chk-mostrar-inactivos">
                    <span class="pista-interruptor"></span>
                    <span class="texto-interruptor">Mostrar inactivos</span>
                </label>
                <button type="button" id="btn-nuevo-banner" class="btn-primario-accento">
                    <i class="bi bi-plus-lg"></i> Nuevo banner
                </button>
            </div>
        </div>

        <div class="rejilla-banners" id="contenedor-banners"></div>
    </div>
    </div>

    {{-- ================= MODAL: BANNER ================= --}}
    <div class="modal fade modal-moderno" id="modal-banner" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header-moderno">
                    <span class="insignia-encabezado"><i class="bi bi-image"></i></span>
                    <div>
                        <h5 class="titulo-modal-moderno" id="modal-banner-titulo-texto">Nuevo banner</h5>
                        <p class="subtitulo-modal-moderno" id="modal-banner-subtitulo">Sube una imagen para tu página pública</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="contenedor-form-banner">
                        <input type="hidden" id="id_banner" name="id_banner">

                        <div class="tarjeta-seccion-form">
                            <div class="etiqueta-seccion-form">Imagen</div>

                            <label class="zona-imagen" id="zona-imagen-banner" for="imagen_banner">
                                <img id="vista-previa-banner" alt="" hidden>
                                <span class="marcador-zona-imagen" id="marcador-zona-imagen">
                                    <i class="bi bi-cloud-arrow-up"></i>
                                    <span>Elige una imagen</span>
                                </span>
                            </label>
                            <input type="file" id="imagen_banner" name="imagen" accept=".jpg,.jpeg,.png,.webp" hidden>

                            <div class="ayuda-campo mt-2 text-center">
                                JPG, PNG o WEBP, hasta 2 MB. Se recomienda una imagen apaisada.
                            </div>
                            <div class="mensaje-imagen" id="mensaje-imagen-banner" hidden></div>
                        </div>

                        <div class="tarjeta-seccion-form">
                            <div class="etiqueta-seccion-form">Texto del banner</div>

                            <div class="campo-flotante">
                                <i class="bi bi-type"></i>
                                <input type="text" id="titulo_banner" name="titulo" maxlength="150" placeholder=" ">
                                <label for="titulo_banner">Título</label>
                            </div>

                            <div class="campo-flotante mt-3">
                                <i class="bi bi-card-text"></i>
                                <textarea id="texto_banner" name="texto" rows="2" maxlength="300" placeholder=" "></textarea>
                                <label for="texto_banner">Texto</label>
                            </div>

                            <div class="ayuda-campo">Ambos son opcionales: una imagen sola también funciona.</div>
                        </div>

                        <div class="tarjeta-seccion-form">
                            <div class="etiqueta-seccion-form">Vigencia y orden</div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="campo-flotante">
                                        <i class="bi bi-calendar-plus"></i>
                                        <input type="date" id="fecha_inicio_banner" name="fecha_inicio" placeholder=" ">
                                        <label for="fecha_inicio_banner">Desde</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="campo-flotante">
                                        <i class="bi bi-calendar-x"></i>
                                        <input type="date" id="fecha_fin_banner" name="fecha_fin" placeholder=" ">
                                        <label for="fecha_fin_banner">Hasta</label>
                                    </div>
                                </div>
                            </div>

                            <div class="ayuda-campo">
                                Déjalas vacías para que el banner esté siempre visible. Solo "Desde" lo publica a partir de ese día; solo "Hasta" lo retira después de ese día.
                            </div>

                            <div class="rotulo-stepper mt-3">Orden en el carrusel</div>
                            <div class="stepper-campo">
                                <button type="button" class="btn-stepper" data-paso="-1" aria-label="Restar uno">
                                    <i class="bi bi-dash-lg"></i>
                                </button>
                                <input type="number" id="orden_banner" name="orden" min="0" step="1" class="valor-stepper">
                                <button type="button" class="btn-stepper" data-paso="1" aria-label="Sumar uno">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                            <div class="ayuda-campo mt-2 text-center">Los números más bajos se muestran primero.</div>
                        </div>

                        {{-- Solo al editar: un banner recién subido siempre nace activo. --}}
                        <div class="tarjeta-seccion-form" id="seccion-estado-banner" hidden>
                            <div class="etiqueta-seccion-form">Estado</div>

                            <label class="interruptor-moderno">
                                <input type="checkbox" id="estado_banner">
                                <span class="pista-interruptor"></span>
                                <span class="texto-interruptor" id="texto-estado-banner">Banner activo</span>
                            </label>

                            <div class="ayuda-campo mt-2">
                                Un banner inactivo deja de mostrarse en tu página pública, pero su imagen se conserva y puede reactivarse desde aquí.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-guardar-banner" class="btn-guardar-moderno">
                        <i class="bi bi-check2 icono-guardar"></i>
                        <span id="texto-btn-guardar-banner">Guardar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Milisegundos que el botón se queda en "Guardado" antes de volver a su
        // estado normal, para que el check no pase inadvertido.
        var ESPERA_CONFIRMACION_GUARDADO = 700;

        /**
         * Los tres estados del botón de guardar que define el kit: normal,
         * "ocupado" (con su spinner) y "exito" (con su check).
         */
        function estadoBotonGuardarConfiguracion(estado) {
            var boton = jQuery('#btn-guardar-configuracion');

            boton.removeClass('ocupado exito');

            if (estado === 'ocupado') {
                boton.addClass('ocupado');
                jQuery('#texto-btn-guardar-configuracion').text('Guardando');
            } else if (estado === 'exito') {
                boton.addClass('exito');
                jQuery('#texto-btn-guardar-configuracion').text('Guardado');
            } else {
                jQuery('#texto-btn-guardar-configuracion').text('Guardar cambios');
            }
        }

        var BASE_PAGINA_PUBLICA = '{{ url('reservar') }}/';

        // Solo muestra a dónde apunta lo que hay escrito; el slug definitivo lo
        // normaliza el backend, que es quien además comprueba que esté libre.
        function refrescarVistaPreviaSlug() {
            var escrito = jQuery.trim(jQuery('#slug').val());

            jQuery('#vista-previa-slug').text(BASE_PAGINA_PUBLICA + (escrito !== '' ? escrito : '…'));
        }

        jQuery('#slug').on('input', refrescarVistaPreviaSlug);

        /* ---------- Acciones sobre el enlace público ---------- */

        // Slug tal como quedó guardado en el backend. No se toca al escribir:
        // solo se refresca al cargar la pantalla y después de guardar, porque
        // el backend normaliza el texto con Str::slug() y el valor bueno únicamente
        // se conoce releyéndolo (escribir "Mi Spa" guarda "mi-spa").
        var slugGuardado = '';

        var TEXTO_BOTON_COPIAR = 'Copiar enlace';
        var ESPERA_CONFIRMACION_COPIADO = 2000;

        function urlPaginaPublica() {
            return slugGuardado !== '' ? BASE_PAGINA_PUBLICA + slugGuardado : '';
        }

        function refrescarAccionesEnlacePublico() {
            var url = urlPaginaPublica();

            if (url === '') {
                jQuery('#acciones-enlace-publico').hide();

                return;
            }

            jQuery('#btn-ver-pagina-publica').attr('href', url);
            jQuery('#acciones-enlace-publico').css('display', 'flex');
        }

        function establecerSlugGuardado(slug) {
            slugGuardado = jQuery.trim(slug || '');
            refrescarAccionesEnlacePublico();
        }

        jQuery('#btn-copiar-enlace-publico').on('click', function () {
            var url = urlPaginaPublica();

            if (url === '') {
                return;
            }

            // navigator.clipboard no existe fuera de contexto seguro y puede ser
            // denegado por permisos del navegador. En cualquiera de los dos casos
            // se le enseña la URL al admin para que la copie a mano, en vez de
            // dejarlo sin saber qué pasó.
            if (!navigator.clipboard || !navigator.clipboard.writeText) {
                notificarUsuario('Copia el enlace manualmente: ' + url, 'info');

                return;
            }

            navigator.clipboard.writeText(url).then(function () {
                var texto = jQuery('#texto-btn-copiar-enlace');

                texto.text('¡Copiado!');

                setTimeout(function () {
                    texto.text(TEXTO_BOTON_COPIAR);
                }, ESPERA_CONFIRMACION_COPIADO);
            }).catch(function () {
                notificarUsuario('Copia el enlace manualmente: ' + url, 'info');
            });
        });

        function cargarConfiguracion(mostrarLoader) {
            axiosSipleInterno('GET', 'request/negocio/configuracion', {}, {}, mostrarLoader !== false, function (respuesta) {
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
                jQuery('#slug').val(negocio.slug);
                refrescarVistaPreviaSlug();
                establecerSlugGuardado(negocio.slug);
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
                slug: jQuery('#slug').val(),
                dias_atencion: diasSeleccionados,
                hora_apertura: jQuery('#hora_apertura').val(),
                hora_cierre: jQuery('#hora_cierre').val()
            };

            // El propio botón hace de indicador, así que no se levanta el loader
            // que tapa la pantalla: el formulario sigue a la vista.
            estadoBotonGuardarConfiguracion('ocupado');

            axiosSipleInterno('POST', 'request/negocio/actualizar-configuracion', {}, datos, false, function (respuesta) {
                if (!respuesta || respuesta.error != 0) {
                    estadoBotonGuardarConfiguracion('normal');

                    if (respuesta) {
                        notificarUsuario(respuesta.mensaje, 'error');
                    }

                    return;
                }

                estadoBotonGuardarConfiguracion('exito');

                // El nombre se refleja en la barra lateral y en el menú de
                // usuario sin recargar la pantalla completa.
                jQuery('#nombre-negocio-lateral').text(datos.nombre_negocio).attr('title', datos.nombre_negocio);
                jQuery('#nombre-negocio-sesion').text(datos.nombre_negocio);

                // Se relee la configuración en vez de dar por bueno lo escrito: el
                // backend normaliza el slug con Str::slug(), así que esta es la única
                // forma de que el enlace a copiar/abrir sea el real. Sin loader, para
                // no tapar el formulario que sigue a la vista.
                cargarConfiguracion(false);

                // Aquí no hay modal que cerrar: el botón vuelve solo a su
                // estado normal después de enseñar el check.
                setTimeout(function () {
                    estadoBotonGuardarConfiguracion('normal');
                    avisarGuardado('Configuración guardada correctamente');
                }, ESPERA_CONFIRMACION_GUARDADO);
            });
        });

        /* ================= PESTAÑA 2: BANNERS ================= */

        var modoFormularioBanner = 'crear';
        var bannersCargados = false;
        // El archivo elegido vive aquí hasta que se envía: un input[type=file]
        // no se puede rellenar por JS, así que al editar no hay forma de
        // "precargarlo" — simplemente, si no se elige uno nuevo, no se manda.
        var imagenElegida = null;

        var PESO_MAXIMO_BANNER_MB = 2;
        var TIPOS_IMAGEN_VALIDOS = ['image/jpeg', 'image/png', 'image/webp'];

        function estadoBotonGuardarBanner(estado) {
            var boton = jQuery('#btn-guardar-banner');

            boton.removeClass('ocupado exito');

            if (estado === 'ocupado') {
                boton.addClass('ocupado');
                jQuery('#texto-btn-guardar-banner').text('Guardando');
            } else if (estado === 'exito') {
                boton.addClass('exito');
                jQuery('#texto-btn-guardar-banner').text('Guardado');
            } else {
                jQuery('#texto-btn-guardar-banner').text('Guardar');
            }
        }

        function establecerEstadoBanner(activo) {
            jQuery('#estado_banner').prop('checked', activo);
            jQuery('#texto-estado-banner').text(activo ? 'Banner activo' : 'Banner inactivo');
        }

        jQuery('#estado_banner').on('change', function () {
            establecerEstadoBanner(jQuery(this).is(':checked'));
        });

        function mostrarMensajeImagen(mensaje) {
            jQuery('#mensaje-imagen-banner').text(mensaje || '').prop('hidden', !mensaje);
        }

        function mostrarVistaPrevia(url) {
            if (url) {
                jQuery('#vista-previa-banner').attr('src', url).prop('hidden', false);
                jQuery('#marcador-zona-imagen').prop('hidden', true);
            } else {
                jQuery('#vista-previa-banner').removeAttr('src').prop('hidden', true);
                jQuery('#marcador-zona-imagen').prop('hidden', false);
            }
        }

        // Se valida en el navegador para dar respuesta inmediata; el backend
        // valida igual por su cuenta, que es donde la regla de verdad manda.
        jQuery('#imagen_banner').on('change', function () {
            var archivo = this.files && this.files[0] ? this.files[0] : null;

            mostrarMensajeImagen('');

            if (!archivo) {
                imagenElegida = null;
                return;
            }

            if (TIPOS_IMAGEN_VALIDOS.indexOf(archivo.type) === -1) {
                imagenElegida = null;
                this.value = '';
                mostrarMensajeImagen('Ese formato no sirve: usa JPG, PNG o WEBP.');
                return;
            }

            if (archivo.size > PESO_MAXIMO_BANNER_MB * 1024 * 1024) {
                imagenElegida = null;
                this.value = '';
                mostrarMensajeImagen('La imagen pesa más de ' + PESO_MAXIMO_BANNER_MB + ' MB. Elige una más liviana.');
                return;
            }

            imagenElegida = archivo;
            mostrarVistaPrevia(URL.createObjectURL(archivo));
        });

        function limpiarFormularioBanner() {
            jQuery('#id_banner').val('');
            jQuery('#titulo_banner').val('');
            jQuery('#texto_banner').val('');
            jQuery('#fecha_inicio_banner').val('');
            jQuery('#fecha_fin_banner').val('');
            jQuery('#orden_banner').val('0');
            jQuery('#imagen_banner').val('');
            imagenElegida = null;
            mostrarVistaPrevia(null);
            mostrarMensajeImagen('');
            jQuery('#contenedor-form-banner .input_vacio').removeClass('input_vacio');
            jQuery('#seccion-estado-banner').prop('hidden', true);
            establecerEstadoBanner(true);
            estadoBotonGuardarBanner('normal');
        }

        function formatearFechaBanner(texto) {
            if (!texto) {
                return '';
            }

            var partes = String(texto).substring(0, 10).split('-');

            return partes[2] + '/' + partes[1] + '/' + partes[0];
        }

        function textoVigencia(banner) {
            if (!banner.fecha_inicio && !banner.fecha_fin) {
                return 'Siempre visible';
            }

            if (banner.fecha_inicio && banner.fecha_fin) {
                return formatearFechaBanner(banner.fecha_inicio) + ' → ' + formatearFechaBanner(banner.fecha_fin);
            }

            return banner.fecha_inicio
                ? 'Desde el ' + formatearFechaBanner(banner.fecha_inicio)
                : 'Hasta el ' + formatearFechaBanner(banner.fecha_fin);
        }

        function cargarBanners() {
            var incluirInactivos = jQuery('#chk-mostrar-inactivos').is(':checked') ? 1 : 0;

            axiosSipleInterno('GET', 'request/banner/listar', { incluir_inactivos: incluirInactivos }, {}, true, function (respuesta) {
                if (respuesta.error != 0) {
                    notificarUsuario(respuesta.mensaje, 'error');
                    return;
                }

                bannersCargados = true;
                pintarBanners(respuesta.data.banners);
            });
        }

        jQuery('#chk-mostrar-inactivos').on('change', cargarBanners);

        function pintarBanners(banners) {
            var contenedor = jQuery('#contenedor-banners');
            contenedor.empty();

            if (!banners || banners.length === 0) {
                contenedor.html(
                    '<div class="banners-vacio">' +
                    '<i class="bi bi-images"></i>' +
                    'Todavía no tienes banners. Crea el primero para que tu página pública tenga algo que mostrar.' +
                    '</div>'
                );
                return;
            }

            banners.forEach(function (banner) {
                var activo = banner.estado == 1;

                var tarjeta = jQuery('<div>').addClass('tarjeta-banner' + (activo ? '' : ' inactivo'));

                var miniatura = jQuery('<div>').addClass('miniatura-banner');
                jQuery('<img>').attr('src', banner.imagen_url).attr('alt', banner.titulo || 'Banner').appendTo(miniatura);
                miniatura.appendTo(tarjeta);

                var cuerpo = jQuery('<div>').addClass('cuerpo-banner');

                if (banner.titulo) {
                    jQuery('<div>').addClass('titulo-banner').text(banner.titulo).appendTo(cuerpo);
                } else {
                    jQuery('<div>').addClass('titulo-banner sin-titulo').text('Sin título').appendTo(cuerpo);
                }

                if (banner.texto) {
                    jQuery('<div>').addClass('texto-banner').text(banner.texto).appendTo(cuerpo);
                }

                jQuery('<div>').addClass('vigencia-banner')
                    .append(jQuery('<i>').addClass('bi bi-calendar-range'))
                    .append(jQuery('<span>').text(textoVigencia(banner)))
                    .appendTo(cuerpo);

                var pie = jQuery('<div>').addClass('pie-banner');

                jQuery('<span>').addClass('insignia-orden')
                    .append(jQuery('<i>').addClass('bi bi-sort-numeric-down'))
                    .append(document.createTextNode(banner.orden))
                    .appendTo(pie);

                jQuery('<span>')
                    .addClass(activo ? 'badge-estado-activo' : 'badge-estado-inactivo')
                    .append(jQuery('<i>').addClass(activo ? 'bi bi-check-circle-fill' : 'bi bi-dash-circle-fill'))
                    .append(document.createTextNode(' ' + (activo ? 'Activo' : 'Inactivo')))
                    .appendTo(pie);

                var acciones = jQuery('<span>').addClass('acciones-banner');

                jQuery('<button>')
                    .attr('type', 'button')
                    .addClass('btn-accion-icono btn-editar-banner')
                    .attr('data-id_banner', banner.id_banner)
                    .attr('title', 'Editar')
                    .append(jQuery('<i>').addClass('bi bi-pencil-square'))
                    .appendTo(acciones);

                // La papelera solo tiene sentido sobre uno activo: el que ya
                // está de baja se reactiva desde el modal.
                if (activo) {
                    jQuery('<button>')
                        .attr('type', 'button')
                        .addClass('btn-accion-icono btn-accion-eliminar btn-eliminar-banner')
                        .attr('data-id_banner', banner.id_banner)
                        .attr('title', 'Desactivar')
                        .append(jQuery('<i>').addClass('bi bi-trash3'))
                        .appendTo(acciones);
                }

                acciones.appendTo(pie);
                pie.appendTo(cuerpo);
                cuerpo.appendTo(tarjeta);

                tarjeta.data('banner', banner).appendTo(contenedor);
            });
        }

        jQuery('#btn-nuevo-banner').on('click', function () {
            modoFormularioBanner = 'crear';
            limpiarFormularioBanner();
            jQuery('#modal-banner-titulo-texto').text('Nuevo banner');
            jQuery('#modal-banner-subtitulo').text('Sube una imagen para tu página pública');

            new bootstrap.Modal(document.getElementById('modal-banner')).show();
        });

        jQuery('#contenedor-banners').on('click', '.btn-editar-banner', function () {
            var banner = jQuery(this).closest('.tarjeta-banner').data('banner');

            modoFormularioBanner = 'editar';
            limpiarFormularioBanner();
            jQuery('#modal-banner-titulo-texto').text('Editar banner');
            jQuery('#modal-banner-subtitulo').text('Cambia la imagen, el texto o su vigencia');

            jQuery('#id_banner').val(banner.id_banner);
            jQuery('#titulo_banner').val(banner.titulo || '');
            jQuery('#texto_banner').val(banner.texto || '');
            jQuery('#fecha_inicio_banner').val(banner.fecha_inicio ? String(banner.fecha_inicio).substring(0, 10) : '');
            jQuery('#fecha_fin_banner').val(banner.fecha_fin ? String(banner.fecha_fin).substring(0, 10) : '');
            jQuery('#orden_banner').val(banner.orden);

            // Se muestra la imagen que ya tiene; si no se elige otra, se
            // conserva (imagenElegida sigue en null y no se manda archivo).
            mostrarVistaPrevia(banner.imagen_url);

            jQuery('#seccion-estado-banner').prop('hidden', false);
            establecerEstadoBanner(banner.estado == 1);

            new bootstrap.Modal(document.getElementById('modal-banner')).show();
        });

        jQuery('#btn-guardar-banner').on('click', function () {
            if (modoFormularioBanner === 'crear' && !imagenElegida) {
                mostrarMensajeImagen('Elige una imagen para el banner.');
                return;
            }

            var fechaInicio = jQuery('#fecha_inicio_banner').val();
            var fechaFin = jQuery('#fecha_fin_banner').val();

            if (fechaInicio && fechaFin && fechaFin < fechaInicio) {
                notificarUsuario('La fecha "Hasta" no puede ser anterior a la fecha "Desde"', 'error');
                return;
            }

            // Va por FormData porque lleva un archivo; es el mismo patrón que
            // usa la carga masiva de Excel.
            var formulario = new FormData();
            formulario.append('titulo', jQuery('#titulo_banner').val());
            formulario.append('texto', jQuery('#texto_banner').val());
            formulario.append('fecha_inicio', fechaInicio);
            formulario.append('fecha_fin', fechaFin);
            formulario.append('orden', jQuery('#orden_banner').val() || 0);

            if (imagenElegida) {
                formulario.append('imagen', imagenElegida);
            }

            var url = 'request/banner/crear';

            if (modoFormularioBanner === 'editar') {
                url = 'request/banner/editar';
                formulario.append('id_banner', jQuery('#id_banner').val());
                // El checkbox del interruptor no lleva "name" a propósito: se
                // agrega aquí como 0/1 explícito, y solo al editar.
                formulario.append('estado', jQuery('#estado_banner').is(':checked') ? 1 : 0);
            }

            estadoBotonGuardarBanner('ocupado');

            axios.post(UrlGlobal + url, formulario, {
                headers: { 'Content-Type': 'multipart/form-data' }
            }).then(function (respuesta) {
                var datos = respuesta.data;

                if (datos.error != 0) {
                    estadoBotonGuardarBanner('normal');
                    notificarUsuario(datos.mensaje, 'error');
                    return;
                }

                estadoBotonGuardarBanner('exito');

                setTimeout(function () {
                    var modalBanner = bootstrap.Modal.getInstance(document.getElementById('modal-banner'));

                    if (modalBanner) {
                        modalBanner.hide();
                    }

                    estadoBotonGuardarBanner('normal');
                    cargarBanners();
                    avisarGuardado(modoFormularioBanner === 'crear' ? 'Banner creado correctamente' : 'Banner actualizado correctamente');
                }, ESPERA_CONFIRMACION_GUARDADO);
            }).catch(function () {
                estadoBotonGuardarBanner('normal');
                notificarUsuario('No pudimos subir el banner. Revisa tu conexión e inténtalo de nuevo.', 'error');
            });
        });

        jQuery('#contenedor-banners').on('click', '.btn-eliminar-banner', function () {
            var idBanner = jQuery(this).data('id_banner');

            Swal.fire({
                title: '¿Desactivar el banner?',
                text: 'Dejará de mostrarse en tu página pública. Su imagen se conserva y puedes reactivarlo desde "Mostrar inactivos".',
                icon: 'warning',
                background: colorVariable('--bg-card'),
                color: colorVariable('--text-primary'),
                confirmButtonColor: colorVariable('--accent'),
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar'
            }).then(function (resultado) {
                if (!resultado.isConfirmed) {
                    return;
                }

                axiosSipleInterno('POST', 'request/banner/eliminar', {}, { id_banner: idBanner }, true, function (respuesta) {
                    if (respuesta.error != 0) {
                        notificarUsuario(respuesta.mensaje, 'error');
                        return;
                    }

                    notificarUsuario('Banner desactivado correctamente', 'success');
                    cargarBanners();
                });
            });
        });

        // La pestaña se carga la primera vez que se abre, no al entrar a la
        // pantalla: quien solo viene a cambiar su horario no paga esa consulta.
        jQuery('#tab-banners').on('shown.bs.tab', function () {
            if (!bannersCargados) {
                cargarBanners();
            }
        });

        jQuery(document).ready(function () {
            cargarConfiguracion();

            iniciarGuiaSiCorresponde('horario', function () {
                iniciarTourContextual('horario', [
                    {
                        attachTo: { element: '#tour-dias-atencion', on: 'bottom' },
                        title: 'Días de atención',
                        text: 'Marca los días en que tu negocio recibe clientes.'
                    },
                    {
                        attachTo: { element: '#hora_apertura', on: 'top' },
                        title: 'Hora de apertura',
                        text: 'Indica a qué hora abres tu negocio.'
                    },
                    {
                        attachTo: { element: '#hora_cierre', on: 'top' },
                        title: 'Hora de cierre',
                        text: 'Indica a qué hora cierras y luego pulsa "Guardar cambios" para completar este paso.'
                    }
                ]);
            });
        });
    </script>
@endsection
