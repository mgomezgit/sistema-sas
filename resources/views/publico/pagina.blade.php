@extends('layout.publico')

@section('titulo', $negocio['nombre_negocio'])

@php
    // Todo lo que se calcula aquí es solo PRESENTACIÓN de datos que ya
    // llegaron filtrados y en su lista blanca desde SvcPaginaPublica; no se
    // consulta nada más en esta vista.

    $inicialNegocio = mb_strtoupper(mb_substr(trim($negocio['nombre_negocio']), 0, 1)) ?: '?';

    $diasSemana = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    $diasCortos = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    $diasActivos = [];
    if (! empty($negocio['dias_atencion'])) {
        $diasActivos = array_values(array_unique(array_filter(
            array_map('intval', explode(',', $negocio['dias_atencion'])),
            fn ($dia) => $dia >= 1 && $dia <= 7
        )));
        sort($diasActivos);
    }

    // "Lunes a sábado" si son consecutivos; si no, la lista corta de cada uno.
    $resumenDias = null;
    if (count($diasActivos) === 1) {
        $resumenDias = $diasSemana[$diasActivos[0]];
    } elseif (count($diasActivos) > 1) {
        $esConsecutivo = $diasActivos === range($diasActivos[0], end($diasActivos));
        $resumenDias = $esConsecutivo
            ? $diasSemana[$diasActivos[0]].' a '.$diasSemana[end($diasActivos)]
            : implode(', ', array_map(fn ($dia) => $diasCortos[$dia], $diasActivos));
    }

    $formatearHoraPublica = function (?string $hora) {
        if (empty($hora)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($hora)->format('g:i A');
        } catch (\Exception $e) {
            return null;
        }
    };

    $horaAperturaTexto = $formatearHoraPublica($negocio['hora_apertura'] ?? null);
    $horaCierreTexto = $formatearHoraPublica($negocio['hora_cierre'] ?? null);

    $whatsappLink = null;
    if (! empty($negocio['whatsapp_numero'])) {
        $whatsappDigitos = preg_replace('/\D/', '', $negocio['whatsapp_numero']);
        if ($whatsappDigitos !== '') {
            $whatsappLink = 'https://wa.me/'.$whatsappDigitos.'?text='.rawurlencode('Hola, quiero agendar una cita');
        }
    }

    // Solo se agrupa por categoría si hay más de una: con una sola categoría
    // (o ninguna) el encabezado no aporta orden, sería ruido.
    $categoriasDistintas = collect($servicios)->pluck('categoria')->filter()->unique()->count();
    $serviciosAgrupados = $categoriasDistintas > 1
        ? collect($servicios)->groupBy(fn ($servicio) => $servicio['categoria'] ?: 'Otros servicios')
        : collect(['' => collect($servicios)]);
@endphp

@section('content')
<div class="lienzo">
    <div class="mancha mancha1"></div>
    <div class="mancha mancha2"></div>
    <div class="mancha mancha3"></div>

    {{-- ================= BARRA SUPERIOR ================= --}}
    <nav class="nav">
        <div class="nav-marca">
            <div class="nav-logo">{{ $inicialNegocio }}</div>
            <div class="nav-nombre">{{ $negocio['nombre_negocio'] }}</div>
        </div>
        <div class="nav-links">
            <a data-scroll-a="seccion-servicios">Servicios</a>
            @if (count($equipo) > 0)
                <a data-scroll-a="seccion-equipo">Equipo</a>
            @endif
            @if (count($banners) > 0)
                <a data-scroll-a="seccion-promos">Promociones</a>
            @endif
            <a data-scroll-a="seccion-contacto">Contacto</a>
        </div>
        <button type="button" class="nav-cta" data-abrir-agendar>Agendar cita</button>
    </nav>

    {{-- ================= HERO ================= --}}
    <div class="hero">
        <div>
            <h1 class="hero-saludo">Hola, bienvenida a tu momento de calma</h1>
            <p class="hero-parrafo">
                En {{ $negocio['nombre_negocio'] }} te recibimos con atención real, gente cálida y el cuidado que te mereces.
                Agenda tu cita en un par de minutos, sin llamadas ni esperas.
            </p>
            <div class="hero-botones">
                <button type="button" class="btn-primario" data-abrir-agendar>Agendar una cita</button>
                <a href="#seccion-servicios" class="btn-fantasma" data-scroll-a="seccion-servicios">
                    <i class="bi bi-list-ul"></i> Ver servicios
                </a>
            </div>
            <div class="fila-valores">
                <div class="valor-item">
                    <div class="valor-icono"><i class="bi bi-check-lg"></i></div>
                    <span class="valor-texto">Atención personalizada</span>
                </div>
                <div class="valor-item">
                    <div class="valor-icono"><i class="bi bi-heart"></i></div>
                    <span class="valor-texto">Ambiente relajante</span>
                </div>
                <div class="valor-item">
                    <div class="valor-icono"><i class="bi bi-calendar2-check"></i></div>
                    <span class="valor-texto">Reservas fáciles</span>
                </div>
            </div>
        </div>
        <div class="hero-visual">
            <span class="hero-visual-letra">{{ $inicialNegocio }}</span>

            @if ($resumenDias || $horaAperturaTexto)
                <div class="widget-reserva">
                    <p class="widget-titulo">Horario de atención</p>
                    <div class="widget-dias">
                        @foreach ($diasCortos as $numeroDia => $nombreCorto)
                            <div class="chip-dia {{ in_array($numeroDia, $diasActivos, true) ? 'activo' : '' }}">{{ $nombreCorto }}</div>
                        @endforeach
                    </div>
                    @if ($horaAperturaTexto && $horaCierreTexto)
                        <div class="widget-horas">
                            <div class="chip-hora">{{ $horaAperturaTexto }} – {{ $horaCierreTexto }}</div>
                        </div>
                    @endif
                    <button type="button" class="btn-widget" data-abrir-agendar>Ver disponibilidad</button>
                </div>
            @endif
        </div>
    </div>

    {{-- ================= FRANJA DE INFO RÁPIDA ================= --}}
    @if ($resumenDias || $horaAperturaTexto || $negocio['telefono_contacto'])
        <div class="franja-info">
            @if ($resumenDias)
                <span class="item-info"><i class="bi bi-calendar-week"></i> {{ $resumenDias }}</span>
            @endif
            @if ($horaAperturaTexto && $horaCierreTexto)
                <span class="item-info"><i class="bi bi-clock"></i> {{ $horaAperturaTexto }} – {{ $horaCierreTexto }}</span>
            @endif
            @if ($negocio['telefono_contacto'])
                <span class="item-info" id="seccion-contacto"><i class="bi bi-telephone"></i> {{ $negocio['telefono_contacto'] }}</span>
            @endif
        </div>
    @endif

    <div class="divisor-ondulado">
        <svg viewBox="0 0 1440 70" preserveAspectRatio="none" width="100%" height="70">
            <path d="M0,35 C 320,80 1120,-10 1440,35 L1440,70 L0,70 Z" style="fill: color-mix(in srgb, var(--accent) 7%, var(--bg-page));"/>
        </svg>
    </div>

    {{-- ================= PROMOCIONES ================= --}}
    @if (count($banners) > 0)
        <div class="seccion seccion-promos" id="seccion-promos">
            <h2 class="titulo-seccion">Promociones para ti</h2>
            <p class="subtitulo-seccion">
                {{ count($banners) > 1 ? 'Toca una historia para verla completa' : 'Vigente por tiempo limitado' }}
            </p>

            @if (count($banners) > 1)
                <div class="historias">
                    @foreach ($banners as $indice => $banner)
                        <button type="button" class="historia" data-indice="{{ $indice }}">
                            <div class="aro-historia {{ $indice === 0 ? 'activo' : '' }}">
                                <div class="aro-interior">{{ \Illuminate\Support\Str::limit($banner['titulo'] ?? 'Promo', 14, '') }}</div>
                            </div>
                            <span class="historia-texto">{{ \Illuminate\Support\Str::limit($banner['titulo'] ?? 'Promo', 12) }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            @foreach ($banners as $indice => $banner)
                <div class="banner-grande" data-indice="{{ $indice }}" style="background-image:url('{{ $banner['imagen_url'] }}')" @if ($indice !== 0) hidden @endif>
                    <div class="banner-contenido">
                        @if ($banner['titulo'])
                            <h3 class="banner-titulo">{{ $banner['titulo'] }}</h3>
                        @endif
                        @if ($banner['texto'])
                            <p class="banner-texto">{{ $banner['texto'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ================= SERVICIOS ================= --}}
    <div class="seccion" id="seccion-servicios">
        <h2 class="titulo-seccion">Servicios que te van a encantar</h2>
        <p class="subtitulo-seccion">Elige el que más se ajuste a lo que necesitas hoy</p>

        @forelse ($serviciosAgrupados as $categoria => $serviciosDeCategoria)
            @if ($categoria !== '')
                <h3 class="titulo-categoria-servicio">{{ $categoria }}</h3>
            @endif
            <div class="grid-servicios">
                @foreach ($serviciosDeCategoria as $servicio)
                    <div class="tarjeta-servicio">
                        <div class="globo-icono"><i class="bi bi-sparkle"></i></div>
                        <div>
                            <p class="servicio-nombre">{{ $servicio['nombre'] }}</p>
                            <p class="servicio-duracion">{{ $servicio['duracion_minutos'] }} min</p>
                        </div>
                        <div class="servicio-precio">${{ number_format($servicio['precio'], 0, ',', '.') }}</div>
                    </div>
                @endforeach
            </div>
        @empty
            <p class="subtitulo-seccion">Todavía no hay servicios publicados. Vuelve pronto.</p>
        @endforelse
    </div>

    {{-- ================= EQUIPO ================= --}}
    @if (count($equipo) > 0)
        <div class="seccion" id="seccion-equipo">
            <h2 class="titulo-seccion">Quién te va a atender</h2>
            <p class="subtitulo-seccion">Gente real, cuidando de nuestras clientas</p>
            <div class="grid-equipo">
                @foreach ($equipo as $persona)
                    <div class="tarjeta-persona">
                        <div class="avatar-persona">{{ mb_strtoupper(mb_substr(trim($persona['nombre']), 0, 1)) ?: '?' }}</div>
                        <p class="persona-nombre">{{ $persona['nombre'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ================= PIE DE PÁGINA ================= --}}
    <div class="pie">
        <h3 class="pie-titulo">Te esperamos con los brazos abiertos</h3>
        @if ($negocio['telefono_contacto'])
            <p class="pie-sub">{{ $negocio['telefono_contacto'] }}</p>
        @endif
        @if ($negocio['politica_cancelacion'])
            <p class="pie-politica">{{ $negocio['politica_cancelacion'] }}</p>
        @endif
        <div class="pie-copy">{{ $negocio['nombre_negocio'] }} © {{ date('Y') }}</div>
    </div>

    {{-- ================= WHATSAPP FLOTANTE ================= --}}
    @if ($whatsappLink)
        <a href="{{ $whatsappLink }}" class="btn-whatsapp" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp">
            <i class="bi bi-whatsapp" style="font-size: 24px;"></i>
        </a>
    @endif

    {{-- ================= MODAL DE AGENDAR ================= --}}
    <div class="modal fade modal-publico" id="modal-agendar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agenda tu cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="error-agendar" class="aviso-error-agendar"></div>

                    <form id="form-agendar">
                        <input type="text" id="campo-trampa-agendar" name="sitio_web" tabindex="-1" autocomplete="off" aria-hidden="true">

                        <div class="campo-agendar">
                            <label for="servicio-agendar">Servicio</label>
                            <select class="form-select" id="servicio-agendar" name="id_recurso">
                                <option value="" disabled selected>Elige un servicio</option>
                                @foreach ($servicios as $servicio)
                                    <option value="{{ $servicio['id_recurso'] }}">
                                        {{ $servicio['nombre'] }} · {{ $servicio['duracion_minutos'] }} min · ${{ number_format($servicio['precio'], 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-6 campo-agendar">
                                <label for="fecha-agendar">Fecha</label>
                                <input type="date" class="form-control" id="fecha-agendar" name="fecha_reserva">
                            </div>
                            <div class="col-6 campo-agendar">
                                <label for="hora-agendar">Hora</label>
                                <input type="time" class="form-control" id="hora-agendar" name="hora_inicio">
                            </div>
                        </div>
                        <div id="aviso-fecha-publica" class="aviso-fecha-publica">
                            <span id="texto-aviso-fecha-publica"></span>
                        </div>

                        <div class="campo-agendar">
                            <label for="nombre-agendar">Tu nombre</label>
                            <input type="text" class="form-control" id="nombre-agendar" name="nombre" maxlength="150">
                        </div>

                        <div class="row">
                            <div class="col-6 campo-agendar">
                                <label for="telefono-agendar">Teléfono</label>
                                <input type="tel" class="form-control" id="telefono-agendar" name="telefono" maxlength="30">
                            </div>
                            <div class="col-6 campo-agendar">
                                <label for="email-agendar">Correo (opcional)</label>
                                <input type="email" class="form-control" id="email-agendar" name="email" maxlength="150">
                            </div>
                        </div>

                        <div class="campo-agendar">
                            <label for="notas-agendar">Notas (opcional)</label>
                            <textarea class="form-control" id="notas-agendar" name="notas" maxlength="500" rows="2"></textarea>
                        </div>

                        <button type="button" class="btn-primario w-100" id="btn-agendar-guardar">
                            <span class="texto-btn-agendar">Agendar cita</span>
                        </button>
                    </form>

                    <div id="panel-exito-agendar" class="panel-exito-agendar">
                        <i class="bi bi-check-circle"></i>
                        <p>Tu solicitud fue enviada, te confirmaremos pronto.</p>
                        <button type="button" class="btn-fantasma mt-3" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        var diasAtencion = @json($negocio['dias_atencion']);
        var urlAgendar = '{{ url('publico/'.$slug.'/agendar') }}';

        /* ---------- Historias / carrusel de promociones ---------- */
        jQuery('.historia').on('click', function () {
            var indice = jQuery(this).data('indice');

            jQuery('.aro-historia').removeClass('activo');
            jQuery(this).find('.aro-historia').addClass('activo');

            jQuery('.banner-grande').attr('hidden', true);
            jQuery('.banner-grande[data-indice="' + indice + '"]').removeAttr('hidden');
        });

        /* ---------- Scroll suave desde la barra y el hero ---------- */
        jQuery('[data-scroll-a]').on('click', function (evento) {
            var destino = document.getElementById(jQuery(this).data('scroll-a'));

            if (destino) {
                evento.preventDefault();
                destino.scrollIntoView({ behavior: 'smooth' });
            }
        });

        /* ---------- Modal de agendar ---------- */
        var modalAgendarEl = document.getElementById('modal-agendar');
        var modalAgendar = new bootstrap.Modal(modalAgendarEl);

        jQuery('[data-abrir-agendar]').on('click', function () {
            modalAgendar.show();
        });

        function ocultarAvisoFechaPublica() {
            jQuery('#aviso-fecha-publica').removeClass('visible');
        }

        function mostrarAvisoFechaPublica(motivo) {
            jQuery('#texto-aviso-fecha-publica').text(motivo);
            jQuery('#aviso-fecha-publica').addClass('visible');
        }

        // Misma regla que el calendario del admin (public/js/validacion-horario.js):
        // capa de prevención en el navegador, la validación real sigue en el servidor.
        function validarFechaPublica() {
            var motivo = motivoFechaNoDisponibleBase(
                jQuery('#fecha-agendar').val(),
                null,
                diasAtencion,
                'El negocio no atiende este día'
            );

            if (motivo === null) {
                ocultarAvisoFechaPublica();
            } else {
                mostrarAvisoFechaPublica(motivo);
            }

            return motivo === null;
        }

        jQuery('#fecha-agendar').on('change', validarFechaPublica);

        function estadoBotonAgendar(estado) {
            var boton = jQuery('#btn-agendar-guardar');

            if (estado === 'ocupado') {
                boton.prop('disabled', true).find('.texto-btn-agendar').text('Enviando...');
            } else {
                boton.prop('disabled', false).find('.texto-btn-agendar').text('Agendar cita');
            }
        }

        function ocultarErrorAgendar() {
            jQuery('#error-agendar').removeClass('visible').text('');
        }

        // .text() siempre: el mensaje puede traer de vuelta algo que escribió
        // la propia persona (por ejemplo su nombre dentro de un mensaje de
        // validación), así que nunca se pinta con .html().
        function mostrarErrorAgendar(mensaje) {
            var texto = Array.isArray(mensaje) ? mensaje.join(' ') : String(mensaje || 'No pudimos registrar tu solicitud.');
            jQuery('#error-agendar').text(texto).addClass('visible');
        }

        function limpiarFormularioAgendar() {
            document.getElementById('form-agendar').reset();
            ocultarAvisoFechaPublica();
            ocultarErrorAgendar();
            jQuery('#form-agendar').show();
            jQuery('#panel-exito-agendar').removeClass('visible');
            estadoBotonAgendar('normal');
        }

        jQuery('#fecha-agendar').attr('min', fechaDeHoy());
        modalAgendarEl.addEventListener('hidden.bs.modal', limpiarFormularioAgendar);

        jQuery('#btn-agendar-guardar').on('click', function () {
            if (!validarFechaPublica()) {
                return;
            }

            ocultarErrorAgendar();

            var datos = jQuery('#form-agendar').serializeObject();

            if (!datos.id_recurso || !datos.nombre || !datos.telefono || !datos.fecha_reserva || !datos.hora_inicio) {
                mostrarErrorAgendar('Completa los campos obligatorios para continuar.');
                return;
            }

            estadoBotonAgendar('ocupado');

            axios.post(urlAgendar, datos, { headers: { 'Accept': 'application/json' } })
                .then(function (respuesta) {
                    estadoBotonAgendar('normal');

                    if (respuesta.data.error == 0) {
                        jQuery('#form-agendar').hide();
                        jQuery('#panel-exito-agendar').addClass('visible');
                    } else {
                        mostrarErrorAgendar(respuesta.data.mensaje);
                    }
                })
                .catch(function (error) {
                    estadoBotonAgendar('normal');

                    var mensaje = (error.response && error.response.data && error.response.data.mensaje)
                        ? error.response.data.mensaje
                        : 'No pudimos comunicarnos con el servidor. Revisa tu conexión e inténtalo de nuevo.';

                    mostrarErrorAgendar(mensaje);
                });
        });
    })();
</script>
@endsection
